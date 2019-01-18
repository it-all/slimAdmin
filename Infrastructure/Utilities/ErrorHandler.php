<?php
declare(strict_types=1);

namespace Infrastructure\Utilities;

use Infrastructure\SlimPostgres;
use Infrastructure\Functions;
use Infrastructure\Utilities;
use Entities\Events\EventsTableMapper;

class ErrorHandler
{
    private $logPath;
    private $redirectPage;
    private $emailErrors;
    private $echoErrors;
    private $mailer;
    private $emailTo;
    private $eventsTableMapper;
    private $fatalMessage;

    public function __construct(
        string $logPath,
        ?string $redirectPage,
        bool $echoErrors = false,
        bool $emailErrors = true,
        array $emailTo = [],
        ?PhpMailerService $mailer = null,
        ?string $fatalMessage = null
    )
    {
        if ($emailErrors) {
            if ($mailer === null) {
                throw new \InvalidArgumentException("mailer not set");
            }
            if (count($emailTo) == 0) {
                throw new \InvalidArgumentException("emailTo not set");
            }
        }
        
        $this->logPath = $logPath;
        $this->redirectPage = $redirectPage;
        $this->emailErrors = $emailErrors;
        $this->echoErrors = $echoErrors;
        $this->mailer = $mailer;
        $this->emailTo = $emailTo;
        $this->fatalMessage = $fatalMessage;
    }

    public function setEventsTableMapper(EventsTableMapper $eventsTableMapper)
    {
        $this->eventsTableMapper = $eventsTableMapper;
    }

    /*
     * 4 ways to handle:
     * -database - always (as long as database and eventsTableMapper properties have been set)
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

        // log to file. suppress errors to prevent infinite loop.
        @error_log($errorMessage, 3, $this->logPath);

        // echo
        if ($this->echoErrors) {
            echo (Functions::isRunningFromCommandLine()) ? $errorMessage : nl2br($errorMessage, false);
        }

        // log to database
        if (isset($this->eventsTableMapper)) {
            /** determine event type */
            switch ($this->getErrorType($errno)) {
                case 'Core Error':
                case 'Parse Error':
                case 'Fatal Error':
                    $eventType = 'critical';
                    break;
                case 'Core Warning':
                case 'Warning':
                    $eventType = 'warning';
                    break;
                case 'Deprecated':
                case 'Notice':
                    $eventType = 'notice';
                    break;
                default:
                    $eventType = 'error';
            }

            $databaseErrorMessage = explode('Stack Trace:', $errorMessage)[0].'...See PHP error log for further details.';

            /** set events administrator id if logged in */
            if ((isset($_SESSION[SlimPostgres::SESSION_KEY_ADMINISTRATOR_ID]))) {
                $this->eventsTableMapper->setAdministratorId((int) $_SESSION[SlimPostgres::SESSION_KEY_ADMINISTRATOR_ID]);
            }

            // suppress errors to prevent infinite loop.
            @$this->eventsTableMapper->insertEvent('PHP Error', $eventType, ['error' => $databaseErrorMessage]);
        }

        // email
        if ($this->emailErrors) {
            @$this->mailer->send($_SERVER['SERVER_NAME'] . " Error", "Check log file for details.", $this->emailTo);
        }

        if ($die) {
            if ($this->redirectPage != null) {
                if (!is_null($this->fatalMessage)) {
                    $_SESSION[SlimPostgres::SESSION_KEY_NOTICE] = [$this->fatalMessage, 'error'];
                }
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
            $message = $this->generateMessageBodyCommon($error["type"], $error["message"], $error["file"], $error["line"]);
            $this->handleError($message, $error["type"], true);
        }
    }

    /** @param \Throwable $e
     * catches both Errors and Exceptions
     * create error message and send to handleError
     */
    public function throwableHandler(\Throwable $e)
    {
        $message = $this->generateMessageBodyCommon($e->getCode(), $e->getMessage(), $e->getFile(), $e->getLine());
        $message .= PHP_EOL . "Stack Trace:" . PHP_EOL . $e->getTraceAsString();

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
        $message = $this->generateMessageBodyCommon($errno, $errstr, $errfile, $errline) . PHP_EOL . "Stack Trace:". PHP_EOL . $this->getDebugBacktraceString();

        $this->handleError($message, $errno, false);
    }

    private function generateMessage(string $messageBody): string
    {
        $message = "[".date('Y-m-d H:i:s e')."] ";

        if (Functions::isRunningFromCommandLine()) {
            global $argv;
            $message .= gethostname() . PHP_EOL . "Command line: " . $argv[0];
        } else {
            $message .= $_SERVER['SERVER_NAME'] . PHP_EOL . "Web Page: " . $_SERVER['REQUEST_METHOD'] . " " . $_SERVER['REQUEST_URI'];
            if (mb_strlen($_SERVER['QUERY_STRING']) > 0) {
                $message .= "?" . $_SERVER['QUERY_STRING'];
            }
        }
        $message .= PHP_EOL . "$messageBody" . PHP_EOL . PHP_EOL;
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
        $message .= htmlspecialchars_decode($errstr) . PHP_EOL;

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
        // also, since the $e->getTraceAsString method shows the full file path, for consistency best to show it here
        $showVendorCalls = true;
        $showFullFilePath = true;
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
                $outLine .= " {".Functions::arrayWalkToStringRecursive($call['args'], 0, 1000, PHP_EOL)."}";
            }
            $out .= "$outLine" . PHP_EOL;
        }

        return $out;
    }
}
