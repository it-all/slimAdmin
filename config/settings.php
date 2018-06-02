<?php
declare(strict_types=1);

// application configuration

$domainName = 'example.com';

return [

    'businessName' => 'example.com, LLC',

    'businessDba' => 'Example Company',

    'domainName' => $domainName,

    'errors' => [
        'emailTo' => ['owner'], // emails must be set in 'emails' section
        'fatalMessage' => 'Apologies, there has been an error on our site. We have been alerted and will correct it as soon as possible.',
        'logToDatabase' => true,
        'echoDev' => true, // echo on dev servers (note, live server will never echo)
        'emailDev' => false // email on dev servers (note, live server will always email)
    ],

    'domainUseWww' => false,

    'session' => [
        'ttlHours' => 24,
        'savePath' => APPLICATION_ROOT_DIRECTORY . '/storage/sessions' // note probably requires chmod 777
    ],

    'adminPath' => 'private',

    'emails' => [
        'owner' => "owner@".$domainName,
        'programmer' => "programmer@".$domainName,
        'service' => "service@".$domainName
    ],

    'pageNotFoundText' => 'Page not found. Please check the URL. If correct, please email service@'.$domainName.' for assistance.',

    'slim' => [

        'outputBuffering' => 'append',

        'templatesPath' => APPLICATION_ROOT_DIRECTORY . '/templates/', // note slim requires trailing slash

        'addContentLengthHeader' => false, // if this is not disabled, slim/App.php threw an exception related to error handling, when the php set_error_handler() function was triggered

        // routerCacheFile should only be set in production (when routes are stable)
        // https://akrabat.com/slims-route-cache-file/
//            'routerCacheFile' => APPLICATION_ROOT_DIRECTORY . '/storage/cache/router.txt',

        'authentication' => [
            'maxFailedLogins' => 5, // If exceeded in a session, will insert a system event and disallow further login attempts
            'adminHomeRoutes' => []
        ],

        'authorization' => [
            /* Either functionalityCategory => permissions or functionalityCategory.functionality => permissions where permissions is either a string set to the minimum authorized role or an array of authorized roles */
            // Important to properly match the indexes to routes authorization
            // The role values must be in the database: roles.role
            // If the index is not defined for a route or nav section, no authorization check is performed (all administrators (logged in users) will be able to access resource or view nav section). therefore, indexes only need to be defined for routes and nav sections that require authorization greater than the base (least permission) role.
            // Note also that it's possible to give a role access to a resource, but then hide the navigation to to that resource to that role, which would usually be undesirable. For example, below the bookkeeper is authorized to view System Events, but will not see the System nav section because of the NAV_ADMIN_SYSTEM entry permissions being set to 'owner'
            'administratorPermissions' => [
                ROUTE_LOGIN_ATTEMPTS => 'director',
                ROUTE_SYSTEM_EVENTS => 'bookkeeper',
                ROUTE_ADMINISTRATORS => 'director',
                ROUTE_ADMINISTRATORS_RESET => 'director',
                ROUTE_ADMINISTRATORS_INSERT => 'owner',
                ROUTE_ADMINISTRATORS_UPDATE => 'owner',
                ROUTE_ADMINISTRATORS_DELETE => 'owner',
                ROUTE_ADMINISTRATORS_ROLES => 'owner',
                ROUTE_ADMINISTRATORS_ROLES_INSERT => 'owner',
                ROUTE_ADMINISTRATORS_ROLES_UPDATE => 'owner',
                ROUTE_ADMINISTRATORS_ROLES_DELETE => 'owner',
                NAV_ADMIN_SYSTEM => 'owner',
            ],

        ],

        'adminDefaultRole' => 'user',

        // if true removes leading and trailing blank space on all inputs
        'trimAllUserInput' => true,

        // how to add admin nav menu options
//        'adminNav' => [
//            'Test' => [
//                'route' => ROUTE_SYSTEM_EVENTS,
//                'subSections' => [
//                    'Insert' => [
//                        'route' => ROUTE_ADMINISTRATORS_ROLES_INSERT,
//                    ]
//                ],
//            ]
//        ],

    ] // end slim specific config

];
