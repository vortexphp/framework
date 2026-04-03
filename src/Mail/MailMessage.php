<?php

declare(strict_types=1);

namespace Vortex\Mail;

use InvalidArgumentException;

/**
 * Immutable outbound message. `$from` and each `$to` entry: `[email, optional display name]`.
 *
 * @phpstan-type AddressPair array{0: string, 1?: string|null}
 */
final readonly class MailMessage
{
    /**
     * @param AddressPair $from
     * @param list<AddressPair> $to
     * @param list<AddressPair> $cc
     * @param list<AddressPair> $bcc
     */
    public function __construct(
        public array $from,
        public array $to,
        public string $subject,
        public string $textBody,
        public ?string $htmlBody = null,
        public array $cc = [],
        public array $bcc = [],
    ) {
        self::assertAddress($from, 'from');
        foreach ($to as $i => $addr) {
            self::assertAddress($addr, "to[{$i}]");
        }
        foreach ($cc as $i => $addr) {
            self::assertAddress($addr, "cc[{$i}]");
        }
        foreach ($bcc as $i => $addr) {
            self::assertAddress($addr, "bcc[{$i}]");
        }
        if ($to === []) {
            throw new InvalidArgumentException('Mail message must have at least one to address.');
        }
    }

    /**
     * @param AddressPair $pair
     */
    private static function assertAddress(array $pair, string $label): void
    {
        if ($pair === [] || ! isset($pair[0]) || ! is_string($pair[0]) || $pair[0] === '') {
            throw new InvalidArgumentException("Invalid {$label}: email required.");
        }
        if (filter_var($pair[0], FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException("Invalid {$label}: bad email [{$pair[0]}].");
        }
    }
}
