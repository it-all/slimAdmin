<?php
declare(strict_types=1);

/** note: this file can also be called for cli scripts.*/

namespace Infrastructure;

use Dotenv\Dotenv;
use Infrastructure\Database\Postgres;
use Infrastructure\Security\Authentication\AuthenticationService;
use Infrastructure\Security\Authorization\AuthorizationService;
use Infrastructure\Utilities\TrackerMiddleware;
use Entities\Events\EventsTableMapper;
use Infrastructure\Utilities;
use Infrastructure\Functions;
use Psr\Http\Message\ServerRequestInterface as Request;
use Infrastructure\Utilities\PhpMailerService;
use Slim\Factory\AppFactory;
use DI\Container;
use Psr\Container\ContainerInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\App;
use Slim\Csrf\Guard;
use Slim\Interfaces\RouteParserInterface;
use Slim\Middleware\MethodOverrideMiddleware;
use Slim\Psr7\Response;

/** singleton */
class SlimAdmin
{
    private $postgres;
    private $config;

    private $environmentalVariables;
    private $eventsTableMapper;
    private $mailer;

    /** if not null the session will be started with this id */
    private $sessionId;

    /** session variable keys */

    const SESSION_DEFAULT_TTL_HOURS = 24;
    const SESSION_KEY_LAST_ACTIVITY = 'lastActivity';

    /** front end notifications */
    const SESSION_KEY_NOTICE = 'notice';

    /** administrative notifications */
    const SESSION_KEY_ADMIN_NOTICE = 'adminNotice';

    /** administrative resource to load upon login */
    const SESSION_KEY_GOTO_ADMIN_PATH = 'gotoAdminPath';

    /** tracks number of login failures */
    const SESSION_KEY_NUM_FAILED_LOGINS = 'numFailedLogins';

    /** filter field values for admin list views, stored in session in order to keep filter on through multiple requests */
    const SESSION_KEY_ADMIN_LIST_VIEW_FILTER = 'adminListViewFilter';

    /** info concerning logged on administrator */
    const SESSION_KEY_ADMINISTRATOR_ID = 'administratorId';

    /** frontend notice statuses (can be used as css classes) */
    const STATUS_NOTICE_SUCCESS = 'noticeSuccess';
    const STATUS_NOTICE_FAILURE = 'noticeFailure';
    const STATUS_NOTICE_CAUTION = 'noticeCaution';
    const STATUS_NOTICE_MUTED = 'noticeMuted';

    /** admin notice statuses (can be used as css classes) */
    const STATUS_ADMIN_NOTICE_SUCCESS = 'adminNoticeSuccess';
    const STATUS_ADMIN_NOTICE_FAILURE = 'adminNoticeFailure';
    const STATUS_ADMIN_NOTICE_CAUTION = 'adminNoticeCaution';
    const STATUS_ADMIN_NOTICE_MUTED = 'adminNoticeMuted';

    const VALID_ROUTE_TYPES = ['index', 'index.reset', 'insert', 'update', 'delete'];

    /** args array key so controllers can pass user input data to views */
    const USER_INPUT_KEY = 'userInput';

    public static function getInstance(?string $sessionId = null)
    {
        static $instance = null;
        if ($instance === null) {
            $instance = new SlimAdmin($sessionId);
        }
        return $instance;
    }

    private function __construct(?string $sessionId = null)
    {
        $this->sessionId = $sessionId;
        $this->setEnvironmentalVariables();
        date_default_timezone_set($this->environmentalVariables['TIME_ZONE']);
        $this->config = require APPLICATION_ROOT_DIRECTORY . '/config/settings.php';
        $this->setEmailsConfig();

        /** so no need to set encoding for each mb_strlen() call */
        mb_internal_encoding($this->config['mbInternalEncoding']); 

        /** add some .env to config */
        // assume this is a live server unless IS_LIVE env var set false
        $this->config['isLive'] = !(array_key_exists('IS_LIVE', $this->environmentalVariables) && !$this->environmentalVariables['IS_LIVE']);
        $this->config['subDomainName'] = isset($this->environmentalVariables['SUBDOMAIN']) ? $this->environmentalVariables['SUBDOMAIN'] : '';
        $this->config['domainName'] = $this->environmentalVariables['DOMAIN_NAME'];
        $this->config['hostname'] = isset($this->environmentalVariables['HOSTNAME']) ? $this->environmentalVariables['HOSTNAME'] : $this->environmentalVariables['DOMAIN_NAME'];
        $this->config['businessName'] = $this->environmentalVariables['BUSINESS_NAME'];
        $this->config['businessDba'] = $this->environmentalVariables['BUSINESS_DBA'] ?? null;

        // echo errors on dev sites unless env var ERRORS_ECHO_DEV set false
        $this->config['errors']['echoDev'] = !(array_key_exists('ERRORS_ECHO_DEV', $this->environmentalVariables) && !$this->environmentalVariables['ERRORS_ECHO_DEV']);

        // do not email error notifications on dev sites unless env var ERRORS_EMAIL_DEV set true
        $this->config['errors']['emailDev'] = array_key_exists('ERRORS_EMAIL_DEV', $this->environmentalVariables) && $this->environmentalVariables['ERRORS_EMAIL_DEV'];

        /** emailer */
        $this->mailer = null; // init
        $defaultFromName = $this->environmentalVariables['DEFAULT_FROM_NAME'] ?? $this->config['businessDba'];
        /** if protocol is set, set up emailer, which is used in error handler and container */
        if ($this->environmentalVariables['PHPMAILER_PROTOCOL'] !== null) {
            $this->mailer = new Utilities\PhpMailerService(
                $this->config['errors']['phpErrorLogPath'],
                $this->environmentalVariables['EMAIL_DEFAULT_RETURN_PATH'],
                $this->environmentalVariables['DEFAULT_FROM_EMAIL'],
                $defaultFromName,
                $this->environmentalVariables['PHPMAILER_PROTOCOL'],
                $this->environmentalVariables['PHPMAILER_SMTP_HOST'],
                $this->environmentalVariables['PHPMAILER_SMTP_PORT'],
                $this->environmentalVariables['PHPMAILER_SMTP_USERNAME'],
                $this->environmentalVariables['PHPMAILER_SMTP_PASSWORD'],
            );
        }

        /** error handling */

        /** only echo on dev sites */
        $echoErrors = !$this->config['isLive'] && $this->config['errors']['echoDev'];

        $emailErrors = $this->mailer !== null && ($this->config['isLive'] || $this->config['errors']['emailDev']);
        $emailErrorsTo = $this->config['emails'][strtolower($this->config['errors']['emailTo'])] ?? [];

        $errorHandler = new Utilities\ErrorHandler(
            $this->config['hostname'],
            $this->config['errors']['phpErrorLogPath'],
            $this->getRedirect(),
            $echoErrors,
            $emailErrors,
            $emailErrorsTo,
            $this->mailer, 
            $this->config['errors']['fatalMessage']
        );

        /** workaround for catching some fatal errors like parse errors. note that parse errors in this file and index.php are not handled, but cause a fatal error with display (not displayed if display_errors is off in php.ini, but the ini_set call will not affect it). */
        register_shutdown_function(array($errorHandler, 'shutdownFunction'));
        set_error_handler(array($errorHandler, 'phpErrorHandler'));
        set_exception_handler(array($errorHandler, 'throwableHandler'));

        /**  all, including future types */
        error_reporting( -1 ); 

        /** do not have php display errors, since this will be determined by config and done in error handler */
        ini_set( 'display_errors', 'off' );
        ini_set( 'display_startup_errors', 'off' );

        /** 
         * any errors prior to this point will not be logged
         * even though the error handler logs errors, this ensures errors in the error handler itself or in this file after this point will be logged. note, if using slim error handling, this will log all php errors
         */
        ini_set('error_log', $this->config['errors']['phpErrorLogPath']);

        /** 
         * set up and connect to postgres, which is used in error handler and container
         * this is done after setting error handler in case connection fails
         * note, injected to error handler below
         */
        $postgresConnectionString = (array_key_exists('POSTGRES_CONNECTION_STRING', $this->environmentalVariables)) ? $this->environmentalVariables['POSTGRES_CONNECTION_STRING'] : '';
        $this->postgres = Postgres::getInstance($postgresConnectionString); // connects

        /** used in error handler and container */
        $this->eventsTableMapper = EventsTableMapper::getInstance();

        if ($this->config['errors']['logToDatabase']) {
            $errorHandler->setEventsTableMapper($this->eventsTableMapper);
        }

        if (!Functions::isRunningFromCommandLine()) {
            /**
             * force all pages to be https if scheme is set https in environmental variable
             * note this practice is ok:
             * http://security.stackexchange.com/questions/49645/actually-isnt-it-bad-to-redirect-http-to-https
             */
            if ($this->environmentalVariables['SCHEME'] === 'https' && !$this->isHttpsRequest()) {
                $this->redirect();
            }

            /** SESSION */
            /** note calling session_save_path here messed up redirecting to the old admin, if that is necessary it's probably better to do in php.ini or .htaccess  */
            $sessionTtlHours = isset($this->environmentalVariables['SESSION_TTL_HOURS']) ? $this->environmentalVariables['SESSION_TTL_HOURS'] : self::SESSION_DEFAULT_TTL_HOURS;
            $sessionTtlSeconds = (int) $sessionTtlHours * 60 * 60;
            ini_set('session.gc_maxlifetime', (string) $sessionTtlSeconds);
            ini_set('session.cookie_lifetime', (string) $sessionTtlSeconds);

            /** force session id if necessary, must be done before starting session */
            if ($this->sessionId !== null) {
                session_id($this->sessionId);
            }
            session_start();

            /** update last activity time stamp */
            $_SESSION[self::SESSION_KEY_LAST_ACTIVITY] = time();
        }
    }

    public function getEventsTableMapper(): EventsTableMapper
    {
        return $this->eventsTableMapper;
    }

    /**
     * Loops through all environmentalVariables looking for EMAILS_ variables map into config
     * Note that this should probably be refactored to use administrator emails
     * instead of hard coding in .env
     * but there should probably be 1 hardcoded .env email / array for error notifications
     */
    private function setEmailsConfig() 
    {
        $this->config['emails'] = [];

        $emailsEnvVarsPrefix = "EMAILS_";
        foreach ($this->environmentalVariables as $key => $value) {
            if (substr($key, 0, 7) == $emailsEnvVarsPrefix) {
                $emailRole = substr($key, strlen($emailsEnvVarsPrefix));
                $this->config['emails'][strtolower($emailRole)] = $value; 
            }
        }
    }

    private function setEnvironmentalVariables() 
    {
        $dotenv = Dotenv::createImmutable(APPLICATION_ROOT_DIRECTORY);
        $dotenv->load();
        $this->environmentalVariables = $_ENV;
        
        /**
         * Environmental Booleans
         * define allowed values of 0,1 (must set as required first)
         * then convert each to a php boolean
         * see https://github.com/vlucas/phpdotenv/issues/365 for why they must be set required
         */
        $envBools = ['IS_LIVE', 'ERRORS_ECHO_DEV', 'ERRORS_EMAIL_DEV'];
        foreach ($envBools as $boolean) {
            $dotenv->required($boolean)->isBoolean();
            $this->environmentalVariables[$boolean] = $this->environmentalVariables[$boolean] ? true : false;
        }

        /**
         * Environmental Arrays
         * explode into php array
         * note this can change with php dot env V3+ with multiline support
         * see https://github.com/vlucas/phpdotenv/pull/271
         */
        $envArrays = [
            'EMAILS_OWNER' => ",",
            'EMAILS_PROGRAMMER' => ",",
        ];
        
        foreach ($envArrays as $varName => $explodeString) {
            $this->environmentalVariables[$varName] = array_map('trim', explode($explodeString, $this->environmentalVariables[$varName]));
        }

        $dotenv->required(['DOMAIN_NAME', 'BUSINESS_NAME', 'POSTGRES_CONNECTION_STRING', 'IS_LIVE']);

        $dotenv->required('PHPMAILER_PROTOCOL')->allowedValues(['', 'smtp', 'sendmail', 'mail', 'qmail']);
        /** explicitly set to null if blank */
        $this->environmentalVariables['PHPMAILER_PROTOCOL'] = trim($this->environmentalVariables['PHPMAILER_PROTOCOL']) == '' ? null : $this->environmentalVariables['PHPMAILER_PROTOCOL'];

        if ($this->environmentalVariables['PHPMAILER_PROTOCOL'] == 'smtp') {
            $dotenv->required('PHPMAILER_SMTP_HOST');
            $dotenv->required('PHPMAILER_SMTP_PORT')->isInteger();

            $this->environmentalVariables['PHPMAILER_SMTP_HOST'] = (array_key_exists('PHPMAILER_SMTP_HOST', $this->environmentalVariables)) ? $this->environmentalVariables['PHPMAILER_SMTP_HOST'] : null;

            $this->environmentalVariables['PHPMAILER_SMTP_PORT'] = (array_key_exists('PHPMAILER_SMTP_PORT', $this->environmentalVariables)) ? (int) $this->environmentalVariables['PHPMAILER_SMTP_PORT'] : null;
        } else {
            $this->environmentalVariables['PHPMAILER_SMTP_HOST'] = null;
            $this->environmentalVariables['PHPMAILER_SMTP_PORT'] = null;
        }
    }

    public function run()
    {
        // Create Container using PHP-DI
        $slimContainer = new Container();
        $slim = AppFactory::createFromContainer($slimContainer);
        $this->setSlimDependencies($slim, $slimContainer, $this->eventsTableMapper, $this->mailer, $slim->getRouteCollector()->getRouteParser());
        $this->registerSlimRoutes($slim, $slimContainer);
        $this->addSlimMiddleware($slim, $slimContainer);
        $slim->run();
    }

    private function addSlimMiddleware(App $slim, Container $slimContainer)
    {
        // Register Middleware To Be Executed On All Routes
        /** Insert System Event for every resource request */
        if (isset($this->config['trackAll']) && $this->config['trackAll']) {
            $slim->add(new TrackerMiddleware($slimContainer));
        }        
        $slim->add('csrf');
        $slim->addRoutingMiddleware();
        $slim->add(new MethodOverrideMiddleware);

        // remove trailing slash in uri
        $slim->add(function (Request $request, RequestHandlerInterface $handler) {
            $uri = $request->getUri();
            $path = $uri->getPath();
            
            if ($path != '/' && substr($path, -1) == '/') {
                // recursively remove slashes when its more than 1 slash
                $path = rtrim($path, '/');
        
                // permanently redirect paths with a trailing slash
                // to their non-trailing counterpart
                $uri = $uri->withPath($path);
                
                if ($request->getMethod() == 'GET') {
                    $response = new Response();
                    return $response
                        ->withHeader('Location', (string) $uri)
                        ->withStatus(301);
                } else {
                    $request = $request->withUri($uri);
                }
            }
        
            return $handler->handle($request);
        });
    }

    private function setSlimDependencies(App $slim, Container $slimContainer, EventsTableMapper $eventsTableMapper, ?Utilities\PhpMailerService $mailer, RouteParserInterface $routeParser)
    {
        $responseFactory = $slim->getResponseFactory();

        /** Settings */
        $slimContainer->set('settings', $this->config);

        /** Authentication */
        $slimContainer->set('authentication', function(ContainerInterface $container) {
            $settings = $container->get('settings');
            return new AuthenticationService($settings['authentication']['maxFailedLogins'], $settings['authentication']['administratorHomeRoutes']);
        });

        /** Authorization */
        $slimContainer->set('authorization', function(ContainerInterface $container) {
            // $settings = $container->get('settings');
            return new AuthorizationService();
        });

        /** CSRF */
        $slimContainer->set('csrf', function () use ($responseFactory) {
            return new Guard($responseFactory);
        });

        /** Events (Database Log) */
        $slimContainer->set('events', function(ContainerInterface $container) use ($eventsTableMapper) {
            return $eventsTableMapper;
        });

        /** Mailer */
        if ($mailer !== null) {
            $slimContainer->set('mailer', function(ContainerInterface $container) use ($mailer) {
                return $mailer;
            });    
        }

        /** Route Parser */
        $slimContainer->set('routeParser', $routeParser);

        $postgres = $this->postgres;
        $slimContainer->set('postgres', function(ContainerInterface $container) use ($postgres) {
            return $postgres;
        });

        /** Template */
        $slimContainer->set('view', function(ContainerInterface $container) {
            $settings = $container->get('settings');
            $templateVariables = [
                'domainName' => $settings['domainName'],
                'businessName' => $settings['businessName'],
                'businessDba' => $settings['businessDba'],
                'isLive' => $settings['isLive'],
                'authentication' => $container->get('authentication'),
                'authorization' => $container->get('authorization'),
                'csrfNameKey' => $container->get('csrf')->getTokenNameKey(),
                'csrfName' => $container->get('csrf')->getTokenName(),
                'csrfValueKey' => $container->get('csrf')->getTokenValueKey(),
                'csrfValue' => $container->get('csrf')->getTokenValue(),
                'routeParser' => $container->get('routeParser'),
            ];
            return new \Slim\Views\PhpRenderer($settings['slim']['templatesPath'], $templateVariables);
        });
    }

    /** note need the arguments for routes.php to access */
    private function registerSlimRoutes(App $slim, Container $slimContainer)
    {
        /** make available to routes file */
        $config = $this->config; 
        require APPLICATION_ROOT_DIRECTORY . '/config/routes.php';
    }

    public function getUrl(?bool $forceHttp = false, ?bool $forceDomainRemote = false): string 
    {
        $scheme = $forceHttp ? 'http' : $this->environmentalVariables['SCHEME'];
        $domain = $forceDomainRemote ? $this->config['domainName'] : $this->config['hostname'];
        $url = $scheme . "://";
        if (mb_strlen($this->config['subDomainName']) > 0) {
            $url .= $this->config['subDomainName'] . ".";
        }
        $url .= $domain;
        return $url;
    }

    /** if called with no args, redirects to current URI with proper protocol, www or not based on config, and query string */
    private function redirect(?string $toURI = null)
    {
        if (Functions::isRunningFromCommandLine()) {
            exit();
        }
        header("Location: ".$this->getRedirect($toURI));
        exit();
    }

    private function getRedirect(?string $toURI = null): ?string
    {
        if (is_null($toURI)) {
            if (Functions::isRunningFromCommandLine()) {
                return null;
            }
            $toURI = $_SERVER['REQUEST_URI'];
        }

        /** add initial '/' if nec */
        if (substr($toURI, 0, 1) != "/") {
            $toURI = "/" . $toURI;
        }

        return $this->getUrl() . $toURI;
    }

    /**
     * determines if current page is https
     * @return bool
     */
    private function isHttpsRequest(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || intval($_SERVER['SERVER_PORT']) === 443;
    }

    /**
     * determines if a $sessionId id is valid.
     * @param $session_id
     * @param bool optional $isEmptyIdValid
     * @return bool
     */
    private function isSessionIdValid(string $sessionId): bool
    {
        // if (mb_strlen($sessionId) == 0) { // if blank, there is no session id
        //     return true;
        // }
        return preg_match('/^[-,a-zA-Z0-9]{1,128}$/', $sessionId) > 0;
    }

    /** because of route naming conventions, only send resourceType of post, put, or patch */
    public static function getRouteName(bool $isAdmin = true, ?string $routePrefix = null, ?string $routeType = null, ?string $requestMethod = null): string
    {
        $routeName = '';

        if ($isAdmin) {
            $routeName .= ROUTEPREFIX_ADMIN;
        }

        if ($routePrefix !== null) {
            $routeName .= '.' . $routePrefix;
        }

        if ($requestMethod !== null) {
            $validActionMethods = ['put', 'post'];
            if (!in_array($requestMethod, $validActionMethods)) {
                throw new \Exception("Invalid request method $requestMethod. Only post and put accepted in route names.");
            }

            $routeName .= '.' . $requestMethod;
        }

        if ($routeType !== null) {
            if (!in_array($routeType, self::VALID_ROUTE_TYPES)) {
                throw new \Exception("Invalid route type $routeType");
            }

            $routeName .= '.' . $routeType;
        }

        return $routeName;
    }

    public static function addAdminNotice(string $text, string $status = 'success') 
    {
        $statusValues = ['success', 'failure', 'caution', 'muted'];
        if (!in_array($status, $statusValues)) {
            throw new \InvalidArgumentException("Invalid admin notice status $status");
        }

        $_SESSION[self::SESSION_KEY_ADMIN_NOTICE][] = ["$text", 'adminNotice'.ucfirst($status)];
    }

    public static function clearAdminNotices()
    {
        unset($_SESSION[self::SESSION_KEY_ADMIN_NOTICE]);
    }

    public function getEnvironmentalVariables(): array 
    {
        return $this->environmentalVariables;
    }

    public function getWhichEnvironmentalVariable(string $key) 
    {
        if (!array_key_exists($key, $this->environmentalVariables)) {
            return null;
        }
        return $this->environmentalVariables[$key];
    } 

    public function getConfig(): array 
    {
        return $this->config;
    }

    public function getWhichConfig(string $key) 
    {
        if (!array_key_exists($key, $this->config)) {
            throw new \InvalidArgumentException("$key does not exist as a key for config");
        }
        return $this->config[$key];
    }

    public function getMailer(): PhpMailerService 
    {
        return $this->mailer;
    }

    public function getPostgresConnection()
    {
        return $this->postgres->getConnection();
    }
}
