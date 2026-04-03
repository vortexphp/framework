<?php

declare(strict_types=1);

namespace Vortex\Tests;

use DateTimeImmutable;
use DateTimeZone;
use JsonException;
use PHPUnit\Framework\TestCase;
use Vortex\Support\CollectionHelp;
use Vortex\Support\DateHelp;
use Vortex\Support\HtmlHelp;
use Vortex\Support\JsonHelp;
use Vortex\Support\NumberHelp;
use Vortex\Support\PathHelp;
use Vortex\Support\UrlHelp;

final class EngineSupportTest extends TestCase
{
    public function testJsonHelpEncodeDecode(): void
    {
        $json = JsonHelp::encode(['a' => 1, 'u' => 'é']);
        self::assertStringContainsString('é', $json);
        self::assertSame(['a' => 1, 'u' => 'é'], JsonHelp::decodeArray($json));
    }

    public function testJsonHelpDecodeArrayRejectsNonObjectRoot(): void
    {
        $this->expectException(JsonException::class);
        JsonHelp::decodeArray('"hi"');
    }

    public function testJsonHelpTryDecodeArrayReturnsNullOnInvalid(): void
    {
        self::assertNull(JsonHelp::tryDecodeArray('{'));
        self::assertNull(JsonHelp::tryDecodeArray(''));
        self::assertSame(['x' => 1], JsonHelp::tryDecodeArray('{"x":1}'));
    }

    public function testUrlHelpWithQuery(): void
    {
        self::assertSame('/login?next=%2Faccount', UrlHelp::withQuery('/login', ['next' => '/account']));
        self::assertSame('/p?a=1&b=2', UrlHelp::withQuery('/p?a=1', ['b' => 2]));
        self::assertSame('/p#frag', UrlHelp::withQuery('/p#frag', []));
        self::assertSame('/p?x=1#frag', UrlHelp::withQuery('/p#frag', ['x' => 1]));
    }

    public function testUrlHelpWithoutFragmentAndIsInternalPath(): void
    {
        self::assertSame('/a', UrlHelp::withoutFragment('/a#x'));
        self::assertTrue(UrlHelp::isInternalPath('/ok'));
        self::assertFalse(UrlHelp::isInternalPath('//evil'));
        self::assertFalse(UrlHelp::isInternalPath('https://x'));
    }

    public function testPathHelpJoin(): void
    {
        self::assertSame('/var/www/html', PathHelp::join('/var', 'www', 'html'));
        self::assertSame('var/www', PathHelp::join('var', 'www'));
        self::assertSame('', PathHelp::join('..'));
    }

    public function testPathHelpIsBelowBase(): void
    {
        $base = sys_get_temp_dir() . '/vortex _path_' . bin2hex(random_bytes(4));
        $sub = $base . '/sub';
        self::assertTrue(mkdir($base, 0777, true));
        self::assertTrue(mkdir($sub, 0777, true));
        $file = $sub . '/f.txt';
        self::assertNotFalse(file_put_contents($file, 'x'));

        self::assertTrue(PathHelp::isBelowBase($base, $file));
        self::assertFalse(PathHelp::isBelowBase($sub, $base));

        @unlink($file);
        @rmdir($sub);
        @rmdir($base);
    }

    public function testNumberHelp(): void
    {
        self::assertSame(2, NumberHelp::clamp(5, 1, 2));
        self::assertSame(10, NumberHelp::parseInt('99', 3, 1, 10));
        self::assertSame(3, NumberHelp::parseInt(null, 3, 1, 10));
        self::assertSame('0 B', NumberHelp::formatBytes(0));
        self::assertStringEndsWith('KB', NumberHelp::formatBytes(2048));
    }

    public function testDateHelp(): void
    {
        $dt = new DateTimeImmutable('2024-06-01 12:00:00', new DateTimeZone('UTC'));
        self::assertStringStartsWith('2024-06-01T12:00:00', DateHelp::toRfc3339($dt));
        self::assertStringContainsString('Jun', DateHelp::toHttpDate($dt));
    }

    public function testHtmlHelp(): void
    {
        self::assertSame('Hi', HtmlHelp::stripTags('<p>Hi</p>'));
        self::assertStringContainsString('Hi', HtmlHelp::stripTags('<p>Hi</p><script>x</script>', ['p']));
        self::assertSame('Hello wo...', HtmlHelp::excerpt('<p>Hello world</p>', 8));
    }

    public function testCollectionHelp(): void
    {
        $rows = [
            ['id' => 1, 'g' => 'a'],
            ['id' => 2, 'g' => 'a'],
            ['id' => 3, 'g' => 'b'],
        ];
        self::assertSame([1, 2, 3], CollectionHelp::pluck($rows, 'id'));
        self::assertSame(['a' => $rows[1], 'b' => $rows[2]], CollectionHelp::keyBy($rows, 'g'));
        self::assertCount(2, CollectionHelp::groupBy($rows, 'g')['a']);
    }
}
