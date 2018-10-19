<?php
declare(strict_types=1);

namespace Infrastructure\Utilities;

/**
 * Service Layer for PHPMailer
 */
class PhpMailerService {

    private $logPath;
    private $defaultFromEmail;
    private $defaultFromName;
    private $protocol;
    private $smtpHost;
    private $smtpPort;
    private $phpMailer;

    /** @var bool this can allow disabling service entirely, ie for a dev site */
    private $disableSend;

    public function __construct(string $logPath, string $defaultFromEmail, string $defaultFromName, string $protocol = 'smtp', string $smtpHost = null, int $smtpPort = null, bool $disableSend = false)
    {
        $this->logPath = $logPath;
        $this->defaultFromEmail = $defaultFromEmail;
        $this->defaultFromName = $defaultFromName;
        $this->protocol = strtolower($protocol);
        $this->smtpHost = $smtpHost;
        $this->smtpPort = $smtpPort;
        $this->phpMailer = $this->create();
        $this->disableSend = $disableSend;
    }

    public function send(string $subject, string $body, array $toEmails, string $fromEmail = null, string $fromName = null)
    {
        if ($this->disableSend) {
            return;
        }

        $toEmails = array_unique($toEmails);
        $this->phpMailer->Subject = $subject;
        $this->phpMailer->Body = $body;
        $toEmailsString = '';
        foreach ($toEmails as $email) {
            $toEmailsString .= "$email ";
            $this->phpMailer->addAddress(strtolower($email));
        }
        if ($fromEmail == null) {
            $fromEmail = $this->defaultFromEmail;
        }
        if ($fromName == null) {
            $fromName = $this->defaultFromName;
        }
        $this->phpMailer->setFrom($fromEmail, $fromName);
        if (!$this->phpMailer->send()) {
            // note do not throw exception here because could get stuck in loop trying to email
            $errorMessage = "[".date('Y-m-d H:i:s e')."]" . PHP_EOL .
                "PhpMailer::send() failed: ".$this->phpMailer->ErrorInfo . PHP_EOL .
                "subject: $subject" . PHP_EOL .
                "body: $body" . PHP_EOL .
                "to: $toEmailsString" . PHP_EOL .
                "from: $fromEmail" . PHP_EOL . PHP_EOL;
                error_log($errorMessage, 3, $this->logPath);
        }
        $this->clear();
    }

    /**
     * clears the current phpmailer
     */
    private function clear() {
        if (!isset($this->phpMailer)) {
            return;
        }
        $this->phpMailer->clearAddresses();
        $this->phpMailer->clearCCs();
        $this->phpMailer->clearBCCs();
        $this->phpMailer->clearReplyTos();
        $this->phpMailer->clearAllRecipients();
        $this->phpMailer->clearAttachments();
        $this->phpMailer->clearCustomHeaders();
    }

    /**
     * Creates a fresh mailer
     */
    private function create() {
        $m = new \PHPMailer();
        switch ($this->protocol) {
            case 'sendmail':
                $m->isSendmail();
                break;
            case 'smtp':
                $m->isSMTP();
                $m->Host = $this->smtpHost;
                $m->SMTPAuth = false;
                $m->SMTPAutoTLS = false;
                $m->Port = $this->smtpPort;
                break;
            case 'mail':
                $m->isMail();
                break;
            case 'qmail':
                $m->isQmail();
                break;
            default:
                throw new \Exception('bad phpmailerType: '.$this->protocol);
        }
        return $m;
    }
}
