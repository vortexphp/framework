<?php

declare(strict_types=1);

namespace Vortex\Http;

use DateTimeInterface;
use InvalidArgumentException;
use Vortex\Support\DateHelp;

/**
 * HTTP {@code Set-Cookie} value (RFC 6265). For PHP’s native session cookie, use {@see Session} — it calls
 * {@see session_set_cookie_params()}; this class is for application cookies you attach with {@see Response::cookie()}
 * or {@see queue()} (flushed onto the outgoing response in {@see Kernel::handle()}).
 */
final class Cookie
{
    /** @var list<Cookie> */
    private static array $queued = [];

    /**
     * @param non-empty-string $name  RFC 6265 {@code token}
     */
    public function __construct(
        public readonly string $name,
        public readonly string $value,
        public readonly string $path = '/',
        public readonly string $domain = '',
        public readonly ?int $maxAge = null,
        public readonly ?DateTimeInterface $expires = null,
        public readonly bool $secure = false,
        public readonly bool $httpOnly = true,
        public readonly string $sameSite = 'Lax',
    ) {
        if ($name === '' || ! preg_match('/^[\x21\x23-\x2B\x2D-\x3A\x3C-\x5B\x5D-\x7E]+$/', $name)) {
            throw new InvalidArgumentException('Invalid cookie name.');
        }
    }

    /**
     * Queue a cookie for the current HTTP response. {@see Kernel::handle()} calls {@see flushQueued()} before
     * returning, so handlers and middleware can emit cookies without holding the {@see Response} instance.
     */
    public static function queue(self $cookie): void
    {
        self::$queued[] = $cookie;
    }

    /**
     * Append all {@see queue()}d cookies to the response and clear the queue.
     */
    public static function flushQueued(Response $response): Response
    {
        foreach (self::$queued as $c) {
            $response->cookie($c);
        }
        self::$queued = [];

        return $response;
    }

    /**
     * @internal Discard queued cookies without sending (e.g. PHPUnit).
     */
    public static function resetQueue(): void
    {
        self::$queued = [];
    }

    /**
     * Normalize env-style SameSite strings for PHP’s session cookie params or {@see self::$sameSite}.
     */
    public static function normalizedSameSite(string $samesite): string
    {
        return match (strtolower(trim($samesite))) {
            'none' => 'None',
            'strict' => 'Strict',
            default => 'Lax',
        };
    }

    /**
     * Parse a raw {@code Cookie} request header into name => value (first occurrence wins on duplicates).
     * Semicolons inside double-quoted values do not split pairs.
     *
     * @return array<string, string>
     */
    public static function parseRequestHeader(string $header): array
    {
        $out = [];
        $len = strlen($header);
        $i = 0;
        while ($i < $len) {
            while ($i < $len && ($header[$i] === ' ' || $header[$i] === "\t" || $header[$i] === ';')) {
                ++$i;
            }
            if ($i >= $len) {
                break;
            }
            $eq = strpos($header, '=', $i);
            if ($eq === false) {
                break;
            }
            $name = trim(substr($header, $i, $eq - $i));
            $i = $eq + 1;
            if ($name === '') {
                continue;
            }
            if ($i >= $len) {
                if (! array_key_exists($name, $out)) {
                    $out[$name] = '';
                }

                break;
            }
            if ($header[$i] === '"') {
                ++$i;
                $buf = '';
                while ($i < $len) {
                    if ($header[$i] === '\\' && $i + 1 < $len) {
                        $buf .= $header[$i + 1];
                        $i += 2;

                        continue;
                    }
                    if ($header[$i] === '"') {
                        ++$i;

                        break;
                    }
                    $buf .= $header[$i];
                    ++$i;
                }
                $value = $buf;
            } else {
                $start = $i;
                while ($i < $len && $header[$i] !== ';') {
                    ++$i;
                }
                $value = rawurldecode(trim(substr($header, $start, $i - $start)));
            }
            if (! array_key_exists($name, $out)) {
                $out[$name] = $value;
            }
        }

        return $out;
    }

    /**
     * Value for a single {@code Set-Cookie} header (without the {@code Set-Cookie:} prefix).
     */
    public function toHeaderValue(): string
    {
        $parts = [$this->name . '=' . self::encodeValue($this->value)];
        if ($this->path !== '') {
            $parts[] = 'Path=' . $this->path;
        }
        if ($this->domain !== '') {
            $parts[] = 'Domain=' . $this->domain;
        }
        if ($this->maxAge !== null) {
            $parts[] = 'Max-Age=' . $this->maxAge;
        }
        if ($this->expires !== null) {
            $parts[] = 'Expires=' . DateHelp::toHttpDate($this->expires);
        }
        if ($this->secure) {
            $parts[] = 'Secure';
        }
        if ($this->httpOnly) {
            $parts[] = 'HttpOnly';
        }
        $ss = self::normalizedSameSite($this->sameSite);
        $parts[] = 'SameSite=' . $ss;

        return implode('; ', $parts);
    }

    private static function encodeValue(string $value): string
    {
        if ($value === '') {
            return '';
        }
        if (! preg_match('/[\x00-\x20\x22\x2c\x3b\x5c]/', $value)) {
            return $value;
        }

        return '"' . addcslashes($value, "\\\"\n\r\t;") . '"';
    }

}
