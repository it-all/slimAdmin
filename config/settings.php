<?php
declare(strict_types=1);

// application configuration

return [

    'businessName' => 'example.com, LLC',

    'businessDba' => 'Example Company',

    'domainName' => DOMAIN_NAME,

    'errors' => [
        'emailTo' => [TOP_ROLE], // emails must be set in 'emails' section
        'fatalMessage' => 'Apologies, there has been an error on our site. We have been alerted and will correct it as soon as possible.',
        'logToDatabase' => true,
        'phpErrorLogPath' => APPLICATION_ROOT_DIRECTORY . '/storage/logs/phpErrors.log',
    ],

    'domainUseWww' => false,

    'session' => [
        'ttlHours' => 24,
        'savePath' => APPLICATION_ROOT_DIRECTORY . '/storage/sessions' // note probably requires chmod 777
    ],

    'adminPath' => 'private', // access site administration

    /** these can be overridden in .env for dev testing */
    'emails' => [
        TOP_ROLE => "owner@".DOMAIN_NAME,
        'programmer' => "programmer@".DOMAIN_NAME,
        'service' => "service@".DOMAIN_NAME
    ],

    'pageNotFoundText' => 'Page not found. Please check the URL. If correct, please email service@'.DOMAIN_NAME.' for assistance.',

    'mbInternalEncoding' => 'UTF-8',

    'authentication' => [
        'maxFailedLogins' => 5, // If met or exceeded in a session, will insert a system event and disallow further login attempts by redirecting to the homepage
        'administratorHomeRoutes' => [
            TOP_ROLE => ROUTE_SYSTEM_EVENTS,
        ],
    ],

    // if true removes leading and trailing blank space on all inputs
    'trimAllUserInput' => true,

    // how to add admin nav menu options
//        'adminNav' => [
//            'Test' => [
//                'route' => ROUTE_TEST,
//                'authorization' => TEST_VIEW_RESOURCE,
//                'subSections' => [
//                    'Insert' => [
//                        'route' => ROUTE_TEST_INSERT,
//                        'authorization' => TEST_INSERT_RESOURCE,
//                    ]
//                ],
//            ]
//        ],

    /** slim specific config */
    'slim' => [

        'outputBuffering' => 'append',

        'templatesPath' => APPLICATION_ROOT_DIRECTORY . '/templates/', // note slim requires trailing slash

        'addContentLengthHeader' => false, // if this is not disabled, slim/App.php threw an exception related to error handling, when the php set_error_handler() function was triggered

        // routerCacheFile should only be set in production (when routes are stable)
        // https://akrabat.com/slims-route-cache-file/
        // 'routerCacheFile' => APPLICATION_ROOT_DIRECTORY . '/storage/cache/router.txt',

    ] // end slim specific config
];
