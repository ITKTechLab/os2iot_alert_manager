<?php

namespace App\Service;

use App\Exception\MailException;

interface MailServiceInterface
{
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
    public function sendEmail(string $to, array $context, string $refId, string $subject = 'Test mail from alert manager', string $htmlTemplate = 'test.html.twig', string $textTemplate = 'test.txt.twig'): void;
}
