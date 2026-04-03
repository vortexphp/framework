<?php

declare(strict_types=1);

namespace Vortex\Database\Schema;

use InvalidArgumentException;
use RuntimeException;
use Vortex\Database\Connection;

class SchemaMigrator
{
    public function __construct(
        private readonly string $basePath,
        private readonly Connection $db,
    ) {
    }

    public function up(): int
    {
        $this->ensureRepository();
        $migrations = $this->discoverMigrations();
        $applied = $this->appliedIds();
        $batch = $this->nextBatchNumber();
        $ran = 0;

        foreach ($migrations as $id => $migration) {
            if (isset($applied[$id])) {
                continue;
            }
            $this->db->transaction(function (Connection $db) use ($migration, $id, $batch): void {
                $migration->up($db);
                $db->execute(
                    'INSERT INTO vortex_migrations (id, batch, applied_at) VALUES (?, ?, ?)',
                    [$id, $batch, gmdate('Y-m-d H:i:s')],
                );
            });
            ++$ran;
        }

        return $ran;
    }

    public function down(): int
    {
        $this->ensureRepository();
        $lastBatch = $this->lastBatchNumber();
        if ($lastBatch === null) {
            return 0;
        }

        $migrations = $this->discoverMigrations();
        $rows = $this->db->select(
            'SELECT id FROM vortex_migrations WHERE batch = ? ORDER BY id DESC',
            [$lastBatch],
        );
        $rolledBack = 0;

        foreach ($rows as $row) {
            $id = (string) ($row['id'] ?? '');
            if ($id === '' || ! isset($migrations[$id])) {
                throw new RuntimeException('Cannot rollback migration [' . $id . '] because it is missing.');
            }
            $migration = $migrations[$id];
            $this->db->transaction(function (Connection $db) use ($migration, $id): void {
                $migration->down($db);
                $db->execute('DELETE FROM vortex_migrations WHERE id = ?', [$id]);
            });
            ++$rolledBack;
        }

        return $rolledBack;
    }

    /**
     * @return array<string, Migration>
     */
    private function discoverMigrations(): array
    {
        $dir = $this->basePath . '/database/migrations';
        if (! is_dir($dir)) {
            throw new InvalidArgumentException('Missing database/migrations directory.');
        }

        $paths = glob($dir . '/*.php') ?: [];
        sort($paths, SORT_STRING);

        $migrations = [];
        foreach ($paths as $path) {
            $migration = require $path;
            if (! $migration instanceof Migration) {
                throw new InvalidArgumentException('Migration file must return a Migration instance: ' . $path);
            }
            $id = trim($migration->id());
            if ($id === '') {
                throw new InvalidArgumentException('Migration id must not be empty: ' . $path);
            }
            if (isset($migrations[$id])) {
                throw new InvalidArgumentException('Duplicate migration id [' . $id . '] in ' . $path);
            }
            $migrations[$id] = $migration;
        }

        return $migrations;
    }

    private function ensureRepository(): void
    {
        $this->db->pdo()->exec(
            'CREATE TABLE IF NOT EXISTS vortex_migrations ('
            . 'id TEXT PRIMARY KEY, '
            . 'batch INTEGER NOT NULL, '
            . 'applied_at TEXT NOT NULL'
            . ')',
        );
    }

    /**
     * @return array<string, true>
     */
    private function appliedIds(): array
    {
        $rows = $this->db->select('SELECT id FROM vortex_migrations');
        $ids = [];
        foreach ($rows as $row) {
            $id = (string) ($row['id'] ?? '');
            if ($id !== '') {
                $ids[$id] = true;
            }
        }

        return $ids;
    }

    private function nextBatchNumber(): int
    {
        $row = $this->db->selectOne('SELECT MAX(batch) AS max_batch FROM vortex_migrations');
        $max = isset($row['max_batch']) ? (int) $row['max_batch'] : 0;

        return $max + 1;
    }

    private function lastBatchNumber(): ?int
    {
        $row = $this->db->selectOne('SELECT MAX(batch) AS max_batch FROM vortex_migrations');
        if ($row === null || $row['max_batch'] === null) {
            return null;
        }

        return (int) $row['max_batch'];
    }
}
