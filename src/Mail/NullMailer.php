<?php

declare(strict_types=1);

namespace Vortex\Mail;

use Vortex\Contracts\Mailer;

final class NullMailer implements Mailer
{
    public function send(MailMessage $message): void
    {
    }
}
