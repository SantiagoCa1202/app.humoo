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
        | Menus
        |--------------------------------------------------------------------------
        |
        | Stable logical menu.
        | Actual editable/historical content lives in menu_versions.
        |--------------------------------------------------------------------------
        */
        Schema::create('menus', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('workspace_id')
                ->constrained('workspaces')
                ->cascadeOnDelete();

            $table->string('name', 180);

            $table->text('description')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Category / type
            |--------------------------------------------------------------------------
            |
            | wedding
            | corporate
            | brunch
            | dinner
            | cocktail
            | buffet
            | seasonal
            | template
            |
            */
            $table->string('type', 64)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Current version
            |--------------------------------------------------------------------------
            |
            | 0 means the logical menu exists but no usable version has
            | been published yet.
            |--------------------------------------------------------------------------
            */
            $table->unsignedInteger('current_version')->default(0);

            /*
            |--------------------------------------------------------------------------
            | State
            |--------------------------------------------------------------------------
            |
            | draft
            | active
            | archived
            */
            $table->string('status', 32)->default('active');

            /*
            |--------------------------------------------------------------------------
            | Optional default guest count
            |--------------------------------------------------------------------------
            |
            | Useful for menu templates and costing previews.
            */
            $table->unsignedInteger('default_guest_count')->nullable();

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

            /*
            |--------------------------------------------------------------------------
            | Indexes
            |--------------------------------------------------------------------------
            */
            $table->index([
                'workspace_id',
                'name',
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
        | Menu Versions
        |--------------------------------------------------------------------------
        |
        | Each change that must remain historically reproducible should
        | create a new version.
        |--------------------------------------------------------------------------
        */
        Schema::create('menu_versions', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('workspace_id')
                ->constrained('workspaces')
                ->cascadeOnDelete();

            $table->foreignUlid('menu_id')
                ->constrained('menus')
                ->cascadeOnDelete();

            $table->unsignedInteger('version');

            /*
            |--------------------------------------------------------------------------
            | Snapshot identity
            |--------------------------------------------------------------------------
            |
            | Name/description are copied into each version intentionally.
            */
            $table->string('name', 180);

            $table->text('description')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Version status
            |--------------------------------------------------------------------------
            |
            | draft
            | review
            | approved
            | superseded
            | archived
            */
            $table->string('status', 32)->default('draft');

            /*
            |--------------------------------------------------------------------------
            | Locking
            |--------------------------------------------------------------------------
            |
            | Once a version is referenced by an event/production workflow,
            | it should normally become immutable.
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
            | Change tracking
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
                'menu_id',
                'version',
            ]);

            $table->index([
                'workspace_id',
                'menu_id',
            ]);

            $table->index([
                'menu_id',
                'status',
            ]);

            $table->index([
                'menu_id',
                'locked',
            ]);
        });

        /*
        |--------------------------------------------------------------------------
        | Menu Sections
        |--------------------------------------------------------------------------
        |
        | Examples:
        | Hors d'oeuvres
        | First Course
        | Main Course
        | Dessert
        | Late Night
        |--------------------------------------------------------------------------
        */
        Schema::create('menu_sections', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('workspace_id')
                ->constrained('workspaces')
                ->cascadeOnDelete();

            $table->foreignUlid('menu_version_id')
                ->constrained('menu_versions')
                ->cascadeOnDelete();

            $table->string('name', 150);

            $table->text('description')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Section type
            |--------------------------------------------------------------------------
            |
            | appetizer
            | starter
            | main
            | side
            | dessert
            | beverage
            | station
            | custom
            */
            $table->string('type', 64)->nullable();

            $table->unsignedInteger('position')->default(0);

            /*
            |--------------------------------------------------------------------------
            | Optional service timing
            |--------------------------------------------------------------------------
            |
            | Useful later for production/service timelines.
            */
            $table->timestamp('service_at')->nullable();

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index([
                'menu_version_id',
                'position',
            ]);

            $table->index([
                'workspace_id',
                'type',
            ]);
        });

        /*
        |--------------------------------------------------------------------------
        | Menu Items
        |--------------------------------------------------------------------------
        */
        Schema::create('menu_items', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('workspace_id')
                ->constrained('workspaces')
                ->cascadeOnDelete();

            $table->foreignUlid('menu_section_id')
                ->constrained('menu_sections')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Recipe relation
            |--------------------------------------------------------------------------
            |
            | nullable because a menu item might initially be text-only or
            | an externally supplied dish.
            */
            $table->foreignUlid('recipe_id')
                ->nullable()
                ->constrained('recipes')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Specific recipe version
            |--------------------------------------------------------------------------
            |
            | Strongly recommended once recipe_versions exists.
            |
            | This is the version the menu item was designed with.
            */
            $table->foreignUlid('recipe_version_id')
                ->nullable()
                ->constrained('recipe_versions')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Display information
            |--------------------------------------------------------------------------
            |
            | Kept as snapshot values so renaming the recipe later does not
            | rewrite old menu versions.
            */
            $table->string('name', 180);

            $table->text('description')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Item type
            |--------------------------------------------------------------------------
            |
            | dish
            | beverage
            | package
            | station
            | custom
            */
            $table->string('type', 64)->default('dish');

            /*
            |--------------------------------------------------------------------------
            | Course
            |--------------------------------------------------------------------------
            |
            | Useful independently from section name.
            */
            $table->string('course', 64)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Quantity / portions
            |--------------------------------------------------------------------------
            |
            | Examples:
            | 1 portion per guest
            | 0.5 portion per guest
            | 2 pieces per guest
            */
            $table->decimal('quantity_per_guest', 12, 4)->nullable();

            $table->string('serving_unit', 64)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Optional planned quantity
            |--------------------------------------------------------------------------
            |
            | Useful if a specific event/menu version decides that only
            | 100 portions are needed regardless of overall guest count.
            */
            $table->decimal('planned_quantity', 12, 4)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Cost snapshot
            |--------------------------------------------------------------------------
            |
            | Recipe costing can change later.
            | This snapshot can preserve the estimate used when approving
            | this particular menu version.
            */
            $table->decimal('estimated_unit_cost', 12, 4)->nullable();

            $table->char('cost_currency', 3)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Dietary / service flags
            |--------------------------------------------------------------------------
            */
            $table->boolean('optional')->default(false);

            $table->boolean('active')->default(true);

            $table->unsignedInteger('position')->default(0);

            $table->text('notes')->nullable();

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index([
                'menu_section_id',
                'position',
            ]);

            $table->index([
                'workspace_id',
                'recipe_id',
            ]);

            $table->index([
                'workspace_id',
                'recipe_version_id',
            ]);
        });

        /*
        |--------------------------------------------------------------------------
        | Dietary Tags
        |--------------------------------------------------------------------------
        |
        | Global/system tags can have workspace_id = NULL.
        | Custom workspace-specific tags carry workspace_id.
        |--------------------------------------------------------------------------
        */
        Schema::create('dietary_tags', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('workspace_id')
                ->nullable()
                ->constrained('workspaces')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Canonical key
            |--------------------------------------------------------------------------
            |
            | vegetarian
            | vegan
            | gluten_free
            | dairy_free
            | halal
            | kosher
            */
            $table->string('key', 100);

            $table->string('name', 150);

            $table->text('description')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Classification
            |--------------------------------------------------------------------------
            |
            | dietary
            | lifestyle
            | religious
            | preference
            */
            $table->string('type', 64)->default('dietary');

            $table->boolean('active')->default(true);

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | Cannot use a simple unique(workspace_id, key) to guarantee global
            | uniqueness with NULL on every MySQL setup, so application-level
            | validation should also enforce this rule.
            |--------------------------------------------------------------------------
            */
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
        | Menu Item Dietary Tags
        |--------------------------------------------------------------------------
        */
        Schema::create('menu_item_dietary_tags', function (Blueprint $table) {
            $table->foreignUlid('menu_item_id')
                ->constrained('menu_items')
                ->cascadeOnDelete();

            $table->foreignUlid('dietary_tag_id')
                ->constrained('dietary_tags')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Source
            |--------------------------------------------------------------------------
            |
            | manual
            | recipe
            | ai
            */
            $table->string('source', 32)->default('manual');

            $table->timestamps();

            $table->primary([
                'menu_item_id',
                'dietary_tag_id',
            ]);
        });

        /*
        |--------------------------------------------------------------------------
        | Event Menus
        |--------------------------------------------------------------------------
        |
        | Assigns an exact menu version to an event.
        |
        | The critical relationship is menu_version_id, not just menu_id.
        |--------------------------------------------------------------------------
        */
        Schema::create('event_menus', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('workspace_id')
                ->constrained('workspaces')
                ->cascadeOnDelete();

            $table->foreignUlid('event_id')
                ->constrained('events')
                ->cascadeOnDelete();

            $table->foreignUlid('menu_id')
                ->constrained('menus')
                ->restrictOnDelete();

            $table->foreignUlid('menu_version_id')
                ->constrained('menu_versions')
                ->restrictOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Role
            |--------------------------------------------------------------------------
            |
            | Allows an event to eventually have:
            | primary menu
            | staff meal
            | cocktail menu
            | late-night menu
            | kids menu
            |
            */
            $table->string('type', 64)->default('primary');

            /*
            |--------------------------------------------------------------------------
            | Guest allocation
            |--------------------------------------------------------------------------
            |
            | Allows multiple menus within one event.
            |
            | Example:
            | primary = 170 guests
            | vegan = 15 guests
            */
            $table->unsignedInteger('guest_count')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Status
            |--------------------------------------------------------------------------
            |
            | draft
            | approved
            | superseded
            */
            $table->string('status', 32)->default('approved');

            /*
            |--------------------------------------------------------------------------
            | Approval / assignment
            |--------------------------------------------------------------------------
            */
            $table->foreignUlid('assigned_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('assigned_at')->nullable();

            $table->foreignUlid('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('approved_at')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Snapshot
            |--------------------------------------------------------------------------
            |
            | Optional summary of the approved assignment.
            */
            $table->json('snapshot_json')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | An event can have multiple versions historically, but only one
            | assignment for this exact event/version/type combination.
            |--------------------------------------------------------------------------
            */
            $table->unique([
                'event_id',
                'menu_version_id',
                'type',
            ]);

            $table->index([
                'workspace_id',
                'event_id',
            ]);

            $table->index([
                'event_id',
                'status',
            ]);

            $table->index([
                'event_id',
                'type',
            ]);
        });

        /*
        |--------------------------------------------------------------------------
        | Menu Version Changes
        |--------------------------------------------------------------------------
        |
        | Supports MenuVersionComparison and operational impact warnings.
        |--------------------------------------------------------------------------
        */
        Schema::create('menu_version_changes', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('workspace_id')
                ->constrained('workspaces')
                ->cascadeOnDelete();

            $table->foreignUlid('menu_id')
                ->constrained('menus')
                ->cascadeOnDelete();

            $table->foreignUlid('from_version_id')
                ->nullable()
                ->constrained('menu_versions')
                ->nullOnDelete();

            $table->foreignUlid('to_version_id')
                ->constrained('menu_versions')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | What changed
            |--------------------------------------------------------------------------
            |
            | section.added
            | item.removed
            | item.recipe_changed
            | item.quantity_changed
            | etc.
            */
            $table->string('change_type', 100);

            /*
            |--------------------------------------------------------------------------
            | Entity involved in change
            |--------------------------------------------------------------------------
            |
            | section
            | item
            | menu
            */
            $table->string('entity_type', 64)->nullable();

            $table->ulid('entity_id')->nullable();

            $table->json('before_value')->nullable();

            $table->json('after_value')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Severity
            |--------------------------------------------------------------------------
            */
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
                'menu_id',
                'to_version_id',
            ]);

            $table->index([
                'workspace_id',
                'affects_production',
            ]);

            $table->index([
                'to_version_id',
                'severity',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_version_changes');

        Schema::dropIfExists('event_menus');

        Schema::dropIfExists('menu_item_dietary_tags');
        Schema::dropIfExists('dietary_tags');

        Schema::dropIfExists('menu_items');
        Schema::dropIfExists('menu_sections');
        Schema::dropIfExists('menu_versions');
        Schema::dropIfExists('menus');
    }
};
