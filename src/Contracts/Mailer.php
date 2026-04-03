<?php

declare(strict_types=1);

namespace Vortex\Contracts;

use Vortex\Mail\MailMessage;

interface Mailer
{
    public function send(MailMessage $message): void;
}
