<?php

return [
    'events' => [
        'summary_text' => 'Here is the event referenced in the conversation context.',
    ],
    'prep' => [
        'summary_text' => 'Here is the prep context related to that event.',
    ],
    'pending' => [
        'summary_text' => 'This is the pending operational context for the selected event.',
    ],
    'unsupported_capability' => [
        'text' => 'I understood what you want to do, but Humoo does not support that capability yet. I recorded the request for future planning. No action was executed.',
    ],
    'menu' => [
        'action_completed' => 'The menu :name was updated successfully.',
        'action_description' => 'The menu change was applied to a new menu version.',
        'action_title' => 'Menu updated',
        'items_label' => 'Items',
        'menu_label' => 'Menu',
        'not_found' => 'I could not find the requested menu.',
        'search_summary' => 'I found :count menus for this context.',
        'search_title' => 'Menus',
        'show_summary' => 'Here is the requested menu.',
        'show_title' => 'Menu',
    ],
    'clarification' => [
        'event_text' => 'I found more than one matching event.',
        'event_description' => 'Choose the correct event to continue safely.',
        'event_title' => 'Which event should I use?',
        'scope_text' => 'I need a bit more direction before I continue.',
        'scope_description' => 'Choose one of these guided chat paths.',
        'scope_events' => 'Events',
        'scope_prep' => 'Active prep',
        'scope_tasks' => 'My tasks',
        'scope_title' => 'What should I review first?',
    ],
    'recovery' => [
        'description' => 'I could not complete that request safely.',
        'title' => 'I need a safer next step',
        'tool_limit_detail' => 'The request hit the maximum number of tool calls for one turn.',
        'event_summary_missing' => 'That event summary is no longer available in recent chat context.',
        'event_not_found' => 'I could not find a matching event in the active workspace.',
        'member_not_found' => 'I could not match that assignee to an active workspace member.',
        'task_update_missing_change' => 'I still need the new status or assignee before preparing a task update.',
        'task_not_found' => 'I could not find a matching task for that update request.',
        'task_ambiguous' => 'I found multiple tasks. Mention the task title or ask me to show your tasks first.',
        'provider_authentication' => 'The AI interpreter credentials were rejected. No changes were made.',
        'provider_bad_request' => 'The AI interpreter rejected the request format. No changes were made.',
        'provider_invalid_response' => 'The AI interpreter returned an invalid response. No changes were made.',
        'provider_network_error' => 'The connection to the AI interpreter failed. No changes were made.',
        'provider_rate_limit' => 'The AI interpreter is temporarily rate-limited. No changes were made.',
        'provider_timeout' => 'The AI interpreter took too long to respond. No changes were made.',
        'provider_unavailable' => 'The AI interpreter is not available right now. No changes were made.',
    ],
];
