<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Units
        |--------------------------------------------------------------------------
        |
        | Global canonical units.
        |
        | Examples:
        | g, kg, oz, lb
        | ml, l, tsp, tbsp, cup, gal
        | each, piece, portion
        |--------------------------------------------------------------------------
        */
        Schema::create('units', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->string('key', 64)->unique();
            $table->string('name', 100);
            $table->string('symbol', 32);

            $table->enum('dimension', [
                'weight',
                'volume',
                'count',
                'portion',
                'length',
                'temperature',
                'time',
                'other',
            ]);

            /*
            |--------------------------------------------------------------------------
            | Base conversion
            |--------------------------------------------------------------------------
            |
            | Example:
            |
            | dimension = weight
            | base unit = gram
            |
            | g  = 1
            | kg = 1000
            |
            | dimension = volume
            | base unit = ml
            |
            | ml = 1
            | l  = 1000
            |
            | Null means not directly convertible through a standard factor.
            |--------------------------------------------------------------------------
            */
            $table->decimal('base_factor', 20, 10)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Precision
            |--------------------------------------------------------------------------
            */
            $table->unsignedTinyInteger('decimal_places')->default(2);

            /*
            |--------------------------------------------------------------------------
            | Display
            |--------------------------------------------------------------------------
            */
            $table->boolean('active')->default(true);

            $table->boolean('system')->default(true);

            $table->timestamps();

            $table->index([
                'dimension',
                'active',
            ]);
        });

        /*
        |--------------------------------------------------------------------------
        | Unit Conversions
        |--------------------------------------------------------------------------
        |
        | Useful for conversions that cannot be inferred only from base_factor,
        | or for explicit business conversion rules.
        |--------------------------------------------------------------------------
        */
        Schema::create('unit_conversions', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('from_unit_id')
                ->constrained('units')
                ->cascadeOnDelete();

            $table->foreignUlid('to_unit_id')
                ->constrained('units')
                ->cascadeOnDelete();

            $table->decimal('factor', 20, 10);

            $table->decimal('offset', 20, 10)->default(0);

            $table->boolean('active')->default(true);

            $table->timestamps();

            $table->unique([
                'from_unit_id',
                'to_unit_id',
            ]);
        });

        /*
        |--------------------------------------------------------------------------
        | Allergens
        |--------------------------------------------------------------------------
        |
        | Global canonical allergens.
        |--------------------------------------------------------------------------
        */
        Schema::create('allergens', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->string('key', 100)->unique();

            $table->string('name', 150);

            $table->text('description')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Regulatory / grouping
            |--------------------------------------------------------------------------
            |
            | Example:
            | us_major
            | eu_major
            | custom
            |--------------------------------------------------------------------------
            */
            $table->string('category', 64)->nullable();

            $table->boolean('active')->default(true);

            $table->timestamps();

            $table->index([
                'category',
                'active',
            ]);
        });

        /*
        |--------------------------------------------------------------------------
        | Recipe Tags
        |--------------------------------------------------------------------------
        |
        | workspace_id = NULL => global/system tag.
        |
        | Examples:
        | sauce
        | dessert
        | vegan
        | banquet
        | garde_manger
        |--------------------------------------------------------------------------
        */
        Schema::create('recipe_tags', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('workspace_id')
                ->nullable()
                ->constrained('workspaces')
                ->cascadeOnDelete();

            $table->string('key', 100);

            $table->string('name', 150);

            $table->text('description')->nullable();

            $table->boolean('active')->default(true);

            $table->timestamps();

            $table->index([
                'workspace_id',
                'key',
            ]);

            $table->index([
                'workspace_id',
                'active',
            ]);
        });

        /*
        |--------------------------------------------------------------------------
        | Recipes
        |--------------------------------------------------------------------------
        |
        | Stable logical/master recipe.
        | Historical and operational content belongs to recipe_versions.
        |--------------------------------------------------------------------------
        */
        Schema::create('recipes', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('workspace_id')
                ->constrained('workspaces')
                ->cascadeOnDelete();

            $table->string('name', 180);

            $table->text('description')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Category
            |--------------------------------------------------------------------------
            |
            | sauce
            | soup
            | appetizer
            | entree
            | dessert
            | pastry
            | prep
            | component
            |
            | Keep string, not enum.
            |--------------------------------------------------------------------------
            */
            $table->string('category', 100)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Recipe type
            |--------------------------------------------------------------------------
            |
            | standard
            | component
            | batch
            | beverage
            |--------------------------------------------------------------------------
            */
            $table->string('type', 64)->default('standard');

            /*
            |--------------------------------------------------------------------------
            | Image
            |--------------------------------------------------------------------------
            */
            $table->foreignUlid('image_document_id')
                ->nullable()
                ->constrained('documents')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Current published/approved version
            |--------------------------------------------------------------------------
            */
            $table->unsignedInteger('current_version')->default(0);

            /*
            |--------------------------------------------------------------------------
            | Status
            |--------------------------------------------------------------------------
            |
            | draft
            | active
            | archived
            |--------------------------------------------------------------------------
            */
            $table->string('status', 32)->default('active');

            /*
            |--------------------------------------------------------------------------
            | Optional internal code
            |--------------------------------------------------------------------------
            */
            $table->string('recipe_code', 64)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Metadata
            |--------------------------------------------------------------------------
            */
            $table->json('metadata')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Audit
            |--------------------------------------------------------------------------
            */
            $table->foreignUlid('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignUlid('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->unique([
                'workspace_id',
                'recipe_code',
            ]);

            $table->index([
                'workspace_id',
                'name',
            ]);

            $table->index([
                'workspace_id',
                'category',
            ]);

            $table->index([
                'workspace_id',
                'status',
            ]);

            $table->index([
                'workspace_id',
                'type',
            ]);
        });

        /*
        |--------------------------------------------------------------------------
        | Recipe Versions
        |--------------------------------------------------------------------------
        |
        | Historical/immutable operational snapshot.
        |--------------------------------------------------------------------------
        */
        Schema::create('recipe_versions', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('workspace_id')
                ->constrained('workspaces')
                ->cascadeOnDelete();

            $table->foreignUlid('recipe_id')
                ->constrained('recipes')
                ->cascadeOnDelete();

            $table->unsignedInteger('version');

            /*
            |--------------------------------------------------------------------------
            | Snapshot identity
            |--------------------------------------------------------------------------
            */
            $table->string('name', 180);

            $table->text('description')->nullable();

            $table->string('category', 100)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Yield
            |--------------------------------------------------------------------------
            */
            $table->decimal('base_yield', 18, 4)->nullable();

            $table->foreignUlid('yield_unit_id')
                ->nullable()
                ->constrained('units')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Portion details
            |--------------------------------------------------------------------------
            |
            | Example:
            | base_yield = 10
            | yield_unit = portion
            | portion_size = 180
            | portion_unit = g
            |--------------------------------------------------------------------------
            */
            $table->decimal('portion_size', 18, 4)->nullable();

            $table->foreignUlid('portion_unit_id')
                ->nullable()
                ->constrained('units')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Preparation times
            |--------------------------------------------------------------------------
            */
            $table->unsignedInteger('prep_time_minutes')->nullable();

            $table->unsignedInteger('cook_time_minutes')->nullable();

            $table->unsignedInteger('rest_time_minutes')->nullable();

            $table->unsignedInteger('total_time_minutes')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Shelf life / storage
            |--------------------------------------------------------------------------
            */
            $table->unsignedInteger('shelf_life_hours')->nullable();

            $table->text('storage_instructions')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Temperature
            |--------------------------------------------------------------------------
            |
            | Use decimal + unit instead of ambiguous free text when possible.
            |--------------------------------------------------------------------------
            */
            $table->decimal('storage_temperature_min', 10, 2)->nullable();

            $table->decimal('storage_temperature_max', 10, 2)->nullable();

            $table->foreignUlid('temperature_unit_id')
                ->nullable()
                ->constrained('units')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Equipment
            |--------------------------------------------------------------------------
            */
            $table->text('equipment_required')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Version state
            |--------------------------------------------------------------------------
            |
            | draft
            | review
            | approved
            | superseded
            | archived
            |--------------------------------------------------------------------------
            */
            $table->string('status', 32)->default('draft');

            /*
            |--------------------------------------------------------------------------
            | Locking
            |--------------------------------------------------------------------------
            |
            | Once referenced by an approved menu/event/prep workflow,
            | this version should normally become immutable.
            |--------------------------------------------------------------------------
            */
            $table->boolean('locked')->default(false);

            $table->timestamp('locked_at')->nullable();

            $table->foreignUlid('locked_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Approval
            |--------------------------------------------------------------------------
            */
            $table->timestamp('approved_at')->nullable();

            $table->foreignUlid('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Change information
            |--------------------------------------------------------------------------
            */
            $table->text('change_summary')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Source
            |--------------------------------------------------------------------------
            |
            | manual
            | duplicated
            | import
            | ai
            |--------------------------------------------------------------------------
            */
            $table->string('source', 32)->default('manual');

            /*
            |--------------------------------------------------------------------------
            | Optimistic locking
            |--------------------------------------------------------------------------
            */
            $table->unsignedInteger('revision')->default(1);

            /*
            |--------------------------------------------------------------------------
            | Optional cost snapshot
            |--------------------------------------------------------------------------
            |
            | Current computed estimates for this version.
            | Detailed cost breakdown still comes from ingredients.
            |--------------------------------------------------------------------------
            */
            $table->decimal('estimated_total_cost', 14, 4)->nullable();

            $table->decimal('estimated_cost_per_yield', 14, 4)->nullable();

            $table->char('cost_currency', 3)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Metadata
            |--------------------------------------------------------------------------
            */
            $table->json('metadata')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Audit
            |--------------------------------------------------------------------------
            */
            $table->foreignUlid('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->unique([
                'recipe_id',
                'version',
            ]);

            $table->index([
                'workspace_id',
                'recipe_id',
            ]);

            $table->index([
                'recipe_id',
                'status',
            ]);

            $table->index([
                'recipe_id',
                'locked',
            ]);
        });

        /*
        |--------------------------------------------------------------------------
        | Recipe Ingredients
        |--------------------------------------------------------------------------
        */
        Schema::create('recipe_ingredients', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('workspace_id')
                ->constrained('workspaces')
                ->cascadeOnDelete();

            $table->foreignUlid('recipe_version_id')
                ->constrained('recipe_versions')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Inventory item
            |--------------------------------------------------------------------------
            |
            | Nullable because recipe creation may happen before inventory
            | is fully configured.
            |--------------------------------------------------------------------------
            */
            $table->foreignUlid('inventory_item_id')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | Sub-recipe / component recipe
            |--------------------------------------------------------------------------
            |
            | Allows:
            | Hollandaise recipe uses clarified butter recipe/component.
            |--------------------------------------------------------------------------
            */
            $table->foreignUlid('component_recipe_id')
                ->nullable()
                ->constrained('recipes')
                ->nullOnDelete();

            $table->foreignUlid('component_recipe_version_id')
                ->nullable()
                ->constrained('recipe_versions')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Snapshot name
            |--------------------------------------------------------------------------
            |
            | Preserves historical display even when inventory item names change.
            |--------------------------------------------------------------------------
            */
            $table->string('ingredient_name', 180);

            /*
            |--------------------------------------------------------------------------
            | Quantity
            |--------------------------------------------------------------------------
            */
            $table->decimal('quantity', 18, 6);

            $table->foreignUlid('unit_id')
                ->constrained('units')
                ->restrictOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Waste / yield
            |--------------------------------------------------------------------------
            |
            | waste_percentage:
            | 0 - 100
            |
            | Example:
            | 20% trim loss.
            |--------------------------------------------------------------------------
            */
            $table->decimal('waste_percentage', 7, 4)->default(0);

            /*
            |--------------------------------------------------------------------------
            | Optional yield percentage
            |--------------------------------------------------------------------------
            |
            | Can be used instead of/in addition to waste where appropriate.
            |--------------------------------------------------------------------------
            */
            $table->decimal('yield_percentage', 7, 4)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Purchase vs recipe unit conversion
            |--------------------------------------------------------------------------
            */
            $table->decimal('conversion_factor', 18, 8)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Cost snapshot
            |--------------------------------------------------------------------------
            */
            $table->decimal('unit_cost', 14, 4)->nullable();

            $table->decimal('extended_cost', 14, 4)->nullable();

            $table->char('cost_currency', 3)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Ingredient behavior
            |--------------------------------------------------------------------------
            */
            $table->boolean('optional')->default(false);

            $table->boolean('scalable')->default(true);

            /*
            |--------------------------------------------------------------------------
            | Preparation information
            |--------------------------------------------------------------------------
            |
            | Examples:
            | diced
            | julienned
            | softened
            | divided
            |--------------------------------------------------------------------------
            */
            $table->string('preparation', 255)->nullable();

            $table->unsignedInteger('position')->default(0);

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index([
                'recipe_version_id',
                'position',
            ]);

            $table->index([
                'workspace_id',
                'inventory_item_id',
            ]);

            $table->index([
                'workspace_id',
                'component_recipe_id',
            ]);
        });

        /*
        |--------------------------------------------------------------------------
        | Recipe Steps
        |--------------------------------------------------------------------------
        */
        Schema::create('recipe_steps', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('workspace_id')
                ->constrained('workspaces')
                ->cascadeOnDelete();

            $table->foreignUlid('recipe_version_id')
                ->constrained('recipe_versions')
                ->cascadeOnDelete();

            $table->unsignedInteger('position');

            /*
            |--------------------------------------------------------------------------
            | Step title
            |--------------------------------------------------------------------------
            |
            | Example:
            | Make reduction
            | Finish sauce
            |--------------------------------------------------------------------------
            */
            $table->string('title', 180)->nullable();

            $table->text('instruction');

            /*
            |--------------------------------------------------------------------------
            | Duration
            |--------------------------------------------------------------------------
            */
            $table->unsignedInteger('duration_minutes')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Station
            |--------------------------------------------------------------------------
            |
            | Added as FK only if stations migration already exists before this.
            |--------------------------------------------------------------------------
            */
            $table->ulid('station_id')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | Temperature
            |--------------------------------------------------------------------------
            */
            $table->decimal('temperature', 10, 2)->nullable();

            $table->foreignUlid('temperature_unit_id')
                ->nullable()
                ->constrained('units')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Step type
            |--------------------------------------------------------------------------
            |
            | prep
            | cook
            | chill
            | rest
            | hold
            | plate
            |--------------------------------------------------------------------------
            */
            $table->string('type', 64)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Critical / food safety flag
            |--------------------------------------------------------------------------
            */
            $table->boolean('critical')->default(false);

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique([
                'recipe_version_id',
                'position',
            ]);

            $table->index([
                'workspace_id',
                'station_id',
            ]);
        });

        /*
        |--------------------------------------------------------------------------
        | Recipe Yields
        |--------------------------------------------------------------------------
        |
        | Alternative/supported yields for the same version.
        |
        | Example:
        | 1 batch
        | 24 portions
        | 6 liters
        |--------------------------------------------------------------------------
        */
        Schema::create('recipe_yields', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('workspace_id')
                ->constrained('workspaces')
                ->cascadeOnDelete();

            $table->foreignUlid('recipe_version_id')
                ->constrained('recipe_versions')
                ->cascadeOnDelete();

            $table->decimal('quantity', 18, 4);

            $table->foreignUlid('unit_id')
                ->constrained('units')
                ->restrictOnDelete();

            $table->string('label', 150)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Conversion against base yield
            |--------------------------------------------------------------------------
            |
            | Example:
            | base = 1 batch
            | alternate = 24 portions
            |
            | factor_to_base may help normalize scaling.
            |--------------------------------------------------------------------------
            */
            $table->decimal('factor_to_base', 18, 8)->nullable();

            $table->boolean('is_default')->default(false);

            $table->timestamps();

            $table->index([
                'recipe_version_id',
                'is_default',
            ]);
        });

        /*
        |--------------------------------------------------------------------------
        | Recipe Version Allergens
        |--------------------------------------------------------------------------
        |
        | Store allergens per version, because ingredient changes can alter them.
        |--------------------------------------------------------------------------
        */
        Schema::create('recipe_version_allergens', function (Blueprint $table) {
            $table->foreignUlid('recipe_version_id')
                ->constrained('recipe_versions')
                ->cascadeOnDelete();

            $table->foreignUlid('allergen_id')
                ->constrained('allergens')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Source
            |--------------------------------------------------------------------------
            |
            | manual
            | ingredient
            | ai
            |--------------------------------------------------------------------------
            */
            $table->string('source', 32)->default('manual');

            /*
            |--------------------------------------------------------------------------
            | Presence type
            |--------------------------------------------------------------------------
            |
            | contains
            | may_contain
            | cross_contact
            |--------------------------------------------------------------------------
            */
            $table->string('presence', 32)->default('contains');

            $table->timestamps();

            $table->primary([
                'recipe_version_id',
                'allergen_id',
            ]);
        });

        /*
        |--------------------------------------------------------------------------
        | Recipe Tag Assignments
        |--------------------------------------------------------------------------
        */
        Schema::create('recipe_tag_assignments', function (Blueprint $table) {
            $table->foreignUlid('recipe_id')
                ->constrained('recipes')
                ->cascadeOnDelete();

            $table->foreignUlid('recipe_tag_id')
                ->constrained('recipe_tags')
                ->cascadeOnDelete();

            $table->timestamps();

            $table->primary([
                'recipe_id',
                'recipe_tag_id',
            ]);
        });

        /*
        |--------------------------------------------------------------------------
        | Recipe Version Changes
        |--------------------------------------------------------------------------
        |
        | Allows RecipeVersionHistory / comparison / operational alerts.
        |--------------------------------------------------------------------------
        */
        Schema::create('recipe_version_changes', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('workspace_id')
                ->constrained('workspaces')
                ->cascadeOnDelete();

            $table->foreignUlid('recipe_id')
                ->constrained('recipes')
                ->cascadeOnDelete();

            $table->foreignUlid('from_version_id')
                ->nullable()
                ->constrained('recipe_versions')
                ->nullOnDelete();

            $table->foreignUlid('to_version_id')
                ->constrained('recipe_versions')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Examples:
            |
            | ingredient.added
            | ingredient.removed
            | ingredient.quantity_changed
            | ingredient.unit_changed
            | step.changed
            | yield.changed
            | allergen.changed
            |--------------------------------------------------------------------------
            */
            $table->string('change_type', 100);

            $table->string('entity_type', 64)->nullable();

            $table->ulid('entity_id')->nullable();

            $table->json('before_value')->nullable();

            $table->json('after_value')->nullable();

            $table->string('severity', 32)->default('info');

            /*
            |--------------------------------------------------------------------------
            | Production impact
            |--------------------------------------------------------------------------
            */
            $table->boolean('affects_production')->default(false);

            $table->boolean('reviewed')->default(false);

            $table->foreignUlid('reviewed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();

            $table->index([
                'recipe_id',
                'to_version_id',
            ]);

            $table->index([
                'workspace_id',
                'affects_production',
            ]);
        });

        Schema::table('menu_items', function (Blueprint $table) {
            $table->foreign('recipe_id')
                ->references('id')
                ->on('recipes')
                ->nullOnDelete();

            $table->foreign('recipe_version_id')
                ->references('id')
                ->on('recipe_versions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('menu_items')) {
            Schema::table('menu_items', function (Blueprint $table) {
                $table->dropForeign(['recipe_id']);
                $table->dropForeign(['recipe_version_id']);
            });
        }

        Schema::dropIfExists('recipe_version_changes');

        Schema::dropIfExists('recipe_tag_assignments');
        Schema::dropIfExists('recipe_version_allergens');

        Schema::dropIfExists('recipe_yields');
        Schema::dropIfExists('recipe_steps');
        Schema::dropIfExists('recipe_ingredients');

        Schema::dropIfExists('recipe_versions');
        Schema::dropIfExists('recipes');

        Schema::dropIfExists('recipe_tags');
        Schema::dropIfExists('allergens');

        Schema::dropIfExists('unit_conversions');
        Schema::dropIfExists('units');
    }
};
