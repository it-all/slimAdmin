<?php
declare(strict_types=1);

/** note: this file can also be called for cli scripts.*/

namespace SlimPostgres;

use Dotenv\Dotenv;
use SlimPostgres\Database\Postgres;
use SlimPostgres\Security\Authentication\AuthenticationService;
use SlimPostgres\Security\Authorization\AuthorizationService;
use SlimPostgres\Security\CsrfMiddleware;
use SlimPostgres\SystemEvents\SystemEventsMapper;
use SlimPostgres\Utilities;

class App
{
    private $config;

    /** @var array config settings that get added to slim so framework can access through container */
    private $commonConfigSettingsKeys;

    private $environmentalVariables;
    private $database;
    private $systemEventsMapper;
    private $mailer;

    /** session variable keys */

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

    /** administrator sub-keys */
    // const SESSION_ADMINISTRATOR_KEY_ID = 'id';
    // const SESSION_ADMINISTRATOR_KEY_NAME = 'name';
    // const SESSION_ADMINISTRATOR_KEY_USERNAME = 'username';
    // const SESSION_ADMINISTRATOR_KEY_ROLES = 'roles';
    // const SESSION_ADMINISTRATOR_KEY_ROLES_NAME = 'roleName';
    // const SESSION_ADMINISTRATOR_KEY_ROLES_LEVEL = 'roleLevel';

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

    public function __construct()
    {
        $this->commonConfigSettingsKeys = ['isLive', 'businessName', 'businessDba', 'emails', 'authentication', 'authorization', 'trimAllUserInput'];
        $dotenv = new Dotenv(APPLICATION_ROOT_DIRECTORY);
        $dotenv->load();

        $this->environmentalVariables = getenv();

        /** validate .env (note, not thorough validation) */
        $dotenv->required('PHPMAILER_PROTOCOL')->allowedValues(['smtp', 'sendmail', 'mail', 'qmail']);
        $phpMailerProtocol = $this->environmentalVariables['PHPMAILER_PROTOCOL'];
        if ($phpMailerProtocol == 'smtp') {
            $dotenv->required('PHPMAILER_SMTP_HOST');
            $dotenv->required('PHPMAILER_SMTP_PORT')->isInteger();
        }

        $this->config = require APPLICATION_ROOT_DIRECTORY . '/config/settings.php';

        if (isset($this->config['emails'])) {
            foreach ($this->config['emails'] as $emailRole => $email) {
                if (isset($this->environmentalVariables['EMAILS_'.strtoupper($emailRole)])) {
                    $this->config['emails'][$emailRole] = $this->environmentalVariables['EMAILS_'.strtoupper($emailRole)];
                }
            }
        }

        mb_internal_encoding($this->config['mbInternalEncoding']); // so no need to set encoding for mb_strlen()

        /** add some .env to config */
        // assume this is a live server unless IS_LIVE env var set false
        $this->config['isLive'] = !(array_key_exists('IS_LIVE', $this->environmentalVariables) && $this->environmentalVariables['IS_LIVE'] === "0");

        // echo errors on dev sites unless env var ERRORS_ECHO_DEV set false
        $this->config['errors']['echoDev'] = !(array_key_exists('ERRORS_ECHO_DEV', $this->environmentalVariables) && $this->environmentalVariables['ERRORS_ECHO_DEV'] === "0");

        // do not email error notifications on dev sites unless env var ERRORS_EMAIL_DEV set true
        $this->config['errors']['emailDev'] = array_key_exists('ERRORS_EMAIL_DEV', $this->environmentalVariables) && $this->environmentalVariables['ERRORS_EMAIL_DEV'] === "1";

        /** set up emailer, which is used in error handler and container */
        $phpMailerSmtpHost = (array_key_exists('PHPMAILER_SMTP_HOST', $this->environmentalVariables)) ? $this->environmentalVariables['PHPMAILER_SMTP_HOST'] : null;
        $phpMailerSmtpPort = (array_key_exists('PHPMAILER_SMTP_PORT', $this->environmentalVariables)) ? (int) $this->environmentalVariables['PHPMAILER_SMTP_PORT'] : null;
        $disableMailerSend = !$this->config['isLive'] && !$this->config['errors']['emailDev'];
        $this->mailer = new Utilities\PhpMailerService(
            $this->config['errors']['phpErrorLogPath'],
            $this->config['emails']['service'],
            $this->config['businessName'],
            $phpMailerProtocol,
            $phpMailerSmtpHost,
            $phpMailerSmtpPort,
            $disableMailerSend
        );

        /** error handling */

        /** only echo on dev sites */
        $echoErrors = !$this->config['isLive'] && $this->config['errors']['echoDev'];

        $emailErrors = $this->config['isLive'] || $this->config['errors']['emailDev'];
        $emailErrorsTo = [];
        if (array_key_exists('emailTo', $this->config['errors'])) {
            foreach ($this->config['errors']['emailTo'] as $roleEmail) {
                $emailErrorsTo[] = $this->config['emails'][$roleEmail];
            }
        }

        $errorHandler = new Utilities\ErrorHandler(
            $this->config['errors']['phpErrorLogPath'],
            $this->getRedirect(),
            $echoErrors,
            $emailErrors,
            $emailErrorsTo,
            $this->mailer
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
        $postgres = Postgres::getInstance($postgresConnectionString);

        /** used in error handler and container */
        $this->systemEventsMapper = SystemEventsMapper::getInstance();

        if ($this->config['errors']['logToDatabase']) {
            $errorHandler->setSystemEventsMapper($this->systemEventsMapper);
        }

        if (!Utilities\Functions::isRunningFromCommandLine()) {
            /**
             * verify/force all pages to be https. and verify/force www or not www based on Config::useWww
             * if not, REDIRECT TO PROPER SECURE PAGE
             * note this practice is ok:
             * http://security.stackexchange.com/questions/49645/actually-isnt-it-bad-to-redirect-http-to-https
             */
            if (!$this->isHttps() || ($this->config['domainUseWww'] && !$this->isWww()) || (!$this->config['domainUseWww'] && $this->isWww())) {
                $this->redirect();
            }

            /** SESSION */
            $sessionTTLseconds = $this->config['session']['ttlHours'] * 60 * 60;
            ini_set('session.gc_maxlifetime', (string) $sessionTTLseconds);
            ini_set('session.cookie_lifetime', (string) $sessionTTLseconds);
            if (!$this->isSessionIdValid(session_id())) {
                session_regenerate_id(true);
            }
            if (isset($this->config['session']['savePath']) && mb_strlen($this->config['session']['savePath']) > 0) {
                session_save_path($this->config['session']['savePath']);
            }
            session_start();

            /** update last activity time stamp */
            $_SESSION[self::SESSION_KEY_LAST_ACTIVITY] = time();
        }

    }

    public function run()
    {
        $slim = new \Slim\App($this->getSlimSettings());
        $slimContainer = $slim->getContainer();

        $this->setSlimDependences($slimContainer, $this->systemEventsMapper, $this->mailer);

        $this->removeSlimErrorHandler($slimContainer);

        $this->setSlimMiddleware($slim, $slimContainer);

        $this->registerSlimRoutes($slim, $slimContainer);

        $slim->run();
    }

    private function getSlimSettings(): array
    {
        $slimSettings['settings'] = $this->config['slim'];

        /** add common config settings */
        foreach ($this->commonConfigSettingsKeys as $key) {
            if (isset($this->config[$key])) {
                $slimSettings['settings'][$key] = $this->config[$key];
            }
        }

        /** Override the default Not Found Handler */
        $slimSettings['notFoundHandler'] = function ($container) {
            return function ($request, $response) use ($container) {

                /** log error */
                $this->systemEventsMapper->insertEvent('404 Page Not Found', 'notice', $container->authentication->getAdministratorId());

                $_SESSION[App::SESSION_KEY_NOTICE] = [$this->config['pageNotFoundText'], App::STATUS_NOTICE_FAILURE];
                return $container->view->render(
                    $response,
                    'frontend/home.php',
                    [])->withStatus(404);
            };
        };

        return $slimSettings;
    }

    private function setSlimDependences($container, SystemEventsMapper $systemEventsMapper, Utilities\PhpMailerService $mailer)
    {
        /** Template */
        $container['view'] = function ($container) {
            $settings = $container->get('settings');
            $templateVariables = [
                'businessName' => $settings['businessName'],
                'domainName' => $settings['domainName'],
                'businessDba' => $settings['businessDba'],
                'isLive' => $settings['isLive'],
                'authentication' => $container->authentication,
                'authorization' => $container->authorization,
                'router' => $container->router,
                'csrfNameKey' => $container->csrf->getTokenNameKey(),
                'csrfName' => $container->csrf->getTokenName(),
                'csrfValueKey' => $container->csrf->getTokenValueKey(),
                'csrfValue' => $container->csrf->getTokenValue(),
            ];
            return new \Slim\Views\PhpRenderer($settings['templatesPath'], $templateVariables);
        };

        /** Authentication */
        $container['authentication'] = function($container) {
            $settings = $container->get('settings');
            return new AuthenticationService($settings['authentication']['maxFailedLogins'], $settings['authentication']['administratorHomeRoutes']);
        };

        /** Authorization */
        $container['authorization'] = function($container) {
            $settings = $container->get('settings');
            return new AuthorizationService();
        };

        /** System Events (Database Log) */
        $container['systemEvents'] = function($container) use ($systemEventsMapper) {
            return $systemEventsMapper;
        };

        /** Mailer */
        $container['mailer'] = function($container) use ($mailer) {
            return $mailer;
        };

        /** CSRF */
        $container['csrf'] = function ($container) {
            /** cannot directly pass null because received by reference */
            $storage = null; 

            /** 
             * setting the persistentTokenMode parameter true allows redisplaying a form with errors with a render rather than redirect call and will not cause CSRF failure if the page is refreshed 
             * (http://blog.ircmaxell.com/2013/02/preventing-csrf-attacks.html)
             */
            $guard = new \Slim\Csrf\Guard('csrf', $storage, null, 200, 16, true);
            $guard->setFailureCallable(function ($request, $response, $next) {
                $request = $request->withAttribute("csrf_status", false);
                return $next($request, $response);
            });
            return $guard;
        };
    }

    private function removeSlimErrorHandler($slimContainer)
    {
        unset($slimContainer['errorHandler']);
        unset($slimContainer['phpErrorHandler']);
    }

    /** Global middleware registration */
    private function setSlimMiddleware(\Slim\App $slim, $slimContainer)
    {
        /** handle CSRF check failures and allow template to access and insert CSRF fields to forms */
        $slim->add(new CsrfMiddleware($slimContainer));

        /** slim CSRF check middleware */
        $slim->add($slimContainer->csrf);
    }

    /** note need the arguments for routes.php to access */
    private function registerSlimRoutes(\Slim\App $slim, $slimContainer)
    {
        /** make available to routes file */
        $config = $this->config; 
        require APPLICATION_ROOT_DIRECTORY . '/config/routes.php';
    }

    /** if called with no args, redirects to current URI with proper protocol, www or not based on config, and query string */
    private function redirect(string $toURI = null)
    {
        header("Location: ".$this->getRedirect($toURI));
        exit();
    }

    private function getRedirect(string $toURI = null): ?string
    {
        if (is_null($toURI)) {
            if (Utilities\Functions::isRunningFromCommandLine()) {
                return null;
            }
            $toURI = $this->getCurrentUri(true);
        }

        /** add initial '/' if nec */
        if (substr($toURI, 0, 1) != "/") {
            $toURI = "/" . $toURI;
        }

        return $this->getBaseUrl() . $toURI;
    }

    private function getCurrentUri(bool $includeQueryString = true): string
    {
        $uri = $_SERVER['REQUEST_URI'];
        if ($includeQueryString && isset($_SERVER['QUERY_STRING']) && !empty($_SERVER['QUERY_STRING'])) {
            $uri .= "?" . $_SERVER['QUERY_STRING'];
        }

        return $uri;
    }

    private function getBaseUrl()
    {
        global $config;
        $baseUrl = "https://";
        if ($config['domainUseWww']) {
            $baseUrl .= "www.";
        }
        $baseUrl .= $this->getHostWithoutWww();
        return $baseUrl;
    }

    private function getHostWithoutWww(): string
    {
        if (substr($_SERVER['HTTP_HOST'], 0, 4) == 'www.') {
            return substr($_SERVER['HTTP_HOST'], 4);
        }
        return $_SERVER['HTTP_HOST'];
    }

    /**
     * determines if current page is https
     * @return bool
     */
    private function isHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || intval($_SERVER['SERVER_PORT']) === 443;
    }

    /**
     * determines if url host name begins with 'www'
     * @return bool
     */
    private function isWww(): bool
    {
        return (strtolower(substr($_SERVER['SERVER_NAME'], 0, 3)) == 'www');
    }

    /**
     * determines if a $sessionId id is valid.
     * @param $session_id
     * @param bool optional $isEmptyIdValid
     * @return bool
     */
    private function isSessionIdValid(string $sessionId, $isEmptyIdValid = true): bool
    {
        if ($isEmptyIdValid && mb_strlen($sessionId) == 0) { // if blank, there is no session id
            return true;
        }
        return preg_match('/^[-,a-zA-Z0-9]{1,128}$/', $sessionId) > 0;
    }

    /** because of route naming conventions, only send resourceType of post, put, or patch */
    public static function getRouteName(bool $isAdmin = true, string $routePrefix = null, string $routeType = null, string $requestMethod = null): string
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

    public static function setAdminNotice(string $text, string $status = 'success') 
    {
        $statusValues = ['success', 'failure', 'caution', 'muted'];
        if (!in_array($status, $statusValues)) {
            throw new \InvalidArgumentException("Invalid admin notice status $status");
        }

        $_SESSION[self::SESSION_KEY_ADMIN_NOTICE] = ["$text", 'adminNotice'.ucfirst($status)];
    }
}
