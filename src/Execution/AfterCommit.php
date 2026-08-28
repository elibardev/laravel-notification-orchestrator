<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Execution;

use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\DatabaseTransactionsManager;

final class AfterCommit
{
    public function __construct(private DatabaseManager $database, private DatabaseTransactionsManager $transactions) {}

    public function run(callable $work): void
    {
        $this->runOn($this->database->connection(), $work);
    }

    public function runOn(Connection $connection, callable $work): void
    {
        if ($connection->transactionLevel() > 0) {
            // Laravel's global addCallback() targets the last transaction across all
            // connections. Attach to the dispatch connection's exact native record.
            $record = $this->transactions->getPendingTransactions()->last(
                fn ($record) => $record->connection === $connection->getName() && $record->level === $connection->transactionLevel(),
            );
            if ($record === null) {
                throw new \LogicException('No native transaction record for the dispatch connection.');
            }
            $record->addCallback($work);
        } else {
            $work();
        }
    }
}
