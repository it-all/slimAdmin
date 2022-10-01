<?php
declare(strict_types=1);

namespace Infrastructure\BaseEntity\BaseMVC\Controller;

use Psr\Container\ContainerInterface as Container;
use Psr\Http\Message\ServerRequestInterface as Request;

abstract class BaseController
{
    protected $container; // dependency injection container
    protected $requestInput; // user input data

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function __get($name)
    {
        return $this->container->get($name);
    }

    protected function setRequestInput(Request $request, array $fieldNames)
    {
        $this->requestInput = [];

        foreach ($fieldNames as $fieldName) {
            /** note, array fields with no checked options will be set null */
            $this->requestInput[$fieldName] = $request->getParsedBody()[$fieldName] ?? null;

            /** trim strings if necessary depending on config */
            if (is_string($this->requestInput[$fieldName]) && $this->settings['trimAllUserInput']) {
                $this->requestInput[$fieldName] = trim($this->requestInput[$fieldName]);
            }
        }
    }

    /** 
     * @param string $emailTo must be in $settings['emails'] array or error will be inserted to events
     * @param string $mainBody
     * @param bool $addEventLogStatement defaults true, if true adds 'See event log for details' after $mainBody
     * @param bool $throwExceptionOnError defaults false, if true exception is thrown if no match for $emailTo
     */
    protected function sendEventNotificationEmail(string $emailTo, string $mainBody, ?string $subjectEnd = '', ?bool $addEventLogStatement = true, ?bool $throwExceptionOnError = false)
    {
        if ($this->mailer !== null && $emailTo !== null) {
            $settings = $this->container->get('settings');
            if (isset($settings['emails'][$emailTo])) {
                $toArray = is_array($settings['emails'][$emailTo]) ? $settings['emails'][$emailTo] : [$settings['emails'][$emailTo]];
                $emailBody = $mainBody;
                if ($addEventLogStatement) {
                    $emailBody .= PHP_EOL . "See event log for details.";
                }
                $this->mailer->send(
                    $_SERVER['SERVER_NAME'] . " Event $subjectEnd",
                    $emailBody,
                    $toArray
                );
            } else {
                $this->events->insertError(EVENT_EMAIL_NOT_FOUND, ['email' => $emailTo]);
                if ($throwExceptionOnError) {
                    throw new \InvalidArgumentException("Email Not Found: $emailTo");
                }
            }
        }
    }
}
