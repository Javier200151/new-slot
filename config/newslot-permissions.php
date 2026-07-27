<?php

return [
    'guard' => 'web',

    'actions' => [
        'view' => 'Ver',
        'create' => 'Crear',
        'update' => 'Modificar',
        'delete' => 'Eliminar',
    ],

    'groups' => [
        'users' => [
            'label' => 'Usuarios y acceso',
            'icon' => 'heroicon-o-users',
            'resources' => [
                'users' => [
                    'label' => 'Usuarios',
                ],
                'roles' => [
                    'label' => 'Roles',
                ],
                'statuses' => [
                    'label' => 'Estados de usuario',
                ],
            ],
        ],

        'metopas' => [
            'label' => 'Metopas y promociones',
            'icon' => 'heroicon-o-trophy',
            'resources' => [
                'metopas' => [
                    'label' => 'Metopas',
                ],
                'user-metopas' => [
                    'label' => 'Asignación de metopas',
                ],
                'promos' => [
                    'label' => 'Promociones',
                ],
            ],
        ],

        'operations' => [
            'label' => 'Operaciones',
            'icon' => 'heroicon-o-map',
            'resources' => [
                'campaigns' => [
                    'label' => 'Campañas',
                ],
                'operations' => [
                    'label' => 'Operaciones',
                ],
                'operation-days' => [
                    'label' => 'Días de operación',
                ],
                'operation-types' => [
                    'label' => 'Tipos de operación',
                ],
                'operation-statuses' => [
                    'label' => 'Estados de operación',
                ],
                'periods' => [
                    'label' => 'Periodos',
                ],
            ],
        ],

        'events' => [
            'label' => 'Eventos',
            'icon' => 'heroicon-o-calendar-days',
            'resources' => [
                'events' => [
                    'label' => 'Eventos',
                ],
                'event-comments' => [
                    'label' => 'Comentarios de eventos',
                ],
                'event-results' => [
                    'label' => 'Resultados de eventos',
                ],
                'event-statuses' => [
                    'label' => 'Estados de eventos',
                ],
            ],
        ],

        'organization' => [
            'label' => 'Organización militar',
            'icon' => 'heroicon-o-shield-check',
            'resources' => [
                'armies' => [
                    'label' => 'Ejércitos',
                ],
                'factions' => [
                    'label' => 'Facciones',
                ],
                'allies' => [
                    'label' => 'Aliados',
                ],
                'sides' => [
                    'label' => 'Bandos',
                ],
                'sqa-groups' => [
                    'label' => 'Grupos SQA',
                ],
            ],
        ],

        'game-configuration' => [
            'label' => 'Juego y configuración',
            'icon' => 'heroicon-o-cog-6-tooth',
            'resources' => [
                'platforms' => [
                    'label' => 'Plataformas',
                ],
                'game-maps' => [
                    'label' => 'Mapas',
                ],
                'addons' => [
                    'label' => 'Addons',
                ],
                'addon-presets' => [
                    'label' => 'Presets de addons',
                ],
                'slot-types' => [
                    'label' => 'Tipos de slot',
                ],
                'radio-models' => [
                    'label' => 'Modelos de radio',
                ],
            ],
        ],

        'streaming' => [
            'label' => 'Streaming',
            'icon' => 'heroicon-o-video-camera',
            'resources' => [
                'streams' => [
                    'label' => 'Streams',
                ],
                'streamers' => [
                    'label' => 'Streamers',
                ],
            ],
        ],

        'system' => [
            'label' => 'Sistema',
            'icon' => 'heroicon-o-command-line',
            'resources' => [
                /*
                 * El registro de actividad debería ser de solo lectura.
                 * Añade "delete" si realmente quieres permitir eliminar logs.
                 */
                'activities' => [
                    'label' => 'Registro de actividad',
                    'actions' => [
                        'view',
                    ],
                ],
            ],
        ],
    ],
];