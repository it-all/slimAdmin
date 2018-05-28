<?php
declare(strict_types=1);

namespace It_All\Slim_Postgres\Infrastructure\Utilities;

use It_All\Slim_Postgres\Infrastructure\Database\Postgres;
use It_All\Slim_Postgres\Infrastructure\Framework\Initialize;
use It_All\Slim_Postgres\Infrastructure\System_Events\SystemEventsModel;

class ErrorHandler
{
    private $logPath;
    private $sessionKeys;
    private $redirectPage;
    private $emailErrors;
    private $echoErrors;
    private $mailer;
    private $emailTo;
    private $database;
    private $systemEventsModel;
    private $fatalMessage;

    public function __construct(
        string $logPath,
        array $sessionKeys = [],
        ?string $redirectPage,
        bool $echoErrors = false,
        bool $emailErrors = true,
        array $emailTo = [],
        PhpMailerService $m = null,
        $fatalMessage = 'Apologies, there has been an error on our site. We have been alerted and will correct it as soon as possible.'
    )
    {
        $this->logPath = $logPath;
        $this->sessionKeys = $sessionKeys;
        $this->redirectPage = $redirectPage;
        $this->emailErrors = $emailErrors;
        $this->echoErrors = $echoErrors;
        $this->mailer = $m;
        $this->emailTo = $emailTo;
        $this->fatalMessage = $fatalMessage;
    }

    public function setDatabaseAndSystemEventsModel(Postgres $database, SystemEventsModel $systemEventsModel)
    {
        $this->setDatabase($database);
        $this->setSystemEventsModel($systemEventsModel);
    }

    public function setDatabase(Postgres $database)
    {
        $this->database = $database;
    }

    public function setSystemEventsModel(SystemEventsModel $systemEventsModel)
    {
        $this->systemEventsModel = $systemEventsModel;
    }

    /*
     * 4 ways to handle:
     * -database - always (as long as database and systemEventsModel properties have been set)
     * -log - always
     * -echo - depends on property
     * -email - depends on property. never email error deets.
     * use @ when calling fns from here to avoid infinite loop
     * die if necessary
     */
    private function handleError(string $messageBody, int $errno, bool $die = false)
    {
        // happens when an expression is prefixed with @ (meaning: ignore errors).
        if (error_reporting() == 0) {
            return;
        }
        $errorMessage = $this->generateMessage($messageBody);

        // database
        if (isset($this->database) && isset($this->systemEventsModel)) {
            switch ($this->getErrorType($errno)) {
                case 'Core Error':
                case 'Parse Error':
                case 'Fatal Error':
                    $systemEventType = 'critical';
                    break;
                case 'Core Warning':
                case 'Warning':
                    $systemEventType = 'warning';
                    break;
                case 'Deprecated':
                case 'Notice':
                    $systemEventType = 'notice';
                    break;
                default:
                    $systemEventType = 'error';
            }

            $databaseErrorMessage = explode('Stack Trace:', $errorMessage)[0].'.See phpErrors.log for further details.';

            $adminId = (array_key_exists('user', $this->sessionKeys) && array_key_exists('userId', $this->sessionKeys) && isset($_SESSION[$this->sessionKeys['user']][$this->sessionKeys['userId']])) ? (int) $_SESSION[$this->sessionKeys['user']][$this->sessionKeys['userId']] : null;

            @$this->systemEventsModel->insertEvent('PHP Error', $systemEventType, $adminId, $databaseErrorMessage);
        }

        // log
        @error_log($errorMessage, 3, $this->logPath);

        // echo
        if ($this->echoErrors) {
            echo nl2br($errorMessage, false);
            if ($die) {
                die();
            }
        }

        // email
        if ($this->emailErrors) {
            @$this->mailer->send($_SERVER['SERVER_NAME'] . " Error", "Check log file for details.", $this->emailTo);
        }

        // will only get here if errors have not been echoed above
        if ($die) {
            if ($this->redirectPage != null) {
                $_SESSION[SESSION_NOTICE] = [$this->fatalMessage, 'error'];
                header("Location: $this->redirectPage");
            }
            exit();
        }
    }

    /**
     * used in register_shutdown_function to see if a fatal error has occurred and handle it.
     * note, this does not occur often in php7, as almost all errors are now exceptions and will be caught by the registered exception handler. fatal errors can still occur for conditions like out of memory: https://trowski.com/2015/06/24/throwable-exceptions-and-errors-in-php7/
     * see also https://stackoverflow.com/questions/10331084/error-logging-in-a-smooth-way
     */
    public function shutdownFunction()
    {
        $error = error_get_last(); // note, stack trace is included in $error["message"]

        if (!isset($error)) {
            return;
        }

        $fatalErrorTypes = [E_USER_ERROR, E_ERROR, E_PARSE, E_CORE_ERROR, E_CORE_WARNING, E_COMPILE_ERROR, E_COMPILE_WARNING];
        if (in_array($error["type"], $fatalErrorTypes)) {
            $this->handleError($this->generateMessageBodyCommon($error["type"], $error["message"], $error["file"], $error["line"]),$error["type"], true);
        }
    }

    /** @param \Throwable $e
     * catches both Errors and Exceptions
     * create error message and send to handleError
     */
    public function throwableHandler(\Throwable $e)
    {
        $message = $this->generateMessageBodyCommon($e->getCode(), $e->getMessage(), $e->getFile(), $e->getLine());
        $message .= "\nStack Trace:\n" . $e->getTraceAsString();
        $exitPage = ($e->getCode() == E_ERROR || $e->getCode() == E_USER_ERROR) ? true : false;

        $this->handleError($message, $e->getCode(), $exitPage);
    }

    /**
     * @param int $errno
     * @param string $errstr
     * @param string|null $errfile
     * @param string|null $errline
     * to be registered with php's set_error_handler()
     * called for script errors and trigger_error()
     */
    public function phpErrorHandler(int $errno, string $errstr, string $errfile = null, string $errline = null)
    {
        $message = $this->generateMessageBodyCommon($errno, $errstr, $errfile, $errline) . "\nStack Trace:\n". $this->getDebugBacktraceString();

        $this->handleError($message, $errno, false);
    }

    private function generateMessage(string $messageBody): string
    {
        $message = "[".date('Y-m-d H:i:s e')."] ";

        $message .= Initialize::isRunningFromCommandLine() ? gethostname() : $_SERVER['SERVER_NAME'];

        if (Initialize::isRunningFromCommandLine()) {
            global $argv;
            $message .= "Command line: " . $argv[0];
        } else {
            $message .= "\nWeb Page: " . $_SERVER['REQUEST_METHOD'] . " " . $_SERVER['REQUEST_URI'];
            if (strlen($_SERVER['QUERY_STRING']) > 0) {
                $message .= "?" . $_SERVER['QUERY_STRING'];
            }
        }
        $message .= "\n$messageBody\n\n";
        return $message;
    }

    /**
     * @param int $errno
     * @param string $errstr
     * @param string|null $errfile
     * @param null $errline
     * @return string
     * errline seems to be passed in as a string or int depending on where it's coming from
     */
    private function generateMessageBodyCommon(int $errno, string $errstr, string $errfile = null, $errline = null): string
    {
        $message = $this->getErrorType($errno).": ";
        $message .= htmlspecialchars_decode($errstr)."\n";

        if (!is_null($errfile)) {
            $message .= "$errfile";
            // note it only makes sense to have line if we have file
            if (!is_null($errline)) {
                $message .= " line: $errline";
            }
        }

        return $message;
    }

    private function getErrorType($errno)
    {
        switch ($errno) {
            case E_ERROR:
            case E_USER_ERROR:
                return 'Fatal Error';
            case E_WARNING:
            case E_USER_WARNING:
                return 'Warning';
            case E_NOTICE:
            case E_USER_NOTICE:
                return 'Notice';
            case E_DEPRECATED:
            case E_USER_DEPRECATED:
                return 'Deprecated';
            case E_PARSE:
                return 'Parse Error';
            case E_CORE_ERROR:
                return 'Core Error';
            case E_CORE_WARNING:
                return 'Core Warning';
            case E_COMPILE_ERROR:
                return 'Compile Error';
            case E_COMPILE_WARNING:
                return 'Compile Warning';
            case E_STRICT:
                return 'Strict';
            case E_RECOVERABLE_ERROR:
                return 'Recoverable Error';
            default:
                return 'Unknown error type';
        }
    }

    // note, formats of various stack traces will differ, as this is not the only way a stack trace is generated. php automatically generates one for fatal errors that are caught in the shutdown function, and throwable exceptions generate one through their getTraceAsString method.
    private function getDebugBacktraceString(): string
    {
        $out = "";

        $dbt = debug_backtrace(~DEBUG_BACKTRACE_PROVIDE_OBJECT & ~DEBUG_BACKTRACE_IGNORE_ARGS);

        // skip the first 2 entries, because they're from this file
        array_shift($dbt);
        array_shift($dbt);

        // these could be in $config, but with the various format note above, that could lead to confusion.
        $showVendorCalls = true;
        $showFullFilePath = false;
        $startFilePath = '/Src'; // only applies if $showFullFilePath is false
        $showClassNamespace = false;

        foreach ($dbt as $index => $call) {
            $outLine = "#$index:";
            if (isset($call['file'])) {
                if (!$showVendorCalls && strstr($call['file'], '/vendor/')) {
                    break;
                }
                $outLine .= " ";
                if ($showFullFilePath) {
                    $outLine .= $call['file'];
                } else {
                    $fileParts = explode($startFilePath, $call['file']);
                    $outLine .= (isset($fileParts[1])) ? $fileParts[1] : $call['file'];
                }
            }
            if (isset($call['line'])) {
                $outLine .= " [".$call['line']."] ";
            }
            if (isset($call['class'])) {
                $classParts = explode("\\", $call['class']);
                $outLine .= " ";
                $outLine .= ($showClassNamespace) ? $call['class'] : $classParts[count($classParts) - 1];
            }
            if (isset($call['type'])) {
                $outLine .= $call['type'];
            }
            if (isset($call['function'])) {
                $outLine .= $call['function']."()";
            }
            if (isset($call['args'])) {
                $outLine .= " {".Initialize::arrayWalkToStringRecursive($call['args'])."}";
            }
            $out .= "$outLine\n\n";
        }

        return $out;
    }
}
