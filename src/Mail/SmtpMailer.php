<?php

declare(strict_types=1);

namespace Vortex\Mail;

use Vortex\Contracts\Mailer;
use RuntimeException;

/**
 * SMTP submission (port 587 + STARTTLS, or 465 SSL). AUTH PLAIN when username is non-empty.
 */
final class SmtpMailer implements Mailer
{
    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $username,
        private readonly string $password,
        /** '', 'tls', or 'ssl' */
        private readonly string $encryption,
    ) {
    }

    public function send(MailMessage $message): void
    {
        $enc = strtolower(trim($this->encryption));
        $remote = $enc === 'ssl'
            ? 'ssl://' . $this->host . ':' . $this->port
            : 'tcp://' . $this->host . ':' . $this->port;

        $errno = 0;
        $errstr = '';
        $fp = @stream_socket_client($remote, $errno, $errstr, 30, STREAM_CLIENT_CONNECT);
        if ($fp === false) {
            throw new RuntimeException("SMTP connect failed: {$errstr} ({$errno})");
        }
        stream_set_timeout($fp, 30);

        try {
            $this->expect($fp, [220]);
            $this->cmd($fp, 'EHLO localhost', [250]);
            if ($enc === 'tls') {
                $this->cmd($fp, 'STARTTLS', [220]);
                if (! stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException('SMTP STARTTLS negotiation failed.');
                }
                $this->cmd($fp, 'EHLO localhost', [250]);
            }
            if ($this->username !== '') {
                $auth = base64_encode("\0{$this->username}\0{$this->password}");
                $this->cmd($fp, 'AUTH PLAIN ' . $auth, [235]);
            }
            $fromEmail = $message->from[0];
            $this->cmd($fp, 'MAIL FROM:<' . $fromEmail . '>', [250]);
            foreach (array_merge($message->to, $message->cc, $message->bcc) as $addr) {
                $this->cmd($fp, 'RCPT TO:<' . $addr[0] . '>', [250, 251]);
            }
            $this->cmd($fp, 'DATA', [354]);
            $payload = $this->buildMime($message);
            fwrite($fp, $this->dotStuff($payload) . "\r\n.\r\n");
            $this->expect($fp, [250]);
            $this->cmd($fp, 'QUIT', [221]);
        } finally {
            fclose($fp);
        }
    }

    private function buildMime(MailMessage $message): string
    {
        $lines = [
            'From: ' . MailEncoding::formatAddress($message->from),
            'To: ' . implode(', ', array_map(static fn (array $a): string => MailEncoding::formatAddress($a), $message->to)),
            'Subject: ' . MailEncoding::encodeSubject($message->subject),
            'MIME-Version: 1.0',
        ];
        if ($message->cc !== []) {
            $lines[] = 'Cc: ' . implode(', ', array_map(static fn (array $a): string => MailEncoding::formatAddress($a), $message->cc));
        }
        $text = str_replace("\r\n", "\n", $message->textBody);
        if ($message->htmlBody !== null && $message->htmlBody !== '') {
            $html = str_replace("\r\n", "\n", $message->htmlBody);
            $b = 'b_' . bin2hex(random_bytes(8));
            $lines[] = 'Content-Type: multipart/alternative; boundary="' . $b . '"';
            $lines[] = '';
            $lines[] = '--' . $b;
            $lines[] = 'Content-Type: text/plain; charset=UTF-8';
            $lines[] = 'Content-Transfer-Encoding: 8bit';
            $lines[] = '';
            $lines[] = $text;
            $lines[] = '--' . $b;
            $lines[] = 'Content-Type: text/html; charset=UTF-8';
            $lines[] = 'Content-Transfer-Encoding: 8bit';
            $lines[] = '';
            $lines[] = $html;
            $lines[] = '--' . $b . '--';
        } else {
            $lines[] = 'Content-Type: text/plain; charset=UTF-8';
            $lines[] = 'Content-Transfer-Encoding: 8bit';
            $lines[] = '';
            $lines[] = $text;
        }

        return implode("\r\n", $lines);
    }

    private function dotStuff(string $data): string
    {
        return preg_replace('/^\./m', '..', $data) ?? $data;
    }

    /**
     * @param resource $fp
     */
    private function cmd($fp, string $line, array $expectCodes): void
    {
        fwrite($fp, $line . "\r\n");
        $this->expect($fp, $expectCodes);
    }

    /**
     * @param resource $fp
     * @param list<int> $expectCodes
     */
    private function expect($fp, array $expectCodes): void
    {
        $code = null;
        $last = '';
        do {
            $line = fgets($fp, 8192);
            if ($line === false) {
                throw new RuntimeException('SMTP connection closed unexpectedly.');
            }
            $line = rtrim($line, "\r\n");
            $last = $line;
            $code = (int) substr($line, 0, 3);
            $more = strlen($line) >= 4 && $line[3] === '-';
        } while ($more);

        if (! in_array($code, $expectCodes, true)) {
            throw new RuntimeException('SMTP error: expected ' . implode('/', $expectCodes) . ', got: ' . $last);
        }
    }
}
