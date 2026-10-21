<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyInsightsPlugin\Ranking;

use Doctrine\DBAL\Connection;
use Tmonier\SyliusCompanyInsightsPlugin\Statistics\CountedOrderSql;
use Webmozart\Assert\Assert;

/**
 * What the three rankings share: the counted orders of ONE company in the period and the currency
 * (the same orders as the indicators), every value a bound parameter, and the totals of a grouped query.
 */
trait RankingQueryTrait
{
    public function __construct(
        private readonly Connection $connection,
        private readonly CountedOrderSql $sql,
        private readonly ?string $timezone = null,
    ) {
    }

    /** The WHERE of the counted orders of the scope; its parameters are given by {@see self::parameters()}. */
    private function where(): string
    {
        return $this->sql->counted() . ' AND o.checkout_completed_at >= :from AND o.checkout_completed_at < :to AND o.currency_code = :currency';
    }

    /** @return array<string, int|string> */
    private function parameters(RankingScope $scope): array
    {
        return [
            'company' => $scope->companyId,
            'from' => CountedOrderSql::storedDate($scope->period->from),
            'to' => CountedOrderSql::storedDate($scope->period->to),
            'currency' => $scope->currencyCode,
        ];
    }

    /** @param array<string, int|string> $parameters */
    private function totalsOf(string $grouped, array $parameters): RankingTotals
    {
        $row = $this->connection->fetchAssociative(sprintf('SELECT COUNT(*) AS rows_count, COALESCE(SUM(g.spend), 0) AS spend FROM (%s) g', $grouped), $parameters);
        Assert::isArray($row);
        Assert::numeric($row['rows_count']);
        Assert::numeric($row['spend']);

        return new RankingTotals((int) $row['rows_count'], (int) $row['spend']);
    }
}
