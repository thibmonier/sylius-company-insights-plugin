<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyInsightsPlugin\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Tmonier\SyliusCompanyInsightsPlugin\Doctrine\CompanyOrderIndexListener;

/**
 * Additive: one index on the company orders table of tmonier/sylius-company-accounts-plugin,
 * which lists the orders of a company without reading the table (company, order). Schema API (not
 * raw SQL) so that it runs on MySQL, MariaDB and PostgreSQL.
 */
final class Version20261021100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Index the orders of a company for the purchasing dashboard (tmonier/sylius-company-insights-plugin).';
    }

    public function up(Schema $schema): void
    {
        $schema->getTable('tmonier_company_order')->addIndex(CompanyOrderIndexListener::COLUMNS, CompanyOrderIndexListener::INDEX_NAME);
    }

    public function down(Schema $schema): void
    {
        $schema->getTable('tmonier_company_order')->dropIndex(CompanyOrderIndexListener::INDEX_NAME);
    }
}
