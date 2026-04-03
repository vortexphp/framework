<?php

declare(strict_types=1);

namespace Vortex\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Vortex\Mail\MailMessage;

final class MailMessageTest extends TestCase
{
    public function testValidMessage(): void
    {
        $m = new MailMessage(
            ['from@ex.test', 'From'],
            [['to@ex.test', 'To']],
            'Subj',
            "Line\n",
        );
        self::assertSame('from@ex.test', $m->from[0]);
        self::assertSame('Subj', $m->subject);
    }

    public function testRejectsBadEmail(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new MailMessage(
            ['not-an-email'],
            [['to@ex.test']],
            'S',
            'b',
        );
    }

    public function testRequiresTo(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new MailMessage(
            ['a@b.test'],
            [],
            'S',
            'b',
        );
    }
}
