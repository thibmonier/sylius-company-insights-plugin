<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyInsightsPlugin\Filter;

use Doctrine\DBAL\Connection;
use Webmozart\Assert\Assert;

final readonly class DbalFilterChoices implements FilterChoicesInterface
{
    public function __construct(private Connection $connection)
    {
    }

    public function members(int $companyId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT m.customer_id AS customer_id, c.first_name AS first_name, c.last_name AS last_name FROM tmonier_company_member m INNER JOIN sylius_customer c ON c.id = m.customer_id WHERE m.company_id = :company ORDER BY c.last_name, c.first_name, m.customer_id',
            ['company' => $companyId],
        );

        return array_map(static function (array $row): MemberChoice {
            Assert::numeric($row['customer_id']);
            Assert::nullOrString($row['first_name']);
            Assert::nullOrString($row['last_name']);

            return new MemberChoice((int) $row['customer_id'], trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')));
        }, $rows);
    }

    public function sites(int $companyId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT ca.id AS id, ca.label AS label FROM tmonier_company_address ca WHERE ca.company_id = :company ORDER BY ca.label, ca.id',
            ['company' => $companyId],
        );

        return array_map(static function (array $row): SiteChoice {
            Assert::numeric($row['id']);
            Assert::nullOrString($row['label']);

            return new SiteChoice((int) $row['id'], $row['label'] ?? '');
        }, $rows);
    }
}
