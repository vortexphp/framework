<?php

declare(strict_types=1);

namespace Vortex\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Vortex\Validation\Rule;
use Vortex\Validation\Validator;

final class ValidatorTest extends TestCase
{
    public function testPassesWhenRulesSatisfied(): void
    {
        $r = Validator::make(
            ['email' => 'a@b.co', 'password' => 'secret12', 'password_confirmation' => 'secret12'],
            ['email' => 'required|email|max:255', 'password' => 'required|min:8|confirmed'],
        );

        self::assertFalse($r->failed());
        self::assertSame([], $r->errors());
    }

    public function testRequiredAndEmail(): void
    {
        $r = Validator::make(['email' => ''], ['email' => 'required|email']);

        self::assertTrue($r->failed());
        self::assertArrayHasKey('email', $r->errors());
    }

    public function testMinLength(): void
    {
        $r = Validator::make(['p' => 'short'], ['p' => 'required|min:8']);

        self::assertTrue($r->failed());
    }

    public function testMaxLength(): void
    {
        $r = Validator::make(['t' => str_repeat('a', 5)], ['t' => 'required|max:4']);

        self::assertTrue($r->failed());
    }

    public function testConfirmedPutsErrorOnConfirmationField(): void
    {
        $r = Validator::make(
            ['password' => 'abcdefgh', 'password_confirmation' => 'nomatch'],
            ['password' => 'required|confirmed'],
        );

        self::assertTrue($r->failed());
        self::assertArrayHasKey('password_confirmation', $r->errors());
        self::assertArrayNotHasKey('password', $r->errors());
    }

    public function testNullableSkipsOtherRulesWhenEmpty(): void
    {
        $r = Validator::make(['bio' => ''], ['bio' => 'nullable|email']);

        self::assertFalse($r->failed());
    }

    public function testNullableStillValidatesWhenPresent(): void
    {
        $r = Validator::make(['bio' => 'not-email'], ['bio' => 'nullable|email']);

        self::assertTrue($r->failed());
    }

    public function testCustomMessages(): void
    {
        $r = Validator::make(
            ['x' => ''],
            ['x' => 'required'],
            ['x.required' => 'Nope'],
        );

        self::assertSame('Nope', $r->errors()['x'] ?? null);
    }

    public function testUnknownRuleThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Validator::make(['a' => '1'], ['a' => 'bogus']);
    }

    public function testRuleBuilderSyntaxWorks(): void
    {
        $r = Validator::make(
            ['name' => 'John', 'email' => 'john@example.com', 'password' => 'secret12', 'password_confirmation' => 'secret12'],
            [
                'name' => Rule::required()->string()->max(120),
                'email' => Rule::required()->email()->max(255),
                'password' => Rule::required()->min(8)->confirmed(),
            ],
        );

        self::assertFalse($r->failed());
    }

    public function testRuleBuilderCanProvideInlineMessages(): void
    {
        $r = Validator::make(
            ['email' => 'not-an-email'],
            ['email' => Rule::required('Need email')->email('Email format invalid')],
        );

        self::assertTrue($r->failed());
        self::assertSame('Email format invalid', $r->errors()['email'] ?? null);
    }

    public function testExplicitMessagesOverrideInlineRuleMessages(): void
    {
        $r = Validator::make(
            ['email' => 'not-an-email'],
            ['email' => Rule::required('Need email')->email('Inline message')],
            ['email.email' => 'From make() messages'],
        );

        self::assertTrue($r->failed());
        self::assertSame('From make() messages', $r->errors()['email'] ?? null);
    }
}
