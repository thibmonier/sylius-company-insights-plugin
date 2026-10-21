<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusCompanyInsightsPlugin\Integration;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Tmonier\SyliusCompanyInsightsPlugin\Doctrine\CompanyOrderIndexListener;
use Tmonier\SyliusCompanyInsightsPlugin\Migrations\Version20261021100000;

/**
 * Not in a transaction: DDL statements are not rolled back on MySQL. The index is dropped and
 * created again, and is back when the test ends.
 */
final class MigrationTest extends KernelTestCase
{
    public function testTheIndexGoesDownAndUpAgain(): void
    {
        self::bootKernel();
        self::assertTrue($this->hasIndex(), 'The test database is migrated.');

        try {
            $this->execute('down');
            self::assertFalse($this->hasIndex(), 'Down drops the index.');

            $this->execute('up');
            self::assertTrue($this->hasIndex(), 'Up creates the index.');
        } finally {
            if (!$this->hasIndex()) {
                $this->execute('up');
            }
        }
    }

    public function testTheIndexIsPartOfTheMappingSoThatSchemaDiffsKeepIt(): void
    {
        self::bootKernel();
        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        self::assertInstanceOf(\Doctrine\ORM\EntityManagerInterface::class, $entityManager);
        $companyOrder = self::getContainer()->getParameter('tmonier_company_accounts.model.company_order.class');
        self::assertIsString($companyOrder);

        $metadata = $entityManager->getClassMetadata($companyOrder);

        self::assertSame(['columns' => CompanyOrderIndexListener::COLUMNS], $metadata->table['indexes'][CompanyOrderIndexListener::INDEX_NAME]);
    }

    private function execute(string $direction): void
    {
        $application = new Application(self::$kernel);
        $application->setAutoExit(false);
        $exit = $application->run(
            new ArrayInput(['command' => 'doctrine:migrations:execute', 'versions' => [Version20261021100000::class], '--' . $direction => true, '--no-interaction' => true]),
            new NullOutput(),
        );
        self::assertSame(0, $exit);
    }

    private function hasIndex(): bool
    {
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        self::assertInstanceOf(Connection::class, $connection);
        $indexes = $connection->createSchemaManager()->listTableIndexes('tmonier_company_order');

        return array_key_exists(CompanyOrderIndexListener::INDEX_NAME, $indexes);
    }
}
