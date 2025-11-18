<?php

return [
    'modal' => [
        'heading' => 'Sesión por expirar',
        'description' => 'Por seguridad, tu sesión se cerrará automáticamente por inactividad.',
        'actions' => [
            'stay' => [
                'label' => 'Mantener sesión activa',
            ],
            'logout' => [
                'label' => 'Cerrar sesión ahora',
            ],
        ],
        'inactive_seconds' => 'Cierre en :seconds segundos…',
    ],

    'notifications' => [
        'logged_out' => [
            'title' => 'Sesión cerrada',
            'body' => 'Tu sesión se cerró por inactividad.',
        ],
    ],
];
