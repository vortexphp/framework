<?php

declare(strict_types=1);

namespace Vortex\Auth;

use Vortex\Database\Connection;

/**
 * Email password reset tokens stored in SQL (one row per email). Tokens are single-use; rows expire by TTL.
 */
final class PasswordResetBroker
{
    public function __construct(
        private readonly Connection $db,
        private readonly string $table = 'password_reset_tokens',
        private readonly int $ttlSeconds = 3600,
    ) {
    }

    /**
     * Replace any existing token for this email and return the plain token to embed in the reset link.
     */
    public function issueToken(string $email): string
    {
        $email = $this->normalizeEmail($email);
        $plain = bin2hex(random_bytes(32));
        $hash = hash('sha256', $plain);
        $createdAt = time();

        $this->db->execute('DELETE FROM ' . $this->table . ' WHERE email = ?', [$email]);
        $this->db->execute(
            'INSERT INTO ' . $this->table . ' (email, token, created_at) VALUES (?, ?, ?)',
            [$email, $hash, $createdAt],
        );

        return $plain;
    }

    /**
     * Whether the token matches and is still within TTL (does not delete).
     */
    public function tokenValid(string $email, string $plainToken): bool
    {
        return $this->rowMatches($email, $plainToken) !== null;
    }

    /**
     * If the token is valid and fresh, delete the row and return true.
     */
    public function verifyAndConsume(string $email, string $plainToken): bool
    {
        $email = $this->normalizeEmail($email);
        $row = $this->rowMatches($email, $plainToken);
        if ($row === null) {
            return false;
        }

        $this->db->execute('DELETE FROM ' . $this->table . ' WHERE email = ?', [$email]);

        return true;
    }

    public function deleteForEmail(string $email): void
    {
        $this->db->execute('DELETE FROM ' . $this->table . ' WHERE email = ?', [$this->normalizeEmail($email)]);
    }

    public function purgeExpired(): void
    {
        $cut = time() - $this->ttlSeconds;
        $this->db->execute('DELETE FROM ' . $this->table . ' WHERE created_at < ?', [$cut]);
    }

    private function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    /**
     * @return array{token: string, created_at: int|string}|null
     */
    private function rowMatches(string $normalizedEmail, string $plainToken): ?array
    {
        $row = $this->db->selectOne(
            'SELECT token, created_at FROM ' . $this->table . ' WHERE email = ? LIMIT 1',
            [$normalizedEmail],
        );
        if ($row === null) {
            return null;
        }

        $created = (int) ($row['created_at'] ?? 0);
        if ($created <= 0 || time() - $created > $this->ttlSeconds) {
            $this->db->execute('DELETE FROM ' . $this->table . ' WHERE email = ?', [$normalizedEmail]);

            return null;
        }

        $hash = (string) ($row['token'] ?? '');
        if ($hash === '' || ! hash_equals($hash, hash('sha256', $plainToken))) {
            return null;
        }

        return ['token' => $hash, 'created_at' => $created];
    }
}
