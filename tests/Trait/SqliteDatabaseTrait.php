<?php

declare(strict_types=1);

namespace Contenir\Commerce\Tests\Trait;

use Contenir\Commerce\Tests\TestAsset\Database\Schema;
use Contenir\Db\Model\EntityManager;
use PDO;
use PhpDb\Adapter\Adapter;
use PhpDb\Sqlite\AdapterPlatform;
use PhpDb\Sqlite\Pdo\Connection;
use PhpDb\Sqlite\Pdo\Driver;

use function array_keys;
use function array_map;
use function implode;
use function is_array;
use function sprintf;

/**
 * A fresh in-memory SQLite database with the commerce tables per test, so
 * nothing survives between tests. Call setUpDatabase() from setUp().
 */
trait SqliteDatabaseTrait
{
    private Adapter $adapter;

    private EntityManager $em;

    private PDO $pdo;

    /**
     * Read one column of one row straight from the database, bypassing the
     * entity manager.
     */
    private function column(string $table, string $column, string $keyColumn, int $key): mixed
    {
        $statement = $this->pdo->prepare(sprintf('SELECT %s FROM %s WHERE %s = :key', $column, $table, $keyColumn));
        $statement->execute(['key' => $key]);

        return $statement->fetchColumn();
    }

    /**
     * @param array<string, int|string|null> $row
     */
    private function insert(string $table, array $row): void
    {
        $statement = $this->pdo->prepare(sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(', ', array_keys($row)),
            implode(', ', array_map(static fn(string $column): string => ":{$column}", array_keys($row))),
        ));
        $statement->execute($row);
    }

    /**
     * One stored row, straight from the database, bypassing the entity
     * manager; an empty array when there is no such row.
     *
     * @return array<string, mixed>
     */
    private function row(string $table, string $keyColumn, int $key): array
    {
        $statement = $this->pdo->prepare(sprintf('SELECT * FROM %s WHERE %s = :key', $table, $keyColumn));
        $statement->execute(['key' => $key]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : [];
    }

    private function rowCount(string $table): int
    {
        return (int) $this->pdo->query(sprintf('SELECT COUNT(*) FROM %s', $table))->fetchColumn();
    }

    private function setUpDatabase(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        foreach (Schema::create() as $statement) {
            $this->pdo->exec($statement);
        }

        $driver        = new Driver(connection: new Connection($this->pdo));
        $this->adapter = new Adapter($driver, new AdapterPlatform($driver));
        $this->em      = new EntityManager($this->adapter);
    }

    /**
     * Change a stored row behind the entity manager's back, as another
     * request would.
     */
    private function updateBehindTheManager(string $sql): void
    {
        $this->pdo->exec($sql);
    }
}
