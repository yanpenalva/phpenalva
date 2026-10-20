<?php

declare(strict_types=1);

namespace Tests\Feature;

use Core\MigrationManager;
use PDO;
use PHPUnit\Framework\TestCase;

final class MigrationManagerTest extends TestCase
{
    private PDO $pdo;

    private MigrationManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->manager = new MigrationManager($this->pdo);
    }

    public function testMigrationsApplyInDependencyOrderAndReapplyAfterRollback(): void
    {
        $applied = $this->manager->migrate();
        self::assertSame(['CreateUsersTable', 'CreatePostsTable'], $applied);
        self::assertTrue($this->tableExists('users'));
        self::assertTrue($this->tableExists('posts'));

        $rolledBack = $this->manager->rollback(true);
        self::assertSame(['CreatePostsTable', 'CreateUsersTable'], $rolledBack);
        self::assertFalse($this->tableExists('users'));
        self::assertFalse($this->tableExists('posts'));

        self::assertSame(['CreateUsersTable', 'CreatePostsTable'], $this->manager->migrate());
        self::assertSame([], $this->manager->migrate());
    }

    public function testRollbackRemovesOnlyTheLatestMigrationFirst(): void
    {
        $this->manager->migrate();

        self::assertSame(['CreatePostsTable'], $this->manager->rollbackLast(true));
        self::assertTrue($this->tableExists('users'));
        self::assertFalse($this->tableExists('posts'));
        self::assertSame(['CreatePostsTable'], $this->manager->migrate());
    }

    public function testRollbackRequiresExplicitConfirmation(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->manager->rollback();
    }

    public function testDriverDdlContainsExpectedConstraintsWithoutLiveDatabase(): void
    {
        foreach ([
            'mysql' => ['ON DELETE SET NULL', 'email VARCHAR(100) NULL UNIQUE'],
            'pgsql' => ['ON DELETE SET NULL', 'email VARCHAR(100) NULL UNIQUE'],
            'sqlsrv' => ['ON DELETE SET NULL', 'email NVARCHAR(100) NULL UNIQUE'],
        ] as $driver => $expected) {
            $pdo = new MigrationRecordingPdo($driver);
            new MigrationManager($pdo);
            (new \CreateUsersTable($pdo))->up();
            (new \CreatePostsTable($pdo))->up();
            self::assertStringContainsString('email', $pdo->sql[1]);
            self::assertStringContainsString($expected[1], $pdo->sql[1]);
            self::assertStringContainsString($expected[0], $pdo->sql[2]);
        }
    }

    private function tableExists(string $tableName): bool
    {
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM sqlite_master WHERE type = :type AND name = :name');
        $statement->execute(['type' => 'table', 'name' => $tableName]);
        $tableCount = $statement->fetchColumn();

        return (int) $tableCount === 1;
    }
}

final class MigrationRecordingPdo extends PDO
{
    /** @var list<string> */
    public array $sql = [];

    public function __construct(private string $driver) {}

    public function getAttribute(int $attribute): mixed
    {
        return $this->driver;
    }

    public function exec(string $statement): int|false
    {
        $this->sql[] = $statement;
        return 0;
    }
}
