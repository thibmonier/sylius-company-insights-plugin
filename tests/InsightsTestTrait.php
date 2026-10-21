<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusCompanyInsightsPlugin;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

/** Every test runs in a transaction rolled back at the end. */
trait InsightsTestTrait
{
    private ?TestCatalog $testCatalog = null;

    private function beginTestTransaction(): void
    {
        $this->connection()->beginTransaction();
    }

    private function rollBackTestTransaction(): void
    {
        if ($this->connection()->isTransactionActive()) {
            $this->connection()->rollBack();
        }
        $this->testCatalog = null;
    }

    private function catalog(): TestCatalog
    {
        return $this->testCatalog ??= new TestCatalog(self::getContainer());
    }

    private function entityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');

        return $entityManager;
    }

    private function connection(): Connection
    {
        return $this->entityManager()->getConnection();
    }
}
