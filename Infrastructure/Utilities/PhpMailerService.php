<?php
declare(strict_types=1);

namespace Infrastructure\Utilities;

use PHPMailer\PHPMailer\PHPMailer;

/**
 * Service Layer for PHPMailer
 */
class PhpMailerService 
{
    private $logPath;
    private $defaultReturnPathEmail;
    private $defaultFromEmail;
    private $defaultFromName;
    private $protocol;
    private $smtpHost;
    private $smtpPort;
    private $smtpUsername;
    private $smtpPassword;
    private $phpMailer;
    private $errorInfo;

    public function __construct(string $logPath, string $defaultReturnPathEmail, string $defaultFromEmail, string $defaultFromName, ?string $protocol = 'smtp', ?string $smtpHost = null, ?int $smtpPort = null, ?string $smtpUsername = null, ?string $smtpPassword = null)
    {
        $this->logPath = $logPath;
        $this->defaultReturnPathEmail = $defaultReturnPathEmail;
        $this->defaultFromEmail = $defaultFromEmail;
        $this->defaultFromName = $defaultFromName;
        $this->protocol = strtolower($protocol);
        $this->smtpHost = $smtpHost;
        $this->smtpPort = $smtpPort;
        $this->smtpUsername = $smtpUsername;
        $this->smtpPassword = $smtpPassword;
        $this->phpMailer = $this->create();
    }

    // should look into changing this to only send to 1 email and validate it first
    // this could be saved as multiSend()
    public function send(string $subject, string $body, array $toEmails, ?string $fromEmail = null, ?string $fromName = null, ?bool $isHtml = false, ?string $altBody = null, ?array $cc = null, ?array $bcc = null, ?string $replyToEmail = null, ?string $replyToName = null, ?string $returnPathEmailOverride = null): bool
    {
        if (count($toEmails) == 0) {
            throw new \Exception("No email(s) provided");
        }
        $toEmails = array_unique($toEmails);
        $this->phpMailer->Sender = $returnPathEmailOverride ?? $this->defaultReturnPathEmail; /** The Sender email (Return-Path) of the message. */
        $this->phpMailer->Subject = $subject;
        $this->phpMailer->Body = $body;
        $this->phpMailer->isHTML($isHtml);
        $this->phpMailer->AltBody = $altBody ?? '';
        $toEmailsString = ''; /** used in error message in case of failure */
        $sendCount = 0;
        foreach ($toEmails as $email) {
            if (is_string($email) && strlen($email) > 0) {
                $sendCount++;
                $toEmailsString .= "$email ";
                $this->phpMailer->addAddress(strtolower($email));    
            }
        }
        if ($sendCount == 0) {
            throw new \Exception("No valid email(s) provided");
        }
        if (!is_null($cc)) {
            foreach ($cc as $ccEmail) {
                $this->phpMailer->addCC($ccEmail);
            }
        }
        if (!is_null($bcc)) {
            foreach ($bcc as $bccEmail) {
                $this->phpMailer->addBCC($bccEmail);
            }
        }
        if ($fromEmail == null) {
            $fromEmail = $this->defaultFromEmail;
        }
        if ($fromName == null) {
            $fromName = $this->defaultFromName;
        }
        $this->phpMailer->setFrom($fromEmail, $fromName);
        if ($replyToEmail != null) {
            $replyToName = $replyToName === null ? '' : $replyToName; // blank string instead of null for compatibility with fn arg below
            $this->phpMailer->addReplyTo($replyToEmail, $replyToName);
        }
        if (!$this->phpMailer->send()) {
            $this->errorInfo = $this->phpMailer->ErrorInfo;
            // note do not throw exception here because could get stuck in loop trying to email
            $errorMessage = "[".date('Y-m-d H:i:s e')."]" . PHP_EOL .
                "PhpMailer::send() failed: ".$this->phpMailer->ErrorInfo . PHP_EOL .
                "subject: $subject" . PHP_EOL .
                "body: $body" . PHP_EOL .
                "to: $toEmailsString" . PHP_EOL .
                "from: $fromEmail" . PHP_EOL . PHP_EOL;
                error_log($errorMessage, 3, $this->logPath);
            $this->clear();
            return false;
        }
        $this->clear();
        return true;
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
        $m = new PHPMailer();
        $m->CharSet = 'utf-8';
        switch ($this->protocol) {
            case 'sendmail':
                $m->isSendmail();
                break;
            case 'smtp':
                $m->isSMTP();
                $m->Host = $this->smtpHost;
                $m->SMTPAuth = true;
                $m->Port = $this->smtpPort;
                $m->Username = $this->smtpUsername;
                $m->Password = $this->smtpPassword;
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

    public function getErrorInfo(): ?string 
    {
        return $this->errorInfo;
    }
}
