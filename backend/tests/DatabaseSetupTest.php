<?php

declare(strict_types=1);

namespace DoubtQueue\Tests;

use DoubtQueue\Database;
use DoubtQueue\DatabaseSetup;
use PDO;
use PHPUnit\Framework\TestCase;

class DatabaseSetupTest extends TestCase
{
    private PDO $pdo;
    private DatabaseSetup $databaseSetup;

    protected function setUp(): void
    {
        $this->pdo = Database::memory();
        $this->databaseSetup = new DatabaseSetup($this->pdo);
    }

    public function testRunCreatesSessionsAndDoubtsTables(): void
    {
        $this->databaseSetup->run();

        $tables = $this->pdo
            ->query("SELECT name FROM sqlite_master WHERE type = 'table'")
            ->fetchAll(PDO::FETCH_COLUMN);

        $this->assertContains('sessions', $tables);
        $this->assertContains('doubts', $tables);
    }

    public function testRunInsertsExpectedSampleData(): void
    {
        $this->databaseSetup->run();

        $this->assertSame(1, (int) $this->pdo
            ->query("SELECT COUNT(*) FROM sessions WHERE status = 'OPEN'")
            ->fetchColumn());

        $this->assertSame(1, (int) $this->pdo
            ->query("SELECT COUNT(*) FROM sessions WHERE status = 'CLOSED'")
            ->fetchColumn());

        $this->assertSame(5, (int) $this->pdo
            ->query("SELECT COUNT(*) FROM doubts WHERE status = 'WAITING'")
            ->fetchColumn());

        $this->assertSame(3, (int) $this->pdo
            ->query("SELECT COUNT(*) FROM doubts WHERE status = 'SOLVED'")
            ->fetchColumn());

        $this->assertSame(1, (int) $this->pdo
            ->query("SELECT COUNT(*) FROM doubts WHERE status = 'SKIPPED'")
            ->fetchColumn());
    }

    public function testRunTwiceDoesNotDuplicateSampleData(): void
    {
        $this->databaseSetup->run();

        $sessionsBefore = (int) $this->pdo
            ->query('SELECT COUNT(*) FROM sessions')->fetchColumn();

        $doubtsBefore = (int) $this->pdo
            ->query('SELECT COUNT(*) FROM doubts')->fetchColumn();

        $this->databaseSetup->run();

        $this->assertSame($sessionsBefore, (int) $this->pdo
            ->query('SELECT COUNT(*) FROM sessions')->fetchColumn());

        $this->assertSame($doubtsBefore, (int) $this->pdo
            ->query('SELECT COUNT(*) FROM doubts')->fetchColumn());
    }

    public function testRunDoesNotInsertSampleDataWhenSessionExists(): void
    {
        $this->databaseSetup->createTables();

        $this->pdo->exec(
            "INSERT INTO sessions (title, mentor_name, status, created_at)
             VALUES ('Existing session', 'Mentor', 'OPEN', '2026-01-01 10:00:00')"
        );

        $this->databaseSetup->run();

        $this->assertSame(1, (int) $this->pdo
            ->query('SELECT COUNT(*) FROM sessions')->fetchColumn());

        $this->assertSame(0, (int) $this->pdo
            ->query('SELECT COUNT(*) FROM doubts')->fetchColumn());
    }

    public function testCreateTablesCanBeCalledTwice(): void
    {
        $this->databaseSetup->createTables();
        $this->databaseSetup->createTables();

        $tables = $this->pdo
            ->query("SELECT name FROM sqlite_master WHERE type = 'table'")
            ->fetchAll(PDO::FETCH_COLUMN);

        $this->assertContains('sessions', $tables);
        $this->assertContains('doubts', $tables);
    }
}
