<?php
declare(strict_types=1);

// application configuration

return [
    
    'errors' => [
        'emailTo' => 'PROGRAMMER', // this email must be set in .env
        'fatalMessage' => 'Apologies, there has been an error on our site. We have been alerted and will correct it as soon as possible.',
        'logToDatabase' => true,
        'phpErrorLogPath' => APPLICATION_ROOT_DIRECTORY . '/storage/logs/phpErrors.log',
    ],

    'adminPath' => ADMIN_DIR, // access site administration

    'scriptsPath' => APPLICATION_ROOT_DIRECTORY . '/cliScripts',

    'pageNotFoundText' => 'Page not found. Please check the URL.',

    // both usernames and roles can be entered, usernames will take precedence
    'authentication' => [
        // If met or exceeded in a session, will insert a event and disallow further login attempts by redirecting to the homepage
        'maxFailedLogins' => 5, 
        // only named routes accepted.
        // the admin /home will redirect using these settings
        'administratorHomeRoutes' => [
            TOP_ROLE => ROUTE_EVENTS,
        ],
    ],

    'orm' => [
        'disallowTables' => [],
        'limitResultsDefault' => 300,
        'allowInsertsDefault' => true,
        'allowUpdatesDefault' => true,
        'allowDeletesDefault' => true,
        'tables' => [
            'event_types' => [
                'allowUpdates' => false,
                'allowDeletes' => false,
            ],
            'events' => [
                'allowInserts' => false,
                'allowUpdates' => false,
                'allowDeletes' => false,
            ],
        ],
        'administrators' => [  // allowed tables by username hack
            'username1' => [
                'table1',
                'table2',
            ]
        ]
    ],

    // if true removes leading and trailing blank space on all inputs
    'trimAllUserInput' => true,

    /** whether to enter all resource requests into events database table */
    'trackAll' => false,

    /** so no need to set encoding for each mb_strlen() call */
    'mbInternalEncoding' => 'UTF-8',

    /** sample admin nav - displayed after default sections */
    'adminNav' => [
        // 'Option Name' => [
        //     'route' => ROUTE_HOME,
        //     'authorization' => EVENTS_VIEW_RESOURCE,
        //     'subSections' => [
        //         'Insert' => [
        //             'route' => ROUTE_HOME,
        //             'authorization' => EVENTS_VIEW_RESOURCE,
        //         ],
        //     ]
        // ],
    ],

    /** slim specific config */
    'slim' => [
        'templatesPath' => TEMPLATES_PATH, // note slim requires trailing slash
    ],
];
