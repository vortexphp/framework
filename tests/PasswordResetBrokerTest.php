<?php

declare(strict_types=1);

namespace Vortex\Tests;

use PDO;
use PHPUnit\Framework\TestCase;
use Vortex\Auth\PasswordResetBroker;
use Vortex\Database\Connection;

final class PasswordResetBrokerTest extends TestCase
{
    private Connection $db;

    protected function setUp(): void
    {
        parent::setUp();
        $pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $pdo->exec('CREATE TABLE password_reset_tokens (
            email TEXT NOT NULL PRIMARY KEY,
            token TEXT NOT NULL,
            created_at INTEGER NOT NULL
        )');
        $this->db = new Connection($pdo);
    }

    public function testIssueVerifyAndConsume(): void
    {
        $broker = new PasswordResetBroker($this->db, 'password_reset_tokens', 3600);

        $plain = $broker->issueToken(' User@Example.com ');
        self::assertSame(64, strlen($plain));
        self::assertTrue($broker->tokenValid('user@example.com', $plain));
        self::assertTrue($broker->verifyAndConsume('user@example.com', $plain));
        self::assertFalse($broker->verifyAndConsume('user@example.com', $plain));
    }

    public function testWrongTokenFails(): void
    {
        $broker = new PasswordResetBroker($this->db, 'password_reset_tokens', 3600);
        $broker->issueToken('a@b.co');

        self::assertFalse($broker->verifyAndConsume('a@b.co', str_repeat('0', 64)));
    }

    public function testExpiredTokenRejected(): void
    {
        $broker = new PasswordResetBroker($this->db, 'password_reset_tokens', 60);
        $plain = $broker->issueToken('old@b.co');
        $this->db->execute('UPDATE password_reset_tokens SET created_at = ? WHERE email = ?', [1, 'old@b.co']);

        self::assertFalse($broker->tokenValid('old@b.co', $plain));
        self::assertFalse($broker->verifyAndConsume('old@b.co', $plain));
    }
}
