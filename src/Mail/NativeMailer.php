<?php

declare(strict_types=1);

namespace Vortex\Mail;

use Vortex\Contracts\Mailer;
use RuntimeException;

/**
 * Uses PHP `mail()`. Requires a working sendmail/exim/postfix on the host.
 */
final class NativeMailer implements Mailer
{
    public function send(MailMessage $message): void
    {
        $toHeader = implode(', ', array_map(static fn (array $a): string => $a[0], $message->to));
        $headers = [
            'From: ' . MailEncoding::formatAddress($message->from),
            'MIME-Version: 1.0',
        ];
        if ($message->cc !== []) {
            $headers[] = 'Cc: ' . implode(', ', array_map(static fn (array $a): string => MailEncoding::formatAddress($a), $message->cc));
        }
        if ($message->bcc !== []) {
            $headers[] = 'Bcc: ' . implode(', ', array_map(static fn (array $a): string => $a[0], $message->bcc));
        }
        if ($message->htmlBody !== null && $message->htmlBody !== '') {
            $boundary = 'b_' . bin2hex(random_bytes(8));
            $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';
            $body = "--{$boundary}\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n"
                . str_replace("\r\n", "\n", $message->textBody)
                . "\r\n--{$boundary}\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n"
                . str_replace("\r\n", "\n", $message->htmlBody)
                . "\r\n--{$boundary}--\r\n";
        } else {
            $headers[] = 'Content-Type: text/plain; charset=UTF-8';
            $body = str_replace("\r\n", "\n", $message->textBody);
        }
        $subject = MailEncoding::encodeSubject($message->subject);
        $ok = @mail($toHeader, $subject, $body, implode("\r\n", $headers));
        if (! $ok) {
            throw new RuntimeException('mail() returned false; check server MTA configuration.');
        }
    }
}
