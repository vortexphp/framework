<?php

declare(strict_types=1);

namespace Vortex\Tests;

use PHPUnit\Framework\TestCase;
use Vortex\Mail\MailEncoding;

final class MailEncodingTest extends TestCase
{
    public function testUtf8SubjectEncoded(): void
    {
        $s = MailEncoding::encodeSubject('Привет');
        self::assertStringStartsWith('=?UTF-8?B?', $s);
    }

    public function testAsciiSubjectUnchanged(): void
    {
        self::assertSame('Hi', MailEncoding::encodeSubject('Hi'));
    }
}
