<?php

declare(strict_types=1);

namespace Vortex\Mail;

use Vortex\AppContext;
use Vortex\Config\Repository;
use Vortex\Contracts\Mailer;

/**
 * Static access to the singleton {@see Mailer}.
 */
final class Mail
{
    private static function mailer(): Mailer
    {
        return AppContext::container()->make(Mailer::class);
    }

    public static function send(MailMessage $message): void
    {
        self::mailer()->send($message);
    }

    /**
     * Default `[address, name]` from `config/mail.php` for {@see MailMessage} construction.
     *
     * @return array{0: string, 1: string}
     */
    public static function defaultFrom(): array
    {
        return [
            (string) Repository::get('mail.from.address', 'hello@example.com'),
            (string) Repository::get('mail.from.name', 'App'),
        ];
    }
}
