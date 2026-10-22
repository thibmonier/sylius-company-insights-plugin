<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyInsightsPlugin\Ranking;

use Tmonier\SyliusCompanyInsightsPlugin\Statistics\CountedOrderSql;
use Webmozart\Assert\Assert;

/**
 * Spend of the customers who ordered for the company. The member is looked up in THIS company only;
 * a customer who is no longer an enabled member of it is reported as not active. Only the names are read.
 */
final readonly class DbalBuyerRanking implements BuyerRankingInterface
{
    use RankingQueryTrait;

    public function rows(RankingScope $scope, PageRequest $page): array
    {
        $rows = $this->connection->fetchAllAssociative(
            sprintf('%s ORDER BY spend DESC, c.id ASC LIMIT %d OFFSET %d', $this->grouped($scope), $page->perPage, $page->offset()),
            $this->parameters($scope),
        );

        return array_map(static function (array $row): BuyerRow {
            Assert::nullOrString($row['first_name']);
            Assert::nullOrString($row['last_name']);
            Assert::numeric($row['active']);
            Assert::numeric($row['orders_count']);
            Assert::numeric($row['spend']);

            return new BuyerRow($row['first_name'], $row['last_name'], 1 === (int) $row['active'], (int) $row['orders_count'], (int) $row['spend']);
        }, $rows);
    }

    public function totals(RankingScope $scope): RankingTotals
    {
        return $this->totalsOf($this->grouped($scope), $this->parameters($scope));
    }

    private function grouped(RankingScope $scope): string
    {
        return sprintf(
            'SELECT c.id AS customer_id, c.first_name AS first_name, c.last_name AS last_name, CASE WHEN m.enabled THEN 1 ELSE 0 END AS active, COUNT(o.id) AS orders_count, SUM(%s) AS spend FROM %s LEFT JOIN sylius_customer c ON c.id = o.customer_id LEFT JOIN tmonier_company_member m ON m.customer_id = o.customer_id AND m.company_id = co.company_id WHERE %s GROUP BY c.id, c.first_name, c.last_name, m.enabled',
            $this->sql->netTotal(),
            CountedOrderSql::FROM,
            $this->where($scope),
        );
    }
}
