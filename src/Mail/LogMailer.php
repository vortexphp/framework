<?php

declare(strict_types=1);

namespace Vortex\Mail;

use Vortex\Contracts\Mailer;

/**
 * Appends a readable record to `storage/logs/mail.log` (dev / tests).
 */
final class LogMailer implements Mailer
{
    public function __construct(
        private readonly string $basePath,
    ) {
    }

    public function send(MailMessage $message): void
    {
        $dir = rtrim($this->basePath, '/') . '/storage/logs';
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $file = $dir . '/mail.log';
        $block = sprintf(
            "[%s] MAIL to=%s subject=%s\n%s\n---\n",
            date('c'),
            implode(', ', array_map(static fn (array $a): string => $a[0], $message->to)),
            $message->subject,
            $message->textBody,
        );
        error_log($block, 3, $file);
    }
}
