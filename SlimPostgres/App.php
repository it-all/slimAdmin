<?php
declare(strict_types=1);
/** note: this file can also be called for cli scripts.*/

namespace SlimPostgres;

use Dotenv\Dotenv;
use SlimPostgres\Database\Postgres;
use SlimPostgres\Security\Authentication\AuthenticationService;
use SlimPostgres\Security\Authorization\AuthorizationService;
use SlimPostgres\Security\CsrfMiddleware;
use SlimPostgres\SystemEvents\SystemEventsModel;
use SlimPostgres\Utilities;

class App
{
    private $config;
    /** @var array config settings that get added to slim so framework can access through container */
    private $commonConfigSettingsKeys;
    private $environmentalVariables;
    private $database;
    private $systemEventsModel;
    private $mailer;

    const PATH_PHP_ERRORS_LOG = APPLICATION_ROOT_DIRECTORY . '/storage/logs/phpErrors.log';
    const SESSION_KEYS = [
        'lastActivity' => 'lastActivity',
        'user' => 'user',
        'userId' => 'id',
        'userName' => 'name',
        'userUsername' => 'username',
        'userRole' => 'role',
        'adminNotice' => 'adminNotice',
        'notice' => 'notice',
        'gotoAdminPath' => 'gotoAdminPath'
    ];

    public function __construct()
    {
        $this->commonConfigSettingsKeys = ['isLive', 'businessName', 'businessDba'];
        $dotenv = new Dotenv(APPLICATION_ROOT_DIRECTORY);
        $dotenv->load();

        $this->environmentalVariables = getenv();

        // validate .env (note, not thorough validation)
        $dotenv->required('PHPMAILER_PROTOCOL')->allowedValues(['smtp', 'sendmail', 'mail', 'qmail']);
        $phpMailerProtocol = $this->environmentalVariables['PHPMAILER_PROTOCOL'];
        if ($phpMailerProtocol == 'smtp') {
            $dotenv->required('PHPMAILER_SMTP_HOST');
            $dotenv->required('PHPMAILER_SMTP_PORT')->isInteger();
        }

        $this->config = require APPLICATION_ROOT_DIRECTORY . '/config/settings.php';

        // add some .env to config
        $this->config['isLive'] = !in_array(strtolower($this->environmentalVariables['IS_LIVE']), [false, 0, 'false', '0', 'off', 'no']); // bool

        // set up emailer, which is used in error handler and container
        $phpMailerSmtpHost = (array_key_exists('PHPMAILER_SMTP_HOST', $this->environmentalVariables)) ? $this->environmentalVariables['PHPMAILER_SMTP_HOST'] : null;
        $phpMailerSmtpPort = (array_key_exists('PHPMAILER_SMTP_PORT', $this->environmentalVariables)) ? (int) $this->environmentalVariables['PHPMAILER_SMTP_PORT'] : null;
        $disableMailerSend = !$this->config['isLive'] && !$this->config['errors']['emailDev'];
        $this->mailer = new Utilities\PhpMailerService(
            self::PATH_PHP_ERRORS_LOG,
            $this->config['emails']['service'],
            $this->config['businessName'],
            $phpMailerProtocol,
            $phpMailerSmtpHost,
            $phpMailerSmtpPort,
            $disableMailerSend
        );

        // error handling
        $echoErrors = !$this->config['isLive'];
        $emailErrors = $this->config['isLive'] || $this->config['errors']['emailDev'];
        $emailErrorsTo = [];
        foreach ($this->config['errors']['emailTo'] as $roleEmail) {
            $emailErrorsTo[] = $this->config['emails'][$roleEmail];
        }

        $errorHandler = new Utilities\ErrorHandler(
            self::PATH_PHP_ERRORS_LOG,
            self::SESSION_KEYS,
            $this->getRedirect(),
            $echoErrors,
            $emailErrors,
            $emailErrorsTo,
            $this->mailer
        );

        // workaround for catching some fatal errors like parse errors. note that parse errors in this file and index.php are not handled, but cause a fatal error with display (not displayed if display_errors is off in php.ini, but the ini_set call will not affect it).
        register_shutdown_function(array($errorHandler, 'shutdownFunction'));
        set_error_handler(array($errorHandler, 'phpErrorHandler'));
        set_exception_handler(array($errorHandler, 'throwableHandler'));

        error_reporting( -1 ); // all, including future types
        ini_set( 'display_errors', 'off' );
        ini_set( 'display_startup_errors', 'off' );

        // any errors prior to this point will not be logged
        ini_set('error_log', self::PATH_PHP_ERRORS_LOG); // even though the error handler logs errors, this ensures errors in the error handler itself or in this file after this point will be logged. note, if using slim error handling, this will log all php errors

        // set up and connect to postgres, which is used in error handler and container
        // this is done after setting error handler in case connection fails
        // note, injected to error handler below
        $postgresConnectionString = (array_key_exists('POSTGRES_CONNECTION_STRING', $this->environmentalVariables)) ? $this->environmentalVariables['POSTGRES_CONNECTION_STRING'] : '';
        $this->database = new Postgres($postgresConnectionString);

        // used in error handler and container
        $this->systemEventsModel = new SystemEventsModel();

        if ($this->config['errors']['logToDatabase']) {
            $errorHandler->setDatabaseAndSystemEventsModel($this->database, $this->systemEventsModel);
        }

        if (!$this->isRunningFromCommandLine()) {
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
            if (!$this->sessionValidId(session_id())) {
                session_regenerate_id(true);
            }
            if (isset($this->config['session']['savePath']) && strlen($this->config['session']['savePath']) > 0) {
                session_save_path($this->config['session']['savePath']);
            }
            session_start();
            $_SESSION[self::SESSION_KEYS['lastActivity']] = time(); // update last activity time stamp
        }

    }

    public function run()
    {
        $slim = new \Slim\App($this->getSlimSettings());
        $slimContainer = $slim->getContainer();

        $this->setSlimDependences($slimContainer, $this->database, $this->systemEventsModel, $this->mailer);

        $this->removeSlimErrorHandler($slimContainer);

        $this->setSlimMiddleware($slim, $slimContainer);

        $this->registerRoutes($slim, $slimContainer);

        $slim->run();
    }

    private function registerRoutes(\Slim\App $slim, $slimContainer)
    {
        $config = $this->config; // make available to routes file
        require APPLICATION_ROOT_DIRECTORY . '/config/routes.php';
    }

    private function getSlimSettings(): array
    {
        $slimSettings = $this->config['slim'];
        // add common settings

        foreach ($this->commonConfigSettingsKeys as $key) {
            if (isset($this->config[$key])) {
                $slimSettings['settings'][$key] = $this->config[$key];
            }
        }

        //Override the default Not Found Handler
        $slimSettings['notFoundHandler'] = function ($container) {
            return function ($request, $response) use ($container) {
                // log error
                // todo get adminId
                $adminId = null; // (array_key_exists('user', $this->sessionKeys) && array_key_exists('userId', $this->sessionKeys) && isset($_SESSION[$this->sessionKeys['user']][$this->sessionKeys['userId']])) ? (int) $_SESSION[$this->sessionKeys['user']][$this->sessionKeys['userId']] : null;

                $this->systemEventsModel->insertEvent('404 Page Not Found', 'notice', $adminId);

                $homeUrl = $container->router->pathFor(ROUTE_HOME);
                $responseBodyHtml = $this->config['pageNotFoundText'].'<br><br><a href="'.$homeUrl.'">home</a>';

                return $container['response']
                    ->withStatus(404)
                    ->withHeader('Content-Type', 'text/html')
                    ->write($responseBodyHtml);

            };
        };

        return $slimSettings;
    }

    private function removeSlimErrorHandler($slimContainer)
    {
        unset($slimContainer['errorHandler']);
        unset($slimContainer['phpErrorHandler']);
    }

    /** Global middleware registration */
    private function setSlimMiddleware(\Slim\App $slim, $slimContainer)
    {
        // handle CSRF check failures and allow template to access and insert CSRF fields to forms
        $slim->add(new CsrfMiddleware($slimContainer));
        // slim CSRF check middleware
        $slim->add($slimContainer->csrf);
    }

    private function setSlimDependences($container, Postgres $database, SystemEventsModel $systemEventsModel, Utilities\PhpMailerService $mailer)
    {
        // Template
        $container['view'] = function ($container) {
            $settings = $container->get('settings');
            return new \Slim\Views\PhpRenderer($settings['templatesPath']);
        };

        // Database
        $container['database'] = function($container) use ($database) {
            return $database;
        };

        // Authentication
        $container['authentication'] = function($container) {
            $settings = $container->get('settings');
            return new AuthenticationService($settings['authentication']['maxFailedLogins'], $settings['authentication']['adminHomeRoutes']);
        };

        // Authorization
        $container['authorization'] = function($container) {
            $settings = $container->get('settings');
            return new AuthorizationService($settings['authorization'], $settings['adminDefaultRole']);
        };

        // System Events (Database Log)
        $container['systemEvents'] = function($container) use ($systemEventsModel) {
            return $systemEventsModel;
        };

        // Mailer
        $container['mailer'] = function($container) use ($mailer) {
            return $mailer;
        };

        // Form Validation
        $container['validator'] = function ($container) {
            return new Utilities\ValitronValidatorExtension();
        };

        // CSRF
        $container['csrf'] = function ($container) {
            $storage = null; // cannot directly pass null because received by reference.
            // setting the persistentTokenMode parameter true allows redisplaying a form with errors with a render rather than redirect call and will not cause CSRF failure if the page is refreshed (http://blog.ircmaxell.com/2013/02/preventing-csrf-attacks.html)
            $guard = new \Slim\Csrf\Guard('csrf', $storage, null, 200, 16, true);
            $guard->setFailureCallable(function ($request, $response, $next) {
                $request = $request->withAttribute("csrf_status", false);
                return $next($request, $response);
            });
            return $guard;
        };
    }

    // if called with no args, redirects to current URI with proper protocol, www or not based on config, and query string
    public function redirect(string $toURI = null)
    {
        header("Location: ".$this->getRedirect($toURI));
        exit();
    }

    public function getRedirect(string $toURI = null): ?string
    {
        if (is_null($toURI)) {
            if ($this->isRunningFromCommandLine()) {
                return null;
            }
            $toURI = $this->getCurrentUri(true);
        }

        // add initial '/' if nec
        if (substr($toURI, 0, 1) != "/") {
            $toURI = "/" . $toURI;
        }

        return $this->getBaseUrl() . $toURI;
    }

    /**
     * Returns true if the current script is running from the command line (ie, CLI).
     */
    static public function isRunningFromCommandLine(): bool
    {
        return php_sapi_name() == 'cli';
    }

    /**
     * converts array to string
     * @param array $arr
     * @param int $level
     * @return string
     */
    static public function arrayWalkToStringRecursive(array $arr, int $level = 0, int $maxLevel = 1000): string
    {
        $out = "";
        $tabs = " ";
        for ($i = 0; $i < $level; $i++) {
            $tabs .= " ^"; // use ^ to denote another level
        }
        foreach ($arr as $k => $v) {
            $out .= "<br>$tabs$k: ";
            if (is_object($v)) {
                $out .= 'object type: '.get_class($v);
            } elseif (is_array($v)) {
                $newLevel = $level + 1;
                if ($newLevel > $maxLevel) {
                    $out .= ' array, too deep, quitting';
                } else {
                    $out .= self::arrayWalkToStringRecursive($v, $newLevel);
                }
            } else {
                $out .= (string)$v;
            }
        }
        return $out;
    }

    public function getCurrentUri(bool $includeQueryString = true): string
    {
        $uri = $_SERVER['REQUEST_URI'];
        if ($includeQueryString && isset($_SERVER['QUERY_STRING']) && !empty($_SERVER['QUERY_STRING'])) {
            $uri .= "?" . $_SERVER['QUERY_STRING'];
        }

        return $uri;
    }

    public function getBaseUrl()
    {
        global $config;
        $baseUrl = "https://";
        if ($config['domainUseWww']) {
            $baseUrl .= "www.";
        }
        $baseUrl .= $this->getHostWithoutWww();
        return $baseUrl;
    }

    public function getHostWithoutWww(): string
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
    public function isHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || intval($_SERVER['SERVER_PORT']) === 443;
    }

    /**
     * determines if url host name begins with 'www'
     * @return bool
     */
    public function isWww(): bool
    {
        return (strtolower(substr($_SERVER['SERVER_NAME'], 0, 3)) == 'www');
    }

    /**
     * determines if a $sessionId id is valid.
     * @param $session_id
     * @param bool optional $isEmptyIdValid
     * @return bool
     */
    public function sessionValidId(string $sessionId, $isEmptyIdValid = true): bool
    {
        if ($isEmptyIdValid && strlen($sessionId) == 0) { // if blank, there is no session id
            return true;
        }
        return preg_match('/^[-,a-zA-Z0-9]{1,128}$/', $sessionId) > 0;
    }

}
