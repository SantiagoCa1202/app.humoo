<?php

namespace App\AI\Tools;

use App\AI\Policy\ActionPolicy;
use Illuminate\Validation\ValidationException;

class ToolRegistry
{
    private ActionPolicy $actionPolicy;

    public function __construct(?ActionPolicy $actionPolicy = null)
    {
        $this->actionPolicy = $actionPolicy ?? new ActionPolicy();
    }

    private const ACTION_ALIASES = [
        'show_events' => 'events.list',
        'list_events' => 'events.list',
        'show_event' => 'events.detail',
        'show_clients' => 'clients.list',
        'list_clients' => 'clients.list',
        'show_client' => 'clients.detail',
        'show_contacts' => 'contacts.list',
        'list_contacts' => 'contacts.list',
        'show_contact' => 'contacts.detail',
        'show_venues' => 'venues.list',
        'list_venues' => 'venues.list',
        'show_venue' => 'venues.detail',
        'create_event' => 'events.create',
        'new_event' => 'events.create',
        'add_event' => 'events.create',
        'update_event' => 'events.update',
        'cancel_event' => 'events.cancel',
        'delete_event' => 'events.delete',
        'create_client' => 'clients.create',
        'update_client' => 'clients.update',
        'delete_client' => 'clients.delete',
        'create_contact' => 'contacts.create',
        'update_contact' => 'contacts.update',
        'delete_contact' => 'contacts.delete',
        'create_venue' => 'venues.create',
        'update_venue' => 'venues.update',
        'delete_venue' => 'venues.delete',
        'show_my_tasks' => 'tasks.mine',
        'show_tasks' => 'tasks.list',
        'list_tasks' => 'tasks.list',
        'show_task' => 'tasks.detail',
        'search_tasks' => 'tasks.search',
        'read_task' => 'tasks.read',
        'assign_task' => 'tasks.assign',
        'update_task_status' => 'tasks.status.update',
        'complete_task' => 'tasks.complete',
        'delete_task' => 'tasks.delete',
        'show_prep_lists' => 'prep.list',
        'show_prep' => 'prep.detail',
        'generate_prep' => 'prep.generate',
        'regenerate_prep' => 'prep.regenerate',
        'update_prep' => 'prep.update',
        'list_prep_items' => 'prep.items.list',
        'show_prep_item' => 'prep.items.detail',
        'update_prep_item_details' => 'prep.items.update',
        'complete_prep_item' => 'prep.items.complete',
        'reopen_prep_item' => 'prep.items.reopen',
        'assign_prep_item' => 'prep.items.assign',
        'unassign_prep_item' => 'prep.items.unassign',
        'prep_items.update' => 'prep.items.update',
        'update_prep_item' => 'prep.items.update',
        'update_task' => 'tasks.update',
        'create_task' => 'tasks.create',
        'show_teams' => 'teams.list',
        'list_teams' => 'teams.list',
        'create_team' => 'teams.create',
        'update_team' => 'teams.update',
        'delete_team' => 'teams.delete',
        'show_stations' => 'stations.list',
        'list_stations' => 'stations.list',
        'create_station' => 'stations.create',
        'update_station' => 'stations.update',
        'delete_station' => 'stations.delete',
        'show_shifts' => 'shifts.list',
        'list_shifts' => 'shifts.list',
        'create_shift' => 'shifts.create',
        'update_shift' => 'shifts.update',
        'delete_shift' => 'shifts.delete',
        'show_availability' => 'availability.list',
        'list_availability' => 'availability.list',
        'update_availability' => 'availability.sync',
        'create_menu' => 'menus.create',
        'search_menus' => 'menus.search',
        'show_menu' => 'menus.show',
        'rename_menu' => 'menus.rename',
        'add_menu_item' => 'menus.items.add',
        'move_menu_item_section' => 'menus.items.move_section',
        'reorder_menu_item' => 'menus.items.reorder',
        'update_menu' => 'menus.update',
        'update_menu_item' => 'menus.items.update',
        'delete_menu_item' => 'menus.items.delete',
        'duplicate_menu' => 'menus.duplicate',
        'delete_menu' => 'menus.delete',
        'list_recipes' => 'recipes.list',
        'show_recipe' => 'recipes.detail',
        'create_recipe' => 'recipes.create',
        'create_recipes' => 'recipes.create_many',
        'update_recipe' => 'recipes.update',
        'edit_recipe' => 'recipes.edit',
        'duplicate_recipe' => 'recipes.duplicate',
        'delete_recipe' => 'recipes.delete',
        'scale_recipe' => 'recipes.scale',
        'recipe_versions' => 'recipes.versions',
        'show_documents' => 'documents.list',
        'list_documents' => 'documents.list',
        'show_document' => 'documents.detail',
        'retry_document_extraction' => 'documents.retry_extraction',
        'link_document_to_event' => 'documents.link_event',
        'show_beos' => 'beos.list',
        'list_beos' => 'beos.list',
        'show_beo' => 'beos.detail',
        'show_notifications' => 'notifications.list',
        'list_notifications' => 'notifications.list',
        'read_notifications' => 'notifications.read_all',
        'notification_preferences' => 'notification_preferences.list',
        'update_notification_preference' => 'notification_preferences.update',
        'show_workspace' => 'workspace.detail',
        'update_workspace' => 'workspace.update',
        'show_members' => 'members.list',
        'show_member' => 'members.detail',
        'invite_member' => 'members.invite',
        'update_member_role' => 'members.update',
        'remove_member' => 'members.remove',
    ];

    private const TOOLS = [
        'events.list' => [
            'action_id' => 'events.list',
            'component' => 'events.list',
            'description' => 'List workspace events using safe server filters.',
            'entity_type' => 'event',
            'module' => 'events',
            'mode' => 'read',
            'operation_type' => 'read',
            'permission' => 'events.view',
            'requires_confirmation' => false,
            'schema_version' => 1,
        ],
        'execution_plans.latest' => [
            'action_id' => 'execution_plans.latest', 'component' => 'action.result', 'description' => 'Show the latest persisted execution-plan progress for this conversation. Use it to report queued, running, completed, partial, or failed batch work without creating duplicate writes.',
            'entity_type' => 'execution_plan', 'module' => 'ai', 'mode' => 'read', 'operation_type' => 'read',
            'permission' => 'recipes.view', 'requires_confirmation' => false, 'schema_version' => 1,
        ],
        'events.detail' => [
            'action_id' => 'events.detail',
            'component' => 'events.summary',
            'description' => 'Show the current event details from the active workspace.',
            'entity_type' => 'event',
            'module' => 'events',
            'mode' => 'read',
            'operation_type' => 'read',
            'permission' => 'events.view',
            'requires_confirmation' => false,
            'schema_version' => 1,
        ],
        'events.create' => [
            'action_id' => 'events.create',
            'component' => 'action.preview',
            'description' => 'Prepare a new event for explicit confirmation.',
            'entity_type' => 'event',
            'module' => 'events',
            'mode' => 'write',
            'operation_type' => 'create',
            'permission' => 'events.create',
            'requires_confirmation' => true,
            'result_component' => 'action.result',
            'schema_version' => 1,
        ],
        'events.update' => [
            'action_id' => 'events.update',
            'component' => 'action.preview',
            'description' => 'Prepare an event update with optimistic locking.',
            'entity_type' => 'event',
            'module' => 'events',
            'mode' => 'write',
            'operation_type' => 'update',
            'permission' => 'events.edit',
            'requires_confirmation' => true,
            'result_component' => 'action.result',
            'schema_version' => 1,
        ],
        'events.cancel' => [
            'action_id' => 'events.cancel',
            'component' => 'action.preview',
            'description' => 'Prepare an event cancellation for explicit confirmation.',
            'entity_type' => 'event',
            'module' => 'events',
            'mode' => 'write',
            'operation_type' => 'cancel',
            'permission' => 'events.edit',
            'requires_confirmation' => true,
            'result_component' => 'action.result',
            'schema_version' => 1,
        ],
        'events.delete' => [
            'action_id' => 'events.delete',
            'component' => 'action.preview',
            'description' => 'Prepare deletion of an event after dependency checks.',
            'entity_type' => 'event',
            'module' => 'events',
            'mode' => 'write',
            'operation_type' => 'delete',
            'permission' => 'events.delete',
            'requires_confirmation' => true,
            'result_component' => 'action.result',
            'schema_version' => 1,
        ],
        'clients.list' => [
            'action_id' => 'clients.list', 'component' => 'clients.list', 'description' => 'List clients in the active workspace.',
            'entity_type' => 'client', 'module' => 'clients', 'mode' => 'read', 'operation_type' => 'read', 'permission' => 'clients.view', 'requires_confirmation' => false, 'schema_version' => 1,
        ],
        'clients.detail' => [
            'action_id' => 'clients.detail', 'component' => 'clients.detail', 'description' => 'Show current client details.',
            'entity_type' => 'client', 'module' => 'clients', 'mode' => 'read', 'operation_type' => 'read', 'permission' => 'clients.view', 'requires_confirmation' => false, 'schema_version' => 1,
        ],
        'clients.create' => [
            'action_id' => 'clients.create', 'component' => 'action.preview', 'description' => 'Prepare a client creation.',
            'entity_type' => 'client', 'module' => 'clients', 'mode' => 'write', 'operation_type' => 'create', 'permission' => 'clients.create', 'requires_confirmation' => true, 'result_component' => 'action.result', 'schema_version' => 1,
        ],
        'clients.update' => [
            'action_id' => 'clients.update', 'component' => 'action.preview', 'description' => 'Prepare a client update.',
            'entity_type' => 'client', 'module' => 'clients', 'mode' => 'write', 'operation_type' => 'update', 'permission' => 'clients.edit', 'requires_confirmation' => true, 'result_component' => 'action.result', 'schema_version' => 1,
        ],
        'clients.delete' => [
            'action_id' => 'clients.delete', 'component' => 'action.preview', 'description' => 'Prepare client deletion after dependency checks.',
            'entity_type' => 'client', 'module' => 'clients', 'mode' => 'write', 'operation_type' => 'delete', 'permission' => 'clients.delete', 'requires_confirmation' => true, 'result_component' => 'action.result', 'schema_version' => 1,
        ],
        'contacts.list' => [
            'action_id' => 'contacts.list', 'component' => 'contacts.list', 'description' => 'List contacts in the active workspace.',
            'entity_type' => 'contact', 'module' => 'contacts', 'mode' => 'read', 'operation_type' => 'read', 'permission' => 'contacts.view', 'requires_confirmation' => false, 'schema_version' => 1,
        ],
        'contacts.detail' => [
            'action_id' => 'contacts.detail', 'component' => 'contacts.detail', 'description' => 'Show current contact details.',
            'entity_type' => 'contact', 'module' => 'contacts', 'mode' => 'read', 'operation_type' => 'read', 'permission' => 'contacts.view', 'requires_confirmation' => false, 'schema_version' => 1,
        ],
        'contacts.create' => [
            'action_id' => 'contacts.create', 'component' => 'action.preview', 'description' => 'Prepare a contact creation.',
            'entity_type' => 'contact', 'module' => 'contacts', 'mode' => 'write', 'operation_type' => 'create', 'permission' => 'contacts.create', 'requires_confirmation' => true, 'result_component' => 'action.result', 'schema_version' => 1,
        ],
        'contacts.update' => [
            'action_id' => 'contacts.update', 'component' => 'action.preview', 'description' => 'Prepare a contact update.',
            'entity_type' => 'contact', 'module' => 'contacts', 'mode' => 'write', 'operation_type' => 'update', 'permission' => 'contacts.edit', 'requires_confirmation' => true, 'result_component' => 'action.result', 'schema_version' => 1,
        ],
        'contacts.delete' => [
            'action_id' => 'contacts.delete', 'component' => 'action.preview', 'description' => 'Prepare contact deletion after dependency checks.',
            'entity_type' => 'contact', 'module' => 'contacts', 'mode' => 'write', 'operation_type' => 'delete', 'permission' => 'contacts.delete', 'requires_confirmation' => true, 'result_component' => 'action.result', 'schema_version' => 1,
        ],
        'venues.list' => [
            'action_id' => 'venues.list', 'component' => 'venues.list', 'description' => 'List venues in the active workspace.',
            'entity_type' => 'venue', 'module' => 'venues', 'mode' => 'read', 'operation_type' => 'read', 'permission' => 'venues.view', 'requires_confirmation' => false, 'schema_version' => 1,
        ],
        'venues.detail' => [
            'action_id' => 'venues.detail', 'component' => 'venues.detail', 'description' => 'Show current venue details.',
            'entity_type' => 'venue', 'module' => 'venues', 'mode' => 'read', 'operation_type' => 'read', 'permission' => 'venues.view', 'requires_confirmation' => false, 'schema_version' => 1,
        ],
        'venues.create' => [
            'action_id' => 'venues.create', 'component' => 'action.preview', 'description' => 'Prepare a venue creation.',
            'entity_type' => 'venue', 'module' => 'venues', 'mode' => 'write', 'operation_type' => 'create', 'permission' => 'venues.create', 'requires_confirmation' => true, 'result_component' => 'action.result', 'schema_version' => 1,
        ],
        'venues.update' => [
            'action_id' => 'venues.update', 'component' => 'action.preview', 'description' => 'Prepare a venue update.',
            'entity_type' => 'venue', 'module' => 'venues', 'mode' => 'write', 'operation_type' => 'update', 'permission' => 'venues.edit', 'requires_confirmation' => true, 'result_component' => 'action.result', 'schema_version' => 1,
        ],
        'venues.delete' => [
            'action_id' => 'venues.delete', 'component' => 'action.preview', 'description' => 'Prepare venue deletion after dependency checks.',
            'entity_type' => 'venue', 'module' => 'venues', 'mode' => 'write', 'operation_type' => 'delete', 'permission' => 'venues.delete', 'requires_confirmation' => true, 'result_component' => 'action.result', 'schema_version' => 1,
        ],
        'prep.list' => [
            'action_id' => 'prep.list',
            'component' => 'prep.list',
            'description' => 'List active prep lists for the current workspace.',
            'entity_type' => 'prep_list',
            'module' => 'prep',
            'mode' => 'read',
            'operation_type' => 'read',
            'permission' => 'prep_lists.view',
            'requires_confirmation' => false,
            'schema_version' => 1,
        ],
        'prep.detail' => [
            'action_id' => 'prep.detail',
            'component' => 'prep.detail',
            'description' => 'Show one prep list and its current production items.',
            'entity_type' => 'prep_list',
            'module' => 'prep',
            'mode' => 'read',
            'operation_type' => 'read',
            'permission' => 'prep_lists.view',
            'requires_confirmation' => false,
            'schema_version' => 1,
        ],
        'prep.items.list' => [
            'action_id' => 'prep.items.list',
            'component' => 'prep.detail',
            'description' => 'List production items in the active workspace or prep list.',
            'entity_type' => 'prep_item',
            'module' => 'prep',
            'mode' => 'read',
            'operation_type' => 'read',
            'permission' => 'prep_lists.view',
            'requires_confirmation' => false,
            'schema_version' => 1,
        ],
        'prep.items.detail' => [
            'action_id' => 'prep.items.detail',
            'component' => 'prep.detail',
            'description' => 'Show one production item from the active workspace.',
            'entity_type' => 'prep_item',
            'module' => 'prep',
            'mode' => 'read',
            'operation_type' => 'read',
            'permission' => 'prep_lists.view',
            'requires_confirmation' => false,
            'schema_version' => 1,
        ],
        'prep.generate' => [
            'action_id' => 'prep.generate',
            'component' => 'prep.preview',
            'description' => 'Prepare a deterministic prep list generation from the current event menu.',
            'entity_type' => 'prep_list',
            'module' => 'prep',
            'mode' => 'write',
            'operation_type' => 'generate',
            'permission' => 'prep_lists.edit',
            'requires_confirmation' => true,
            'result_component' => 'prep.detail',
            'schema_version' => 1,
        ],
        'prep.regenerate' => [
            'action_id' => 'prep.regenerate',
            'component' => 'prep.preview',
            'description' => 'Prepare a new prep list version from fresh event menu data.',
            'entity_type' => 'prep_list',
            'module' => 'prep',
            'mode' => 'write',
            'operation_type' => 'regenerate',
            'permission' => 'prep_lists.edit',
            'requires_confirmation' => true,
            'result_component' => 'prep.detail',
            'schema_version' => 1,
        ],
        'prep.update' => [
            'action_id' => 'prep.update',
            'component' => 'action.preview',
            'description' => 'Prepare a prep list metadata update for confirmation.',
            'entity_type' => 'prep_list',
            'module' => 'prep',
            'mode' => 'write',
            'operation_type' => 'update',
            'permission' => 'prep_lists.edit',
            'requires_confirmation' => true,
            'result_component' => 'prep.detail',
            'schema_version' => 1,
        ],
        'prep.items.update' => [
            'action_id' => 'prep.items.update',
            'component' => 'action.preview',
            'description' => 'Prepare a production item update for confirmation.',
            'entity_type' => 'prep_item',
            'module' => 'prep',
            'mode' => 'write',
            'operation_type' => 'update',
            'permission' => 'prep_lists.edit',
            'requires_confirmation' => true,
            'result_component' => 'action.result',
            'schema_version' => 1,
        ],
        'prep.items.complete' => [
            'action_id' => 'prep.items.complete',
            'component' => 'action.preview',
            'description' => 'Complete a production item after explicit confirmation.',
            'entity_type' => 'prep_item',
            'module' => 'prep',
            'mode' => 'write',
            'operation_type' => 'complete',
            'permission' => 'prep_lists.edit',
            'requires_confirmation' => true,
            'result_component' => 'action.result',
            'schema_version' => 1,
        ],
        'prep.items.reopen' => [
            'action_id' => 'prep.items.reopen',
            'component' => 'action.preview',
            'description' => 'Reopen a completed production item after explicit confirmation.',
            'entity_type' => 'prep_item',
            'module' => 'prep',
            'mode' => 'write',
            'operation_type' => 'reopen',
            'permission' => 'prep_lists.edit',
            'requires_confirmation' => true,
            'result_component' => 'action.result',
            'schema_version' => 1,
        ],
        'prep.items.assign' => [
            'action_id' => 'prep.items.assign',
            'component' => 'action.preview',
            'description' => 'Assign a production item to an active workspace member.',
            'entity_type' => 'prep_item',
            'module' => 'prep',
            'mode' => 'write',
            'operation_type' => 'assign',
            'permission' => 'prep_lists.edit',
            'requires_confirmation' => true,
            'result_component' => 'action.result',
            'schema_version' => 1,
        ],
        'prep.items.unassign' => [
            'action_id' => 'prep.items.unassign',
            'component' => 'action.preview',
            'description' => 'Remove the primary assignment from a production item.',
            'entity_type' => 'prep_item',
            'module' => 'prep',
            'mode' => 'write',
            'operation_type' => 'unassign',
            'permission' => 'prep_lists.edit',
            'requires_confirmation' => true,
            'result_component' => 'action.result',
            'schema_version' => 1,
        ],
        'tasks.mine' => [
            'action_id' => 'tasks.mine',
            'component' => 'tasks.mine',
            'description' => 'List open tasks assigned to the current membership.',
            'entity_type' => 'task',
            'module' => 'tasks',
            'mode' => 'read',
            'operation_type' => 'read',
            'permission' => 'tasks.view',
            'requires_confirmation' => false,
            'schema_version' => 1,
        ],
        'tasks.list' => [
            'action_id' => 'tasks.list',
            'component' => 'tasks.mine',
            'description' => 'List workspace tasks using safe filters.',
            'entity_type' => 'task',
            'module' => 'tasks',
            'mode' => 'read',
            'operation_type' => 'read',
            'permission' => 'tasks.view',
            'requires_confirmation' => false,
            'schema_version' => 1,
        ],
        'tasks.detail' => [
            'action_id' => 'tasks.detail',
            'component' => 'tasks.mine',
            'description' => 'Show one workspace task after tenant-safe resolution.',
            'entity_type' => 'task',
            'module' => 'tasks',
            'mode' => 'read',
            'operation_type' => 'read',
            'permission' => 'tasks.view',
            'requires_confirmation' => false,
            'schema_version' => 1,
        ],
        'tasks.search' => [
            'action_id' => 'tasks.search',
            'component' => 'tasks.list',
            'description' => 'Search workspace tasks by text, date, status, priority, assignee, team, station, or event. This is a read step: when the user requested an assignment, update, completion, or deletion, use the returned exact task IDs in the corresponding write tool and do not finish with the search result alone.',
            'entity_type' => 'task',
            'module' => 'tasks',
            'mode' => 'read',
            'operation_type' => 'read',
            'permission' => 'tasks.view',
            'requires_confirmation' => false,
            'schema_version' => 1,
        ],
        'tasks.read' => [
            'action_id' => 'tasks.read',
            'component' => 'tasks.detail',
            'description' => 'Show one workspace task after tenant-safe resolution.',
            'entity_type' => 'task',
            'module' => 'tasks',
            'mode' => 'read',
            'operation_type' => 'read',
            'permission' => 'tasks.view',
            'requires_confirmation' => false,
            'schema_version' => 1,
        ],
        'tasks.update' => [
            'action_id' => 'tasks.update',
            'component' => 'action.preview',
            'description' => 'Prepare a safe preview to update one or more tasks. Use task_id for one exact task, or task_ids/search/task_search/date filters for the selected set. Include membership_id or member_search when the request assigns or reassigns a task, together with any other requested changes. Always return the confirmation preview before execution.',
            'entity_type' => 'task',
            'module' => 'tasks',
            'mode' => 'write',
            'operation_type' => 'update',
            'permission' => 'tasks.edit',
            'requires_confirmation' => true,
            'result_component' => 'action.result',
            'schema_version' => 1,
        ],
        'tasks.create' => [
            'action_id' => 'tasks.create',
            'component' => 'action.preview',
            'description' => 'Prepare a safe preview to create one task from a clearly expressed request. If the user asks for multiple new tasks in the same turn, use tasks.create_many so no requested task is omitted.',
            'entity_type' => 'task',
            'module' => 'tasks',
            'mode' => 'write',
            'operation_type' => 'create',
            'permission' => 'tasks.create',
            'requires_confirmation' => true,
            'result_component' => 'action.result',
            'input_schema' => [
                'type' => 'object',
                'required' => ['title'],
                'properties' => [
                    'blocked_reason' => ['type' => ['string', 'null']],
                    'description' => ['type' => ['string', 'null']],
                    'event_id' => ['type' => ['string', 'null']],
                    'event_search' => ['type' => ['string', 'null']],
                    'team_id' => ['type' => ['string', 'null']],
                    'station_id' => ['type' => ['string', 'null']],
                    'membership_id' => ['type' => ['string', 'null']],
                    'due_at' => ['type' => ['string', 'null']],
                    'duration_minutes' => ['type' => ['integer', 'null'], 'minimum' => 1, 'maximum' => 1440],
                    'priority' => ['type' => 'string', 'enum' => ['low', 'normal', 'high', 'urgent']],
                    'starts_at' => ['type' => ['string', 'null']],
                    'status' => ['type' => 'string', 'enum' => ['todo', 'in_progress', 'blocked', 'done', 'cancelled']],
                    'title' => ['type' => ['string', 'null']],
                    'type' => ['type' => ['string', 'null']],
                    'team_search' => ['type' => ['string', 'null']],
                    'station_search' => ['type' => ['string', 'null']],
                    'member_search' => ['type' => ['string', 'null']],
                ],
            ],
            'schema_version' => 1,
        ],
        'tasks.create_many' => [
            'action_id' => 'tasks.create_many',
            'component' => 'action.preview',
            'description' => 'Prepare one safe preview for creating multiple tasks. Use this capability whenever the user requests two or more new tasks in one turn; preserve every task, its schedule, priority, and assignee.',
            'entity_type' => 'task',
            'module' => 'tasks',
            'mode' => 'write',
            'operation_type' => 'create_many',
            'permission' => 'tasks.create',
            'requires_confirmation' => true,
            'result_component' => 'action.result',
            'input_schema' => [
                'type' => 'object',
                'required' => ['tasks'],
                'properties' => [
                    'tasks' => [
                        'type' => 'array',
                        'minItems' => 2,
                        'items' => [
                            'type' => 'object',
                            'additionalProperties' => false,
                            'required' => ['title'],
                            'properties' => [
                                'blocked_reason' => ['type' => ['string', 'null']],
                                'description' => ['type' => ['string', 'null']],
                                'event_id' => ['type' => ['string', 'null']],
                                'event_search' => ['type' => ['string', 'null']],
                                'team_id' => ['type' => ['string', 'null']],
                                'team_search' => ['type' => ['string', 'null']],
                                'station_id' => ['type' => ['string', 'null']],
                                'station_search' => ['type' => ['string', 'null']],
                                'membership_id' => ['type' => ['string', 'null']],
                                'member_search' => ['type' => ['string', 'null']],
                                'due_at' => ['type' => ['string', 'null']],
                                'duration_minutes' => ['type' => ['integer', 'null'], 'minimum' => 1, 'maximum' => 1440],
                                'priority' => ['type' => ['string', 'null'], 'enum' => ['low', 'normal', 'high', 'urgent', null]],
                                'starts_at' => ['type' => ['string', 'null']],
                                'status' => ['type' => ['string', 'null'], 'enum' => ['todo', 'in_progress', 'blocked', 'done', 'cancelled', null]],
                                'title' => ['type' => 'string'],
                                'type' => ['type' => ['string', 'null']],
                                'timezone' => ['type' => ['string', 'null']],
                            ],
                        ],
                    ],
                ],
            ],
            'schema_version' => 1,
        ],
        'tasks.assign' => [
            'action_id' => 'tasks.assign',
            'component' => 'action.preview',
            'description' => 'Prepare a task assignment or reassignment for explicit confirmation. Resolve the destination member from the workspace; if the name is partial or has multiple matches, return the candidate choices and wait for the user selection. Preserve the selected task ID or task IDs.',
            'entity_type' => 'task',
            'module' => 'tasks',
            'mode' => 'write',
            'operation_type' => 'assign',
            'permission' => 'tasks.edit',
            'requires_confirmation' => true,
            'result_component' => 'action.result',
            'schema_version' => 1,
        ],
        'tasks.status.update' => [
            'action_id' => 'tasks.status.update',
            'component' => 'action.preview',
            'description' => 'Prepare a task status change for explicit confirmation. Use task_id for one exact task, or task_ids/search/task_search/date filters for all selected tasks.',
            'entity_type' => 'task',
            'module' => 'tasks',
            'mode' => 'write',
            'operation_type' => 'update',
            'permission' => 'tasks.edit',
            'requires_confirmation' => true,
            'result_component' => 'action.result',
            'schema_version' => 1,
        ],
        'tasks.complete' => [
            'action_id' => 'tasks.complete',
            'component' => 'action.preview',
            'description' => 'Prepare marking one or more selected tasks as completed for explicit confirmation. For plural requests, pass every matching task_id in task_ids or use the explicit search filter; never complete only the first match.',
            'entity_type' => 'task',
            'module' => 'tasks',
            'mode' => 'write',
            'operation_type' => 'complete',
            'permission' => 'tasks.edit',
            'requires_confirmation' => true,
            'result_component' => 'action.result',
            'schema_version' => 1,
        ],
        'tasks.delete' => [
            'action_id' => 'tasks.delete',
            'component' => 'action.preview',
            'description' => 'Prepare deletion of one or more selected workspace tasks after explicit confirmation. Use task_id for one exact task, or task_ids/search/date/member filters for the selected set. Never delete only the first match of a plural request.',
            'entity_type' => 'task',
            'module' => 'tasks',
            'mode' => 'write',
            'operation_type' => 'delete',
            'permission' => 'tasks.delete',
            'requires_confirmation' => true,
            'result_component' => 'action.result',
            'schema_version' => 1,
        ],
        'documents.list' => [
            'action_id' => 'documents.list', 'component' => 'action.result', 'description' => 'List workspace documents and their processing status.',
            'entity_type' => 'document', 'module' => 'documents', 'mode' => 'read', 'operation_type' => 'read', 'permission' => 'events.view', 'requires_confirmation' => false, 'schema_version' => 1,
        ],
        'documents.detail' => [
            'action_id' => 'documents.detail', 'component' => 'action.result', 'description' => 'Show one workspace document and its processing status.',
            'entity_type' => 'document', 'module' => 'documents', 'mode' => 'read', 'operation_type' => 'read', 'permission' => 'events.view', 'requires_confirmation' => false, 'schema_version' => 1,
        ],
        'documents.retry_extraction' => [
            'action_id' => 'documents.retry_extraction', 'component' => 'action.preview', 'description' => 'Prepare a retry of a failed document extraction.',
            'entity_type' => 'document', 'module' => 'documents', 'mode' => 'write', 'operation_type' => 'retry', 'permission' => 'events.edit', 'requires_confirmation' => true, 'result_component' => 'action.result', 'schema_version' => 1,
        ],
        'documents.link_event' => [
            'action_id' => 'documents.link_event', 'component' => 'action.preview', 'description' => 'Prepare linking a document to a workspace event.',
            'entity_type' => 'document', 'module' => 'documents', 'mode' => 'write', 'operation_type' => 'update', 'permission' => 'events.edit', 'requires_confirmation' => true, 'result_component' => 'action.result', 'schema_version' => 1,
        ],
        'beos.list' => [
            'action_id' => 'beos.list', 'component' => 'action.result', 'description' => 'List BEO event orders in the active workspace.',
            'entity_type' => 'beo', 'module' => 'beos', 'mode' => 'read', 'operation_type' => 'read', 'permission' => 'events.view', 'requires_confirmation' => false, 'schema_version' => 1,
        ],
        'beos.detail' => [
            'action_id' => 'beos.detail', 'component' => 'action.result', 'description' => 'Show one BEO event order from the active workspace.',
            'entity_type' => 'beo', 'module' => 'beos', 'mode' => 'read', 'operation_type' => 'read', 'permission' => 'events.view', 'requires_confirmation' => false, 'schema_version' => 1,
        ],
        'beos.versions' => [
            'action_id' => 'beos.versions', 'component' => 'action.result', 'description' => 'Show the versions of one BEO event order.',
            'entity_type' => 'beo', 'module' => 'beos', 'mode' => 'read', 'operation_type' => 'read', 'permission' => 'events.view', 'requires_confirmation' => false, 'schema_version' => 1,
        ],
        'notifications.list' => [
            'action_id' => 'notifications.list', 'component' => 'action.result', 'description' => 'List notifications belonging to the current user in the active workspace.',
            'entity_type' => 'notification', 'module' => 'notifications', 'mode' => 'read', 'operation_type' => 'read', 'permission' => 'notifications.view', 'requires_confirmation' => false, 'schema_version' => 1,
        ],
        'notifications.unread_count' => [
            'action_id' => 'notifications.unread_count', 'component' => 'action.result', 'description' => 'Show the unread notification count for the current user.',
            'entity_type' => 'notification', 'module' => 'notifications', 'mode' => 'read', 'operation_type' => 'read', 'permission' => 'notifications.view', 'requires_confirmation' => false, 'schema_version' => 1,
        ],
        'notifications.read_all' => [
            'action_id' => 'notifications.read_all', 'component' => 'action.result', 'description' => 'Mark the current user notifications as read.',
            'entity_type' => 'notification', 'module' => 'notifications', 'mode' => 'action', 'operation_type' => 'update', 'permission' => 'notifications.edit', 'requires_confirmation' => false, 'schema_version' => 1,
        ],
        'notification_preferences.list' => [
            'action_id' => 'notification_preferences.list', 'component' => 'action.result', 'description' => 'Show supported notification preferences for the current user.',
            'entity_type' => 'notification_preference', 'module' => 'notifications', 'mode' => 'read', 'operation_type' => 'read', 'permission' => 'notifications.view', 'requires_confirmation' => false, 'schema_version' => 1,
        ],
        'notification_preferences.update' => [
            'action_id' => 'notification_preferences.update', 'component' => 'action.preview', 'description' => 'Prepare a supported in-app notification preference change.',
            'entity_type' => 'notification_preference', 'module' => 'notifications', 'mode' => 'write', 'operation_type' => 'update', 'permission' => 'notifications.edit', 'requires_confirmation' => true, 'result_component' => 'action.result', 'schema_version' => 1,
        ],
        'workspace.detail' => [
            'action_id' => 'workspace.detail', 'component' => 'action.result', 'description' => 'Show the current workspace settings.',
            'entity_type' => 'workspace', 'module' => 'workspace', 'mode' => 'read', 'operation_type' => 'read', 'permission' => 'members.view', 'requires_confirmation' => false, 'schema_version' => 1,
        ],
        'workspace.update' => [
            'action_id' => 'workspace.update', 'component' => 'action.preview', 'description' => 'Prepare a safe workspace settings update.',
            'entity_type' => 'workspace', 'module' => 'workspace', 'mode' => 'write', 'operation_type' => 'update', 'permission' => 'members.manage', 'requires_confirmation' => true, 'result_component' => 'action.result', 'schema_version' => 1,
        ],
        'members.list' => [
            'action_id' => 'members.list', 'component' => 'action.result', 'description' => 'List members of the active workspace. Use this read tool to resolve a person before tasks.assign; only active members can receive task assignments.',
            'entity_type' => 'membership', 'module' => 'workspace', 'mode' => 'read', 'operation_type' => 'read', 'permission' => 'members.view', 'requires_confirmation' => false, 'schema_version' => 1,
            'include_in_supporting_results' => false,
        ],
        'members.detail' => [
            'action_id' => 'members.detail', 'component' => 'action.result', 'description' => 'Show one member of the active workspace.',
            'entity_type' => 'membership', 'module' => 'workspace', 'mode' => 'read', 'operation_type' => 'read', 'permission' => 'members.view', 'requires_confirmation' => false, 'schema_version' => 1,
            'include_in_supporting_results' => false,
        ],
        'members.invite' => [
            'action_id' => 'members.invite', 'component' => 'action.preview', 'description' => 'Prepare an invitation for a new workspace member.',
            'entity_type' => 'membership', 'module' => 'workspace', 'mode' => 'write', 'operation_type' => 'create', 'permission' => 'members.invite', 'requires_confirmation' => true, 'result_component' => 'action.result', 'schema_version' => 1,
        ],
        'members.update' => [
            'action_id' => 'members.update', 'component' => 'action.preview', 'description' => 'Prepare a workspace member role or status change.',
            'entity_type' => 'membership', 'module' => 'workspace', 'mode' => 'write', 'operation_type' => 'update', 'permission' => 'members.manage', 'requires_confirmation' => true, 'result_component' => 'action.result', 'schema_version' => 1,
        ],
        'members.remove' => [
            'action_id' => 'members.remove', 'component' => 'action.preview', 'description' => 'Prepare removal of a workspace member.',
            'entity_type' => 'membership', 'module' => 'workspace', 'mode' => 'write', 'operation_type' => 'delete', 'permission' => 'members.manage', 'requires_confirmation' => true, 'result_component' => 'action.result', 'schema_version' => 1,
        ],
        'menus.create' => [
            'action_id' => 'menus.create',
            'component' => 'action.preview',
            'description' => 'Prepare a menu draft from chat content and create it after explicit confirmation.',
            'entity_type' => 'menu',
            'module' => 'menus',
            'mode' => 'write',
            'operation_type' => 'create',
            'permission' => 'menus.create',
            'requires_confirmation' => true,
            'result_component' => 'action.result',
            'schema_version' => 1,
        ],
        'menus.search' => [
            'action_id' => 'menus.search',
            'component' => 'menus.list',
            'description' => 'Search menus in the active workspace by name or description.',
            'entity_type' => 'menu',
            'module' => 'menus',
            'mode' => 'read',
            'operation_type' => 'read',
            'permission' => 'menus.view',
            'requires_confirmation' => false,
            'schema_version' => 1,
        ],
        'menus.show' => [
            'action_id' => 'menus.show',
            'component' => 'menus.detail',
            'description' => 'Show one menu and its current sections and items. Search by name when an ID is not supplied; ask the user to choose among multiple authorized matches. Never create or modify a menu.',
            'entity_type' => 'menu',
            'module' => 'menus',
            'mode' => 'read',
            'operation_type' => 'read',
            'permission' => 'menus.view',
            'requires_confirmation' => false,
            'schema_version' => 1,
        ],
        'menus.rename' => [
            'action_id' => 'menus.rename',
            'component' => 'action.preview',
            'description' => 'Rename the resolved menu in the active workspace.',
            'entity_type' => 'menu',
            'module' => 'menus',
            'mode' => 'write',
            'operation_type' => 'update',
            'permission' => 'menus.edit',
            'requires_confirmation' => true,
            'schema_version' => 1,
        ],
        'menus.items.add' => [
            'action_id' => 'menus.items.add',
            'component' => 'action.preview',
            'description' => 'Add one named item to a resolved menu section.',
            'entity_type' => 'menu_item',
            'module' => 'menus',
            'mode' => 'write',
            'operation_type' => 'create',
            'permission' => 'menus.edit',
            'requires_confirmation' => true,
            'schema_version' => 1,
        ],
        'menus.items.move_section' => [
            'action_id' => 'menus.items.move_section',
            'component' => 'action.preview',
            'description' => 'Move one existing menu item to another section without creating a new menu.',
            'entity_type' => 'menu_item',
            'module' => 'menus',
            'mode' => 'write',
            'operation_type' => 'update',
            'permission' => 'menus.edit',
            'requires_confirmation' => true,
            'schema_version' => 1,
        ],
        'menus.items.reorder' => [
            'action_id' => 'menus.items.reorder',
            'component' => 'action.preview',
            'description' => 'Move one existing item before another existing item in the same resolved menu section. Use the stable item IDs returned by menus.show. This changes only order and preserves every other menu, section, item, recipe, quantity, and note value.',
            'entity_type' => 'menu_item',
            'module' => 'menus',
            'mode' => 'write',
            'operation_type' => 'update',
            'permission' => 'menus.edit',
            'requires_confirmation' => true,
            'result_component' => 'action.result',
            'schema_version' => 1,
        ],
        'menus.update' => [
            'action_id' => 'menus.update', 'component' => 'action.preview',
            'description' => 'Prepare one atomic replacement of a resolved menu current version for confirmation. Use menus.show first, then submit the complete desired sections/items order with stable IDs preserved for existing records. This supports adding, renaming, removing and reordering sections or items, moving items between sections, and linking a resolved recipe. Include every requested menu change in this one write.',
            'entity_type' => 'menu', 'module' => 'menus', 'mode' => 'write', 'operation_type' => 'update',
            'permission' => 'menus.edit', 'requires_confirmation' => true, 'result_component' => 'action.result', 'schema_version' => 1,
        ],
        'menus.duplicate' => [
            'action_id' => 'menus.duplicate', 'component' => 'action.preview',
            'description' => 'Prepare an independent duplicate of a resolved menu with a new name. Use menus.show first; when additional changes are requested, submit the complete desired copied sections/items state in the same write. Preserve recipe links only when their IDs are supplied from the source menu.',
            'entity_type' => 'menu', 'module' => 'menus', 'mode' => 'write', 'operation_type' => 'create',
            'permission' => 'menus.create', 'requires_confirmation' => true, 'result_component' => 'action.result', 'schema_version' => 1,
        ],
        'menus.delete' => [
            'action_id' => 'menus.delete', 'component' => 'action.preview',
            'description' => 'Inspect active event assignments, then prepare deletion of one resolved menu for explicit confirmation. Never delete until the dependency impact is displayed and confirmed.',
            'entity_type' => 'menu', 'module' => 'menus', 'mode' => 'write', 'operation_type' => 'delete',
            'permission' => 'menus.edit', 'requires_confirmation' => true, 'result_component' => 'action.result', 'schema_version' => 1,
        ],
        'menus.items.update' => [
            'action_id' => 'menus.items.update', 'component' => 'action.preview',
            'description' => 'Prepare an update to a menu item and its recipe or serving data.',
            'entity_type' => 'menu_item', 'module' => 'menus', 'mode' => 'write', 'operation_type' => 'update',
            'permission' => 'menus.edit', 'requires_confirmation' => true, 'result_component' => 'action.result', 'schema_version' => 1,
        ],
        'menus.items.batch_update' => [
            'action_id' => 'menus.items.batch_update', 'component' => 'action.preview',
            'description' => 'Prepare one atomic update of several existing items in one resolved menu. Use this for plural recipe links or several independent item field changes. Every item and recipe must use stable IDs returned by earlier tools; never infer a menu when more than one is in context.',
            'entity_type' => 'menu_item', 'module' => 'menus', 'mode' => 'write', 'operation_type' => 'update',
            'permission' => 'menus.edit', 'requires_confirmation' => true, 'result_component' => 'action.result', 'schema_version' => 1,
        ],
        'menus.items.delete' => [
            'action_id' => 'menus.items.delete', 'component' => 'action.preview',
            'description' => 'Prepare deletion of a menu item from a new menu version.',
            'entity_type' => 'menu_item', 'module' => 'menus', 'mode' => 'write', 'operation_type' => 'delete',
            'permission' => 'menus.edit', 'requires_confirmation' => true, 'result_component' => 'action.result', 'schema_version' => 1,
        ],
        'recipes.list' => [
            'action_id' => 'recipes.list', 'component' => 'recipes.list', 'description' => 'List recipes in the active workspace.',
            'entity_type' => 'recipe', 'module' => 'recipes', 'mode' => 'read', 'operation_type' => 'read',
            'permission' => 'recipes.view', 'requires_confirmation' => false, 'schema_version' => 1,
        ],
        'recipes.detail' => [
            'action_id' => 'recipes.detail', 'component' => 'recipes.detail', 'description' => 'Show one recipe and its current version.',
            'entity_type' => 'recipe', 'module' => 'recipes', 'mode' => 'read', 'operation_type' => 'read',
            'permission' => 'recipes.view', 'requires_confirmation' => false, 'schema_version' => 1,
        ],
        'recipes.versions' => [
            'action_id' => 'recipes.versions', 'component' => 'recipes.detail', 'description' => 'Show the versions of one recipe.',
            'entity_type' => 'recipe', 'module' => 'recipes', 'mode' => 'read', 'operation_type' => 'read',
            'permission' => 'recipes.view', 'requires_confirmation' => false, 'schema_version' => 1,
        ],
        'recipes.scale' => [
            'action_id' => 'recipes.scale', 'component' => 'recipes.scaled', 'description' => 'Calculate recipe quantities using the existing recipe scaling action.',
            'entity_type' => 'recipe', 'module' => 'recipes', 'mode' => 'read', 'operation_type' => 'read',
            'permission' => 'recipes.view', 'requires_confirmation' => false, 'schema_version' => 1,
        ],
        'recipes.create' => [
            'action_id' => 'recipes.create', 'component' => 'action.preview', 'description' => 'Prepare a complete recipe draft for explicit confirmation. When quantities are stated, send their canonical unit_key (for example lb, cup, tbsp, portion). A complete recipe needs only name, yield, ingredients, and steps; do not request category or a cooking duration when the instruction already states its cooking target.',
            'entity_type' => 'recipe', 'module' => 'recipes', 'mode' => 'write', 'operation_type' => 'create',
            'permission' => 'recipes.create', 'requires_confirmation' => true, 'result_component' => 'action.result', 'schema_version' => 1,
        ],
        'recipes.create_many' => [
            'action_id' => 'recipes.create_many', 'component' => 'action.preview', 'description' => 'Prepare one durable execution plan for two or more independent complete recipe drafts. The plan shows every recipe before one explicit confirmation, then the backend executes bounded sequential queue blocks without repeating completed items. Use this instead of several recipes.create calls when the user requests multiple recipes at once. Do not use it for recipes that depend on a recipe, menu, or other record that has not been created yet.',
            'entity_type' => 'recipe', 'module' => 'recipes', 'mode' => 'write', 'operation_type' => 'create_many',
            'permission' => 'recipes.create', 'requires_confirmation' => true, 'result_component' => 'action.result', 'schema_version' => 1,
        ],
        'recipes.update' => [
            'action_id' => 'recipes.update', 'component' => 'action.preview', 'description' => 'Prepare a new recipe version from a complete structured recipe draft for explicit confirmation. Natural-language patches are not accepted.',
            'entity_type' => 'recipe', 'module' => 'recipes', 'mode' => 'write', 'operation_type' => 'update',
            'permission' => 'recipes.edit', 'requires_confirmation' => true, 'result_component' => 'action.result', 'schema_version' => 1,
        ],
        'recipes.edit' => [
            'action_id' => 'recipes.edit', 'component' => 'action.preview', 'description' => 'Prepare structured ingredient, step, yield, or compatible unit changes on the current recipe version for explicit confirmation.',
            'entity_type' => 'recipe', 'module' => 'recipes', 'mode' => 'write', 'operation_type' => 'update',
            'permission' => 'recipes.edit', 'requires_confirmation' => true, 'result_component' => 'action.result', 'schema_version' => 1,
        ],
        'recipes.duplicate' => [
            'action_id' => 'recipes.duplicate', 'component' => 'action.preview', 'description' => 'Prepare an independent copy of a recipe with a new name for explicit confirmation.',
            'entity_type' => 'recipe', 'module' => 'recipes', 'mode' => 'write', 'operation_type' => 'create',
            'permission' => 'recipes.create', 'requires_confirmation' => true, 'result_component' => 'action.result', 'schema_version' => 1,
        ],
        'recipes.delete' => [
            'action_id' => 'recipes.delete', 'component' => 'action.preview', 'description' => 'Inspect menu and prep dependencies, then prepare recipe deletion for explicit confirmation.',
            'entity_type' => 'recipe', 'module' => 'recipes', 'mode' => 'write', 'operation_type' => 'delete',
            'permission' => 'recipes.edit', 'requires_confirmation' => true, 'result_component' => 'action.result', 'schema_version' => 1,
        ],
    ];

    public function resolve(string $actionId): array
    {
        $normalized = self::ACTION_ALIASES[$actionId] ?? $actionId;
        $teamStaffTool = $this->teamStaffTool($normalized);

        if ($teamStaffTool === null && !array_key_exists($normalized, self::TOOLS)) {
            throw ValidationException::withMessages([
                'action_id' => ['The selected action is not registered.'],
            ]);
        }

        $tool = $teamStaffTool ?? self::TOOLS[$normalized];
        $policy = $this->actionPolicy->resolve($normalized);

        return [
            'key' => $normalized,
            'policy' => $policy,
            ...$tool,
            'reference_fields' => $this->referenceFieldsFor($normalized),
            'target_entity_required' => !in_array(($tool['operation_type'] ?? null), ['create', 'create_many'], true),
            'target_reference_fields' => $this->targetReferenceFieldsFor($normalized),
            'requires_confirmation' => (bool) ($tool['requires_confirmation'] || $policy['confirmation_required']),
        ];
    }

    /** @return array<int, string> */
    private function referenceFieldsFor(string $actionKey): array
    {
        return match ($actionKey) {
            'recipes.create', 'recipes.create_many' => [],
            'recipes.update', 'recipes.edit', 'recipes.duplicate', 'recipes.delete' => ['recipe_id', 'recipe_search'],
            'menus.create' => ['menu_draft.sections.*.items.*.recipe_reference'],
            'menus.update', 'menus.duplicate', 'menus.delete', 'menus.items.update', 'menus.items.batch_update', 'menus.items.delete', 'menus.items.move_section', 'menus.items.reorder' => ['menu_id', 'menu_search', 'menu_item_id', 'menu_item_search', 'item_id', 'item_search'],
            'tasks.create', 'tasks.create_many' => ['membership_id', 'member_search', 'team_id', 'team_search', 'station_id', 'station_search', 'event_id', 'event_search'],
            'tasks.update', 'tasks.delete', 'tasks.assign', 'tasks.status.update', 'tasks.complete' => ['task_id', 'task_search', 'task_ids', 'search', 'due_from', 'due_to', 'membership_id', 'member_search', 'team_id', 'team_search', 'station_id', 'station_search', 'event_id', 'event_search'],
            default => [],
        };
    }

    /** @return array<int, string> */
    private function targetReferenceFieldsFor(string $actionKey): array
    {
        return match ($actionKey) {
            'recipes.update', 'recipes.edit', 'recipes.duplicate', 'recipes.delete' => ['recipe_id', 'recipe_search'],
            'menus.update', 'menus.duplicate', 'menus.delete' => ['menu_id', 'menu_search'],
            'menus.items.update', 'menus.items.batch_update', 'menus.items.delete', 'menus.items.move_section', 'menus.items.reorder' => ['menu_id', 'menu_search', 'menu_item_id', 'menu_item_search', 'item_id', 'item_search'],
            'tasks.update', 'tasks.delete', 'tasks.assign', 'tasks.status.update', 'tasks.complete' => ['task_id', 'task_search'],
            'tasks.read' => ['task_id', 'task_search'],
            default => [],
        };
    }

    private static function teamStaffReadTool(string $key, string $component, string $description): array
    {
        return [
            'action_id' => $key, 'component' => $component, 'description' => $description,
            'entity_type' => str_contains($key, 'availability') ? 'availability' : (str_contains($key, 'stations') ? 'station' : (str_contains($key, 'shifts') ? 'shift' : 'team')),
            'module' => 'team_staff', 'mode' => 'read', 'operation_type' => 'read',
            'permission' => str_contains($key, 'availability') ? 'members.view' : 'teams.view',
            'requires_confirmation' => false, 'schema_version' => 1,
        ];
    }

    private static function teamStaffWriteTool(string $key, string $entityType, string $operation, string $permission): array
    {
        return [
            'action_id' => $key, 'component' => 'action.preview', 'description' => 'Prepare a team staff action for explicit confirmation.',
            'entity_type' => $entityType, 'module' => 'team_staff', 'mode' => 'write', 'operation_type' => $operation,
            'permission' => $permission, 'requires_confirmation' => true, 'result_component' => 'action.result', 'schema_version' => 1,
        ];
    }

    public function actionKeyForIntent(string $intent): ?string
    {
        $normalized = self::ACTION_ALIASES[$intent] ?? $intent;

        return $this->teamStaffTool($normalized) !== null || array_key_exists($normalized, self::TOOLS) ? $normalized : null;
    }

    public function metadata(array $tool): array
    {
        $inputSchema = $tool['input_schema'] ?? ($this->directoryInputSchema($tool) ?: $this->chatInputSchema($tool));
        if ($inputSchema === []) {
            $inputSchema = ['additional_properties' => false, 'fields' => []];
        }

        return [
            'action_id' => $tool['key'],
            'action_key' => $tool['key'],
            'component' => $tool['component'],
            'confirmation_policy' => $tool['requires_confirmation']
                ? 'explicit_confirmation'
                : 'none',
            'context_requirements' => in_array($tool['entity_type'], ['menu', 'menu_item'], true)
                ? ['active_menu_or_menu_search']
                : [],
            'description' => $tool['description'],
            'enabled' => true,
            'executor' => ToolExecutor::class,
            'entity_type' => $tool['entity_type'],
            'key' => $tool['key'],
            'input_schema' => $inputSchema,
            'module' => $tool['module'] ?? null,
            'mode' => $tool['mode'],
            'operation_type' => $tool['operation_type'] ?? $tool['mode'],
            'policy' => $tool['policy'] ?? $this->actionPolicy->resolve($tool['key']),
            'output_schema' => $tool['output_schema'] ?? ['component' => $tool['component'].'@'.$tool['schema_version']],
            'payload_extractor' => $this->payloadExtractorFor($tool['key']),
            'permission' => $tool['permission'],
            'requires_confirmation' => $tool['requires_confirmation'],
            'schema_version' => $tool['schema_version'],
            'legacy_action_aliases' => $this->legacyAliasesFor($tool['key']),
            'include_in_supporting_results' => $tool['include_in_supporting_results'] ?? true,
        ];
    }

    public function allMetadata(): array
    {
        $tools = self::TOOLS;
        foreach (['teams.list','teams.detail','teams.create','teams.update','teams.delete','teams.members.sync','stations.list','stations.detail','stations.create','stations.update','stations.delete','shifts.list','shifts.detail','shifts.create','shifts.update','shifts.delete','availability.list','availability.sync'] as $key) {
            $tools[$key] = $this->teamStaffTool($key);
        }
        return collect($tools)
            ->map(fn (array $tool, string $key) => $this->metadata([
                'key' => $key,
                ...$tool,
            ]))
            ->values()
            ->all();
    }

    /**
     * Render the runtime capability index used by the model prompt.
     *
     * Function schemas remain the source of truth for exact arguments. This
     * index only gives the model a compact map of the capabilities selected
     * for the current turn, so module-specific routing rules do not need to be
     * duplicated in the system prompt.
     *
     * @param array<int, array<string, mixed>> $metadata
     */
    public function modelContract(array $metadata = []): string
    {
        $metadata = $metadata !== [] ? $metadata : $this->allMetadata();

        $lines = collect($metadata)
            ->filter(static fn (mixed $tool): bool => is_array($tool) && filled($tool['key'] ?? null))
            ->map(function (array $tool): string {
                $schema = is_array($tool['input_schema'] ?? null) ? $tool['input_schema'] : [];
                $fields = is_array($schema['fields'] ?? null) ? $schema['fields'] : [];
                if ($fields === [] && is_array($schema['properties'] ?? null)) {
                    $fields = array_keys($schema['properties']);
                }

                $fields = collect($fields)
                    ->filter(static fn (mixed $field): bool => is_string($field) && trim($field) !== '')
                    ->map(static fn (string $field): string => trim($field))
                    ->implode(',');
                $component = data_get($tool, 'output_schema.component')
                    ?? ($tool['component'] ?? '');
                $confirmation = ($tool['requires_confirmation'] ?? false) ? 'yes' : 'no';
                $description = preg_replace('/\s+/', ' ', trim((string) ($tool['description'] ?? '')));

                return sprintf(
                    '%s [module=%s; entity=%s; operation=%s; mode=%s; confirm=%s; component=%s; fields=%s] %s',
                    $tool['key'],
                    $tool['module'] ?? 'general',
                    $tool['entity_type'] ?? 'none',
                    $tool['operation_type'] ?? $tool['mode'] ?? 'read',
                    $tool['mode'] ?? 'read',
                    $confirmation,
                    $component,
                    $fields === '' ? '-' : $fields,
                    $description,
                );
            })
            ->values()
            ->all();

        return implode("\n", [
            'RUNTIME CAPABILITY CONTRACT: use only the capabilities listed below. The function input schema is authoritative for arguments; this index is routing metadata.',
            ...$lines,
        ]);
    }

    public function canonicalKeys(): array
    {
        return collect($this->allMetadata())->pluck('key')->values()->all();
    }

    private function directoryInputSchema(array $tool): array
    {
        if (!in_array($tool['key'] ?? null, [
            'events.list', 'events.detail', 'events.create', 'events.update', 'events.cancel', 'events.delete',
            'clients.list', 'clients.detail', 'clients.create', 'clients.update', 'clients.delete',
            'contacts.list', 'contacts.detail', 'contacts.create', 'contacts.update', 'contacts.delete',
            'venues.list', 'venues.detail', 'venues.create', 'venues.update', 'venues.delete',
        ], true)) {
            return [];
        }

        $entity = (string) ($tool['entity_type'] ?? '');
        $operation = (string) ($tool['operation_type'] ?? 'read');
        $fields = match ($entity) {
            'event' => ['name', 'starts_at', 'ends_at', 'timezone', 'status', 'guest_count_expected', 'guest_count_confirmed', 'service_type', 'event_type', 'client_id', 'contact_id', 'venue_id', 'client_search', 'contact_search', 'venue_search', 'notes'],
            'client' => ['name', 'company_name', 'email', 'phone', 'website', 'tax_id', 'address_line_1', 'address_line_2', 'city', 'state', 'postal_code', 'country_code', 'status', 'notes'],
            'contact' => ['client_id', 'client_search', 'first_name', 'last_name', 'display_name', 'email', 'phone', 'job_title', 'contact_type', 'is_primary', 'notes'],
            'venue' => ['name', 'address_line_1', 'address_line_2', 'city', 'state', 'postal_code', 'country_code', 'latitude', 'longitude', 'timezone', 'contact_name', 'contact_email', 'contact_phone', 'capacity', 'access_instructions', 'parking_notes', 'loading_notes', 'kitchen_notes', 'notes', 'status'],
            default => [],
        };

        // Detail reads may start from a natural-language reference. The
        // executor resolves that reference to an authorized stable ID and
        // asks the user when more than one record matches.
        $isDetailRead = $operation === 'read' && str_ends_with((string) ($tool['key'] ?? ''), '.detail');
        $fields = array_values(array_filter($fields, static fn (string $field): bool =>
            !str_ends_with($field, '_search')
        ));
        if ($isDetailRead) {
            $fields = array_values(array_unique([...$fields, 'entity_id', 'entity_search']));
        } elseif ($operation !== 'create') {
            $targetId = match ($entity) {
                'event' => 'event_id', 'client' => 'client_id', 'contact' => 'contact_id', 'venue' => 'venue_id',
                default => null,
            };
            if ($targetId !== null && !in_array($targetId, $fields, true)) {
                array_unshift($fields, $targetId);
            }
        }

        return [
            'additional_properties' => false,
            'fields' => $fields,
            'required' => $operation === 'create'
                ? match ($entity) {
                    'event' => ['name', 'starts_at'],
                    'contact' => ['first_name'],
                    default => ['name'],
                }
                : [],
        ];
    }

    private function chatInputSchema(array $tool): array
    {
        return match ($tool['key'] ?? null) {
            'menus.search' => ['additional_properties' => false, 'fields' => ['search', 'menu_id']],
            'menus.show' => ['additional_properties' => false, 'fields' => ['menu_id', 'menu_search']],
            'menus.create' => ['additional_properties' => false, 'required' => ['menu_draft.name', 'menu_draft.sections'], 'fields' => ['menu_draft', 'menu_draft.name', 'menu_draft.description', 'menu_draft.type', 'menu_draft.default_guest_count', 'menu_draft.event_reference', 'menu_draft.sections', 'menu_draft.sections.*.name', 'menu_draft.sections.*.items', 'menu_draft.sections.*.items.*.name', 'menu_draft.sections.*.items.*.recipe_reference', 'menu_draft.sections.*.items.*.quantity_per_guest', 'menu_draft.sections.*.items.*.serving_unit', 'menu_draft.sections.*.items.*.notes', 'name', 'sections', 'requested_guest_count']],
            'menus.update' => ['additional_properties' => false, 'fields' => ['menu_id', 'menu_search', 'name', 'description', 'type', 'status', 'default_guest_count', 'sections', 'event_id']],
            'menus.duplicate' => ['additional_properties' => false, 'required' => ['name'], 'fields' => ['menu_id', 'menu_search', 'name', 'sections', 'description', 'type', 'default_guest_count']],
            'menus.delete' => ['additional_properties' => false, 'fields' => ['menu_id', 'menu_search']],
            'menus.rename' => ['additional_properties' => false, 'fields' => ['menu_id', 'name']],
            'menus.items.add' => ['additional_properties' => false, 'fields' => ['menu_id', 'section_id', 'item_name']],
            'menus.items.move_section' => ['additional_properties' => false, 'fields' => ['menu_id', 'item_id', 'target_section_id']],
            'menus.items.reorder' => ['additional_properties' => false, 'fields' => ['menu_id', 'menu_search', 'item_id', 'before_item_id']],
            'menus.items.update' => ['additional_properties' => false, 'fields' => ['menu_id', 'item_id', 'name', 'description', 'notes', 'quantity_per_guest', 'serving_unit', 'recipe_id', 'recipe_version_id', 'active', 'optional']],
            'menus.items.batch_update' => ['additional_properties' => false, 'required' => ['updates'], 'fields' => ['menu_id', 'menu_search', 'updates', 'updates.*.item_id', 'updates.*.name', 'updates.*.description', 'updates.*.notes', 'updates.*.quantity_per_guest', 'updates.*.serving_unit', 'updates.*.recipe_id', 'updates.*.recipe_version_id', 'updates.*.active', 'updates.*.optional']],
            'menus.items.delete' => ['additional_properties' => false, 'fields' => ['menu_id', 'item_id']],
            'recipes.list' => ['additional_properties' => false, 'fields' => ['search', 'recipe_search']],
            'recipes.detail', 'recipes.versions' => ['additional_properties' => false, 'fields' => ['recipe_id', 'recipe_version_id']],
            'recipes.scale' => ['additional_properties' => false, 'fields' => ['recipe_id', 'recipe_search', 'recipe_version_id', 'target_quantity', 'target_unit_id']],
            'recipes.create' => ['additional_properties' => false, 'required' => ['recipe_draft'], 'fields' => ['recipe_draft', 'recipe_draft.name', 'recipe_draft.description', 'recipe_draft.yield', 'recipe_draft.yield.quantity', 'recipe_draft.yield.quantity_min', 'recipe_draft.yield.quantity_max', 'recipe_draft.yield.unit_key', 'recipe_draft.ingredients', 'recipe_draft.ingredients.*.ingredient_name', 'recipe_draft.ingredients.*.quantity', 'recipe_draft.ingredients.*.quantity_min', 'recipe_draft.ingredients.*.quantity_max', 'recipe_draft.ingredients.*.unit_key', 'recipe_draft.ingredients.*.preparation', 'recipe_draft.ingredients.*.optional', 'recipe_draft.steps', 'recipe_draft.steps.*.instruction']],
            'recipes.create_many' => ['additional_properties' => false, 'required' => ['recipes'], 'fields' => ['title', 'block_size', 'recipes']],
            'recipes.update' => ['additional_properties' => false, 'required' => ['recipe_id', 'recipe_draft', 'current_version_id', 'expected_revision'], 'fields' => ['recipe_id', 'recipe_draft', 'recipe_draft.name', 'recipe_draft.description', 'recipe_draft.category', 'recipe_draft.type', 'recipe_draft.status', 'recipe_draft.recipe_code', 'recipe_draft.tags', 'recipe_draft.version', 'recipe_draft.version.name', 'recipe_draft.version.description', 'recipe_draft.version.category', 'recipe_draft.version.status', 'recipe_draft.version.ingredients', 'recipe_draft.version.ingredients.*.ingredient_name', 'recipe_draft.version.ingredients.*.quantity', 'recipe_draft.version.ingredients.*.unit_id', 'recipe_draft.version.ingredients.*.notes', 'recipe_draft.version.ingredients.*.optional', 'recipe_draft.version.ingredients.*.preparation', 'recipe_draft.version.ingredients.*.component_recipe_id', 'recipe_draft.version.ingredients.*.component_recipe_version_id', 'recipe_draft.version.steps', 'recipe_draft.version.steps.*.instruction', 'recipe_draft.version.steps.*.title', 'recipe_draft.version.steps.*.duration_minutes', 'recipe_draft.version.steps.*.notes', 'recipe_draft.version.yields', 'recipe_draft.version.yields.*.quantity', 'recipe_draft.version.yields.*.unit_id', 'recipe_draft.version.yields.*.label', 'recipe_draft.version.yields.*.is_default', 'current_version_id', 'expected_revision']],
            'recipes.edit' => ['additional_properties' => false, 'required' => ['mutation'], 'fields' => ['recipe_id', 'recipe_search', 'mutation', 'mutation.ingredient_changes', 'mutation.step_changes', 'mutation.yield', 'mutation.convert_units']],
            'recipes.duplicate' => ['additional_properties' => false, 'required' => ['name'], 'fields' => ['recipe_id', 'recipe_search', 'name']],
            'recipes.delete' => ['additional_properties' => false, 'fields' => ['recipe_id', 'recipe_search']],
            'tasks.create' => ['additional_properties' => false, 'required' => ['title'], 'fields' => ['title', 'description', 'blocked_reason', 'type', 'starts_at', 'due_at', 'duration_minutes', 'priority', 'status', 'timezone', 'membership_id', 'member_search', 'team_id', 'team_search', 'station_id', 'station_search', 'event_id', 'event_search']],
            'tasks.create_many' => ['additional_properties' => false, 'required' => ['tasks'], 'fields' => ['tasks']],
            'tasks.update' => ['additional_properties' => false, 'fields' => ['task_id', 'task_search', 'task_ids', 'search', 'due_from', 'due_to', 'title', 'description', 'type', 'starts_at', 'time_hour', 'time_minute', 'time_period', 'due_at', 'priority', 'status', 'timezone', 'blocked_reason', 'event_id', 'event_search', 'membership_id', 'member_search', 'team_id', 'team_search', 'station_id', 'station_search', 'expected_revision']],
            'tasks.search', 'tasks.list' => ['additional_properties' => false, 'fields' => ['search', 'status', 'priority', 'due_from', 'due_to', 'overdue', 'unassigned', 'membership_id', 'member_search', 'exclude_membership_id', 'exclude_member_search', 'team_id', 'team_search', 'station_id', 'station_search', 'event_id', 'event_search', 'limit']],
            'tasks.read', 'tasks.detail' => ['additional_properties' => false, 'fields' => ['task_id', 'task_search']],
            'tasks.delete' => ['additional_properties' => false, 'fields' => ['task_id', 'task_search', 'task_ids', 'search', 'status', 'priority', 'due_from', 'due_to', 'membership_id', 'member_search', 'team_id', 'team_search', 'station_id', 'station_search', 'event_id', 'event_search']],
            'tasks.assign' => ['additional_properties' => false, 'fields' => ['task_id', 'task_search', 'membership_id', 'member_search', 'from_membership_id', 'from_member_search', 'task_ids', 'due_from', 'due_to', 'status', 'search']],
            'tasks.status.update' => ['additional_properties' => false, 'fields' => ['task_id', 'task_search', 'task_ids', 'search', 'due_from', 'due_to', 'status']],
            'tasks.complete' => ['additional_properties' => false, 'fields' => ['task_id', 'task_search', 'task_ids', 'search', 'due_from', 'due_to']],
            'documents.list' => ['additional_properties' => false, 'fields' => ['search', 'processing_status', 'limit']],
            'documents.detail', 'documents.retry_extraction' => ['additional_properties' => false, 'fields' => ['document_id', 'document_search']],
            'documents.link_event' => ['additional_properties' => false, 'fields' => ['document_id', 'event_id']],
            'beos.list' => ['additional_properties' => false, 'fields' => ['search', 'limit']],
            'beos.detail', 'beos.versions' => ['additional_properties' => false, 'fields' => ['beo_id', 'beo_search']],
            'notifications.list' => ['additional_properties' => false, 'fields' => ['unread_only', 'limit']],
            'notification_preferences.update' => ['additional_properties' => false, 'fields' => ['event_key', 'enabled', 'in_app', 'minimum_priority']],
            'workspace.update' => ['additional_properties' => false, 'fields' => ['name', 'default_locale', 'timezone', 'currency']],
            'members.list' => ['additional_properties' => false, 'fields' => ['search', 'limit']],
            'members.detail', 'members.update', 'members.remove' => ['additional_properties' => false, 'fields' => ['membership_id', 'member_search', 'role_id', 'status']],
            'members.invite' => ['additional_properties' => false, 'fields' => ['email', 'role_id']],
            'prep.list' => ['additional_properties' => false, 'fields' => ['event_id', 'event_search', 'status', 'active_only', 'limit']],
            'prep.detail' => ['additional_properties' => false, 'fields' => ['prep_list_id', 'prep_list_search', 'event_id', 'event_search']],
            'prep.items.list' => ['additional_properties' => false, 'fields' => ['prep_list_id', 'status', 'limit']],
            'prep.items.detail' => ['additional_properties' => false, 'fields' => ['prep_item_id', 'prep_item_search', 'prep_list_id']],
            'prep.generate', 'prep.regenerate' => ['additional_properties' => false, 'fields' => ['event_id', 'prep_list_id', 'guest_count', 'menu_version_id', 'due_at', 'timezone', 'include_assignments', 'preserve_completed_items', 'preserve_assignments', 'notes', 'change_summary', 'name']],
            'prep.update' => ['additional_properties' => false, 'fields' => ['prep_list_id', 'event_id', 'name', 'production_starts_at', 'production_ends_at', 'timezone', 'status', 'metadata']],
            'prep.items.update' => ['additional_properties' => false, 'fields' => ['prep_item_id', 'prep_list_id', 'title', 'description', 'quantity', 'unit_id', 'portions', 'yield_quantity', 'yield_unit_id', 'actual_quantity', 'actual_unit_id', 'starts_at', 'due_at', 'timezone', 'priority', 'status', 'blocked_reason', 'notes', 'assignment_membership_id', 'version']],
            'prep.items.complete', 'prep.items.reopen', 'prep.items.assign', 'prep.items.unassign', 'prep_items.update' => ['additional_properties' => false, 'fields' => ['prep_item_id', 'prep_list_id', 'assignment_membership_id', 'version']],
            'teams.list' => ['additional_properties' => false, 'fields' => ['search', 'limit']],
            'teams.detail' => ['additional_properties' => false, 'fields' => ['team_id', 'team_search']],
            'stations.list' => ['additional_properties' => false, 'fields' => ['team_id', 'search', 'limit']],
            'stations.detail' => ['additional_properties' => false, 'fields' => ['station_id', 'station_search', 'team_id']],
            'shifts.list' => ['additional_properties' => false, 'fields' => ['membership_id', 'team_id', 'station_id', 'from', 'to', 'limit']],
            'shifts.detail' => ['additional_properties' => false, 'fields' => ['shift_id', 'shift_search', 'membership_id', 'team_id', 'station_id']],
            'availability.list' => ['additional_properties' => false, 'fields' => ['membership_id', 'member_search', 'from', 'to', 'limit']],
            'teams.create', 'teams.update' => ['additional_properties' => false, 'fields' => ['team_id', 'team_search', 'name', 'key', 'description', 'type', 'status', 'member_ids', 'lead_membership_id']],
            'stations.create', 'stations.update' => ['additional_properties' => false, 'fields' => ['station_id', 'station_search', 'name', 'key', 'description', 'team_id', 'type', 'capacity', 'position', 'status']],
            'shifts.create', 'shifts.update' => ['additional_properties' => false, 'fields' => ['shift_id', 'shift_search', 'membership_id', 'member_search', 'event_id', 'team_id', 'station_id', 'starts_at', 'ends_at', 'timezone', 'break_minutes', 'role', 'status', 'notes']],
            'availability.sync' => ['additional_properties' => false, 'fields' => ['membership_id', 'member_search', 'records', 'rules']],
            default => [],
        };
    }

    private function teamStaffTool(string $key): ?array
    {
        if (in_array($key, ['teams.list', 'teams.detail'], true)) {
            return self::teamStaffReadTool($key, $key, 'List or show workspace teams.');
        }
        if (in_array($key, ['stations.list', 'stations.detail'], true)) {
            return self::teamStaffReadTool($key, $key, 'List or show workspace stations.');
        }
        if (in_array($key, ['shifts.list', 'shifts.detail'], true)) {
            return self::teamStaffReadTool($key, $key, 'List or show workspace shifts.');
        }
        if ($key === 'availability.list') {
            return self::teamStaffReadTool($key, $key, 'List workspace staff availability.');
        }
        if (preg_match('/^(teams|stations|shifts)\.(create|update|delete)$/', $key, $matches) === 1) {
            return self::teamStaffWriteTool($key, match ($matches[1]) {
                'teams' => 'team', 'stations' => 'station', default => 'shift',
            }, $matches[2], $matches[1].'.'.($matches[2] === 'update' ? 'edit' : $matches[2]));
        }
        if ($key === 'teams.members.sync') {
            return self::teamStaffWriteTool($key, 'team', 'sync_members', 'teams.edit');
        }
        if ($key === 'availability.sync') {
            return self::teamStaffWriteTool($key, 'availability', 'sync', 'members.manage');
        }
        return null;
    }

    private function payloadExtractorFor(string $actionKey): string
    {
        return match ($actionKey) {
            'recipes.create' => 'recipe_draft',
            'recipes.create_many' => 'recipe_drafts',
            'menus.create' => 'menu_draft',
            'tasks.create' => 'task_create',
            'tasks.create_many' => 'task_create_many',
            default => 'structured_input',
        };
    }

    private function legacyAliasesFor(string $actionKey): array
    {
        return collect(self::ACTION_ALIASES)
            ->filter(static fn (string $canonical): bool => $canonical === $actionKey)
            ->keys()
            ->values()
            ->all();
    }
}
