<?php
return [
    'navigation' => [
        'authentication-log' => [
            'group' => 'Ajustes',
            'label' => 'Historial de Autenticación',
            'plural-label' => 'Historial de Autenticación',
        ],
    ],
    'resource' => [
        'label' => 'Historial de Autenticación',
        'plural-label' => 'Historial de Autenticación',
        'columns' => [
            'authenticatable' => 'Usuario',
            'ip_address' => 'Dirección IP',
            'user_agent' => 'Agente de Usuario',
            'login_at' => 'Inicio de Sesión',
            'login_successful' => 'Éxito',
            'logout_at' => 'Cierre de Sesión',
            'cleared_by_user' => 'Limpiado por el usuario',
            'location' => 'Ubicación',
        ],
        'filter' => [
            'login_successful' => 'Inicio de sesión exitoso',
            'login_unsuccessful' => 'Inicio de sesión fallido',
        ],
    ],
    'section' => [
        'widget' => [
            'label' => 'Historial de Autenticación',
            'login_successful' => 'Inicio de sesión exitoso',
            'login_unsuccessful' => 'Inicio de sesión fallido',
        ],
    ],
];