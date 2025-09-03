<?php

return [
    'accepted' => 'El campo :attribute debe ser aceptado.',
    'required' => 'El campo :attribute es obligatorio.',

    'custom' => [
        'proceso_id' => [
            'required' => 'Seleccione un Proceso Abierto.',
        ],
        'proceso_fecha_id' => [
            'required' => 'Seleccione una Fecha Activa del Proceso.',
        ],
        'locales_maestro_ids' => [
            'required' => 'Seleccione al menos un Local para asignar.',
        ],
        'data.proceso_id' => [
            'required' => 'Seleccione un Proceso Abierto.',
        ],
        'data.proceso_fecha_id' => [
            'required' => 'Seleccione una Fecha Activa del Proceso.',
        ],
        'data.locales_maestro_ids' => [
            'required' => 'Seleccione al menos un Local para asignar.',
        ],
    ],

    'attributes' => [
        'proceso_id' => 'Proceso Abierto',
        'proceso_fecha_id' => 'Fecha Activa del Proceso',
        'locales_maestro_ids' => 'Locales a Asignar',
        'data.proceso_id' => 'Proceso Abierto',
        'data.proceso_fecha_id' => 'Fecha Activa del Proceso',
        'data.locales_maestro_ids' => 'Locales a Asignar',
    ],
];
