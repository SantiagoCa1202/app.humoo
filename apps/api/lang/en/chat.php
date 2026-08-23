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
        'provider_unavailable' => 'The AI interpreter is not available right now. No changes were made.',
    ],
];
