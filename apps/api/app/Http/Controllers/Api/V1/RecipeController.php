<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Actions\Recipes\CreateRecipe;
use App\Application\Actions\Recipes\UpdateRecipe;
use App\Http\Controllers\Controller;
use App\Http\Requests\Recipes\StoreRecipeRequest;
use App\Http\Requests\Recipes\UpdateRecipeRequest;
use App\Http\Resources\AllergenResource;
use App\Http\Resources\RecipeResource;
use App\Http\Resources\RecipeTagResource;
use App\Http\Resources\RecipeVersionChangeResource;
use App\Http\Resources\RecipeVersionResource;
use App\Http\Resources\UnitResource;
use App\Models\Allergen;
use App\Models\Recipe;
use App\Models\RecipeTag;
use App\Models\RecipeVersion;
use App\Models\Unit;
use App\Services\AuditLogger;
use Illuminate\Http\Request;

class RecipeController extends Controller
{
    public function catalog(Request $request)
    {
        abort_unless($this->canAccessCatalog($request), 403);

        return response()->json([
            'data' => $this->catalogPayload(app('currentWorkspace')->id),
        ]);
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', Recipe::class);

        $workspace = app('currentWorkspace');
        $search = trim((string) $request->input('search', ''));
        $status = trim((string) $request->input('status', ''));
        $category = trim((string) $request->input('category', ''));
        $perPage = max(1, min((int) $request->input('per_page', 25), 100));

        $recipes = Recipe::query()
            ->where('workspace_id', $workspace->id)
            ->with($this->recipeRelations())
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($searchQuery) use ($search): void {
                    $searchQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('recipe_code', 'like', "%{$search}%");
                });
            })
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($category !== '', fn ($query) => $query->where('category', $category))
            ->latest('updated_at')
            ->cursorPaginate($perPage);

        return response()->json([
            'data' => RecipeResource::collection(collect($recipes->items())),
            'path' => $recipes->path(),
            'per_page' => $recipes->perPage(),
            'next_cursor' => $recipes->nextCursor()?->encode(),
            'next_page_url' => $recipes->nextPageUrl(),
            'prev_cursor' => $recipes->previousCursor()?->encode(),
            'prev_page_url' => $recipes->previousPageUrl(),
            'meta' => [
                'catalog' => $this->catalogPayload($workspace->id),
            ],
        ]);
    }

    public function store(
        StoreRecipeRequest $request,
        CreateRecipe $action,
        AuditLogger $auditLogger
    ) {
        $this->authorize('create', Recipe::class);

        $workspace = app('currentWorkspace');
        $recipe = $action->execute(
            $workspace->id,
            $request->user()->id,
            $request->validated()
        );

        $recipe = $this->loadRecipe($recipe);

        $auditLogger->logWorkspaceAction(
            $request,
            $workspace->id,
            $request->user()->id,
            'recipe.created',
            Recipe::class,
            $recipe->id,
            null,
            $recipe->toArray()
        );

        return response()->json([
            'data' => new RecipeResource($recipe),
            'meta' => [
                'catalog' => $this->catalogPayload($workspace->id),
            ],
        ], 201);
    }

    public function show(Recipe $recipe)
    {
        $workspace = app('currentWorkspace');

        abort_unless($recipe->workspace_id === $workspace->id, 404);
        $this->authorize('view', $recipe);

        return response()->json([
            'data' => new RecipeResource($this->loadRecipe($recipe)),
            'meta' => [
                'catalog' => $this->catalogPayload($workspace->id),
            ],
        ]);
    }

    public function update(
        UpdateRecipeRequest $request,
        Recipe $recipe,
        UpdateRecipe $action,
        AuditLogger $auditLogger
    ) {
        $workspace = app('currentWorkspace');

        abort_unless($recipe->workspace_id === $workspace->id, 404);
        $this->authorize('update', $recipe);

        $before = $recipe->toArray();
        $updated = $action->execute(
            $recipe,
            $workspace->id,
            $request->user()->id,
            $request->validated('current_version_id'),
            $request->integer('expected_revision'),
            $request->safe()->except([
                'current_version_id',
                'expected_revision',
            ])
        );

        if (!$updated) {
            return response()->json([
                'message' => 'Resource conflict.',
                'code' => 'VERSION_CONFLICT',
                'data' => (new RecipeResource(
                    $this->loadRecipe(
                        Recipe::query()
                            ->whereKey($recipe->getKey())
                            ->where('workspace_id', $workspace->id)
                            ->firstOrFail()
                    )
                ))->resolve(),
            ], 409);
        }

        $updated = $this->loadRecipe($updated);

        $auditLogger->logWorkspaceAction(
            $request,
            $workspace->id,
            $request->user()->id,
            'recipe.updated',
            Recipe::class,
            $updated->id,
            $before,
            $updated->toArray()
        );

        return response()->json([
            'data' => new RecipeResource($updated),
            'meta' => [
                'catalog' => $this->catalogPayload($workspace->id),
            ],
        ]);
    }

    public function versions(Recipe $recipe)
    {
        $workspace = app('currentWorkspace');

        abort_unless($recipe->workspace_id === $workspace->id, 404);
        $this->authorize('view', $recipe);

        $versions = RecipeVersion::query()
            ->where('workspace_id', $workspace->id)
            ->where('recipe_id', $recipe->id)
            ->with($this->versionRelations())
            ->orderByDesc('version')
            ->get();

        return response()->json([
            'data' => RecipeVersionResource::collection($versions),
        ]);
    }

    public function version(Recipe $recipe, RecipeVersion $recipeVersion)
    {
        $workspace = app('currentWorkspace');

        abort_unless(
            $recipe->workspace_id === $workspace->id
            && $recipeVersion->workspace_id === $workspace->id
            && $recipeVersion->recipe_id === $recipe->id,
            404
        );
        $this->authorize('view', $recipe);

        return response()->json([
            'data' => new RecipeVersionResource(
                $recipeVersion->load($this->versionRelations())
            ),
        ]);
    }

    public function comparison(Request $request, Recipe $recipe, RecipeVersion $recipeVersion)
    {
        $workspace = app('currentWorkspace');

        abort_unless(
            $recipe->workspace_id === $workspace->id
            && $recipeVersion->workspace_id === $workspace->id
            && $recipeVersion->recipe_id === $recipe->id,
            404
        );
        $this->authorize('view', $recipe);

        $baseVersion = null;
        $baseVersionId = $request->input('base_version_id');

        if ($baseVersionId) {
            $baseVersion = RecipeVersion::query()
                ->where('workspace_id', $workspace->id)
                ->where('recipe_id', $recipe->id)
                ->findOrFail($baseVersionId);
        } else {
            $baseVersion = RecipeVersion::query()
                ->where('workspace_id', $workspace->id)
                ->where('recipe_id', $recipe->id)
                ->where('version', '<', $recipeVersion->version)
                ->orderByDesc('version')
                ->first();
        }

        $recipeVersion->load($this->versionRelations());
        $recipeVersion->load([
            'changes' => fn ($query) => $query->orderBy('created_at'),
        ]);

        return response()->json([
            'data' => [
                'recipe' => (new RecipeResource($this->loadRecipe($recipe)))->resolve(),
                'base_version' => $baseVersion
                    ? (new RecipeVersionResource($baseVersion->load($this->versionRelations())))->resolve()
                    : null,
                'target_version' => (new RecipeVersionResource($recipeVersion))->resolve(),
                'changes' => RecipeVersionChangeResource::collection($recipeVersion->changes)->resolve(),
            ],
        ]);
    }

    private function canAccessCatalog(Request $request): bool
    {
        $workspace = app('currentWorkspace');
        $user = $request->user();

        return $user
            && (
                $user->hasWorkspacePermission($workspace->id, 'recipes.view')
                || $user->hasWorkspacePermission($workspace->id, 'recipes.create')
                || $user->hasWorkspacePermission($workspace->id, 'recipes.edit')
            );
    }

    private function catalogPayload(string $workspaceId): array
    {
        $units = Unit::query()
            ->where('active', true)
            ->orderBy('dimension')
            ->orderBy('name')
            ->get();
        $tags = RecipeTag::query()
            ->where('active', true)
            ->where(function ($query) use ($workspaceId): void {
                $query->whereNull('workspace_id')
                    ->orWhere('workspace_id', $workspaceId);
            })
            ->orderBy('name')
            ->get();
        $allergens = Allergen::query()
            ->where('active', true)
            ->orderBy('name')
            ->get();

        return [
            'units' => UnitResource::collection($units)->resolve(),
            'tags' => RecipeTagResource::collection($tags)->resolve(),
            'allergens' => AllergenResource::collection($allergens)->resolve(),
        ];
    }

    private function recipeRelations(): array
    {
        return [
            'tags',
            'createdBy',
            'updatedBy',
            'currentVersionRecord.createdBy',
            'currentVersionRecord.approvedBy',
            'currentVersionRecord.yieldUnit',
            'currentVersionRecord.temperatureUnit',
            'currentVersionRecord.ingredients.unit',
            'currentVersionRecord.steps.temperatureUnit',
            'currentVersionRecord.yields.unit',
            'currentVersionRecord.allergens',
        ];
    }

    private function versionRelations(): array
    {
        return [
            'createdBy',
            'approvedBy',
            'yieldUnit',
            'temperatureUnit',
            'ingredients.unit',
            'steps.temperatureUnit',
            'yields.unit',
            'allergens',
        ];
    }

    private function loadRecipe(Recipe $recipe): Recipe
    {
        return $recipe->fresh($this->recipeRelations());
    }
}
