<?php

declare(strict_types=1);

namespace Vortex\Mail;

use InvalidArgumentException;
use Vortex\Config\Repository;
use Vortex\Contracts\Mailer;

final class MailFactory
{
    public static function make(string $basePath): Mailer
    {
        $driver = strtolower(trim((string) Repository::get('mail.driver', 'log')));
        $basePath = rtrim($basePath, '/');

        return match ($driver) {
            'null' => new NullMailer(),
            'log' => new LogMailer($basePath),
            'native' => new NativeMailer(),
            'smtp' => new SmtpMailer(
                (string) Repository::get('mail.smtp.host', '127.0.0.1'),
                (int) Repository::get('mail.smtp.port', 587),
                (string) Repository::get('mail.smtp.username', ''),
                (string) Repository::get('mail.smtp.password', ''),
                strtolower(trim((string) Repository::get('mail.smtp.encryption', 'tls'))),
            ),
            default => throw new InvalidArgumentException("Unsupported mail driver [{$driver}]. Use null, log, native, or smtp."),
        };
    }
}
