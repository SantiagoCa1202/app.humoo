<?php

return [
    'events' => [
        'summary_text' => 'Este es el evento referido por el contexto reciente de la conversacion.',
    ],
    'prep' => [
        'summary_text' => 'Este es el contexto de prep relacionado con ese evento.',
    ],
    'pending' => [
        'summary_text' => 'Este es el contexto operativo pendiente para el evento seleccionado.',
    ],
    'unsupported_capability' => [
        'text' => 'Entendi lo que quieres hacer, pero Humoo todavia no tiene disponible esa capacidad. Registre la solicitud para planificacion futura. No se ejecuto ninguna accion.',
    ],
    'menu' => [
        'action_completed' => 'El menu :name se actualizo correctamente.',
        'action_description' => 'El cambio se aplico en una nueva version del menu.',
        'action_title' => 'Menu actualizado',
        'items_label' => 'Items',
        'menu_label' => 'Menu',
        'not_found' => 'No encontre el menu solicitado.',
        'search_summary' => 'Encontre :count menus para este contexto.',
        'search_title' => 'Menus',
        'show_summary' => 'Este es el menu solicitado.',
        'show_title' => 'Menu',
    ],
    'clarification' => [
        'event_text' => 'Encontre mas de un evento que coincide con tu solicitud.',
        'event_description' => 'Elige el evento correcto para continuar de forma segura.',
        'event_title' => 'Que evento debo usar?',
        'scope_text' => 'Necesito un poco mas de direccion antes de continuar.',
        'scope_description' => 'Elige una de estas rutas guiadas del chat.',
        'scope_events' => 'Eventos',
        'scope_prep' => 'Prep activa',
        'scope_tasks' => 'Mis tareas',
        'scope_title' => 'Que debo revisar primero?',
    ],
    'recovery' => [
        'description' => 'No pude completar esa solicitud de forma segura.',
        'title' => 'Necesito un siguiente paso mas seguro',
        'tool_limit_detail' => 'La solicitud alcanzo el maximo de tool calls permitidos para un turno.',
        'event_summary_missing' => 'Ese resumen de evento ya no esta disponible en el contexto reciente del chat.',
        'event_not_found' => 'No encontre un evento que coincida en el workspace activo.',
        'member_not_found' => 'No pude asociar ese responsable con un miembro activo del workspace.',
        'task_update_missing_change' => 'Todavia necesito el nuevo estado o responsable antes de preparar la actualizacion de la tarea.',
        'task_not_found' => 'No encontre una tarea que coincida con esa solicitud de actualizacion.',
        'task_ambiguous' => 'Encontre varias tareas. Menciona el titulo o pideme primero tus tareas.',
        'provider_authentication' => 'Las credenciales del interprete de IA fueron rechazadas. No se hicieron cambios.',
        'provider_bad_request' => 'El interprete de IA rechazo el formato de la solicitud. No se hicieron cambios.',
        'provider_invalid_response' => 'El interprete de IA devolvio una respuesta invalida. No se hicieron cambios.',
        'provider_network_error' => 'Fallo la conexion con el interprete de IA. No se hicieron cambios.',
        'provider_rate_limit' => 'El interprete de IA alcanzo temporalmente el limite de solicitudes. No se hicieron cambios.',
        'provider_timeout' => 'El interprete de IA tardo demasiado en responder. No se hicieron cambios.',
        'provider_unavailable' => 'El interprete de IA no esta disponible en este momento. No se hicieron cambios.',
    ],
];
