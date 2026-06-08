<?php

namespace App\Service;

use App\Exception\MailException;
use ItkDev\MetricsBundle\Service\MetricsService;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

final readonly class MailService implements MailServiceInterface
{
    public function __construct(
        private MailerInterface $mailer,
        private MetricsService $metricsService,
        private string $fromAddress,
        private string $fromName,
        private string $replyAddress,
        private string $defaultAddress,
    ) {
    }

    /**
     * Send an email.
     *
     * @param string $to
     *   The recipient's email address
     * @param array $context
     *   The context for the email template
     * @param string $refId
     *   References header id (used to link mails)
     * @param string $subject
     *   The subject of the email. Defaults to 'Test mail from alert manager'.
     * @param string $htmlTemplate
     *   The HTML template for the email. Defaults to 'test.html.twig'.
     * @param string $textTemplate
     *   The text template for the email. Defaults to 'test.txt.twig'.
     *
     * @throws MailException
     */
    public function sendEmail(string $to, array $context, string $refId, string $subject = 'Test mail from alert manager', string $htmlTemplate = 'test.html.twig', string $textTemplate = 'test.txt.twig'): void
    {
        $email = (new TemplatedEmail())
            ->from(new Address($this->fromAddress, $this->fromName))
            ->to($to)
            ->cc($this->defaultAddress)
            ->replyTo($this->replyAddress)
            ->priority(Email::PRIORITY_HIGH)
            ->subject($subject)
            ->textTemplate('mails/'.$textTemplate)
            ->htmlTemplate('mails/'.$htmlTemplate)
            ->context($context)
        ;

        // Set references header to thread/link mails.
        list($localPart, $domainPart) = explode('@', $this->fromAddress);
        $email->getHeaders()->addTextHeader('References', $refId.'@'.$domainPart);

        try {
            $this->mailer->send($email);
            $this->metricsService->counter(
                name: 'mail_sent_total',
                help: 'The total number of messages sent.',
                labels: ['type' => 'info']
            );
        } catch (TransportExceptionInterface $e) {
            $this->metricsService->counter(
                name: 'mail_sent_exception_total',
                help: 'The total number of messages failed to send.',
                labels: ['type' => 'exception']
            );
            throw new MailException('Unable to send notification mail', $e->getCode(), $e);
        }
    }
}
