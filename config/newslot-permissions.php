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
            'label' => 'Actividades',
            'icon' => 'heroicon-o-map',
            'resources' => [
                'campaigns' => [
                    'label' => 'Campañas',
                ],
                'operations' => [
                    'label' => 'Actividades',
                    'scope' => 'operation_type',
                ],
                'operation-days' => [
                    'label' => 'Días de actividad',
                ],
                'operation-types' => [
                    'label' => 'Tipos de actividad',
                ],
                'operation-statuses' => [
                    'label' => 'Estados de actividad',
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
                    'scope' => 'operation_type',
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

                'event-orbat' => [
                    'label' => 'ORBAT',
                    'scope' => 'operation_type',
                    'actions' => [
                        'manage' => 'Manejar ORBAT',
                    ],
                ],
            ],
        ],

        'organization' => [
            'label' => 'Organización militar',
            'icon' => 'heroicon-o-shield-check',
            'resources' => [
                'countries' => [
                    'label' => 'Países',
                ],
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


        'community' => [
            'label' => 'Comunidad',
            'icon' => 'heroicon-o-chat-bubble-left-right',
            'resources' => [
                'community-forum-cantina' => [
                    'label' => 'Foro · Cantina',
                    'actions' => [
                        'create' => 'Publicar nuevos hilos',
                        'reply' => 'Responder a hilos',
                        'poll' => 'Crear y gestionar votaciones',
                        'moderate' => 'Cerrar, reabrir y fijar hilos',
                        'delete' => 'Eliminar hilos y respuestas',
                    ],
                ],
                'community-forum-debate' => [
                    'label' => 'Foro · Debates',
                    'actions' => [
                        'create' => 'Publicar nuevos hilos',
                        'reply' => 'Responder a hilos',
                        'poll' => 'Crear y gestionar votaciones',
                        'moderate' => 'Cerrar, reabrir y fijar hilos',
                        'delete' => 'Eliminar hilos y respuestas',
                    ],
                ],
                'community-forum-convocatoria' => [
                    'label' => 'Foro · Convocatorias',
                    'actions' => [
                        'create' => 'Publicar nuevos hilos',
                        'reply' => 'Responder a hilos',
                        'poll' => 'Crear y gestionar votaciones',
                        'moderate' => 'Cerrar, reabrir y fijar hilos',
                        'delete' => 'Eliminar hilos y respuestas',
                    ],
                ],
                'community-forum-propuesta' => [
                    'label' => 'Foro · Propuestas',
                    'actions' => [
                        'create' => 'Publicar nuevos hilos',
                        'reply' => 'Responder a hilos',
                        'poll' => 'Crear y gestionar votaciones',
                        'moderate' => 'Cerrar, reabrir y fijar hilos',
                        'delete' => 'Eliminar hilos y respuestas',
                    ],
                ],
                'community-forum-consulta' => [
                    'label' => 'Foro · Consultas',
                    'actions' => [
                        'create' => 'Publicar nuevos hilos',
                        'reply' => 'Responder a hilos',
                        'poll' => 'Crear y gestionar votaciones',
                        'moderate' => 'Cerrar, reabrir y fijar hilos',
                        'delete' => 'Eliminar hilos y respuestas',
                    ],
                ],
            ],
        ],

        'system' => [
            'label' => 'Sistema',
            'icon' => 'heroicon-o-command-line',
            'resources' => [
                'pages' => [
                    'label' => 'Páginas',
                ],

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