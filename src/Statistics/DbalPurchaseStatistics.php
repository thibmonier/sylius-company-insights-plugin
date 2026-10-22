<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyInsightsPlugin\Statistics;

use Doctrine\DBAL\Connection;
use Tmonier\SyliusCompanyInsightsPlugin\Filter\PurchaseFilter;
use Tmonier\SyliusCompanyInsightsPlugin\Period\Period;
use Webmozart\Assert\Assert;

/**
 * Aggregates computed by the database (no entity is loaded). Every value goes through a bound
 * parameter; the conditions are those of {@see CountedOrderSql}.
 */
final readonly class DbalPurchaseStatistics implements PurchaseStatisticsInterface
{
    private const PERIOD = 'o.checkout_completed_at >= :from AND o.checkout_completed_at < :to';

    public function __construct(
        private Connection $connection,
        private CountedOrderSql $sql,
    ) {
    }

    public function counted(int $companyId, Period $period, PurchaseFilter $filter = new PurchaseFilter()): PeriodStatistics
    {
        $where = $this->sql->counted() . ' AND ' . self::PERIOD . $filter->sql();
        $parameters = $this->parameters($companyId, $period, $filter);

        $totals = [];
        foreach ($this->totalsPerCurrency($where, $parameters) as $row) {
            $totals[$row->currencyCode] = $row;
        }
        $buyers = $this->connection->fetchOne(sprintf('SELECT COUNT(DISTINCT o.customer_id) FROM %s WHERE %s', CountedOrderSql::FROM, $where), $parameters);

        Assert::numeric($buyers);

        return new PeriodStatistics($totals, (int) $buyers);
    }

    public function pending(int $companyId, Period $period, PurchaseFilter $filter = new PurchaseFilter()): array
    {
        $condition = $this->sql->pending();
        if (null === $condition) {
            return [];
        }

        return $this->totalsPerCurrency($condition . ' AND ' . self::PERIOD . $filter->sql(), $this->parameters($companyId, $period, $filter));
    }

    /**
     * @param array<string, int|string> $parameters
     *
     * @return list<CurrencyTotals>
     */
    private function totalsPerCurrency(string $where, array $parameters): array
    {
        $rows = $this->connection->fetchAllAssociative(sprintf(
            'SELECT o.currency_code AS currency_code, COUNT(o.id) AS orders_count, COALESCE(SUM(%s), 0) AS spend FROM %s WHERE %s GROUP BY o.currency_code ORDER BY o.currency_code',
            $this->sql->netTotal(),
            CountedOrderSql::FROM,
            $where,
        ), $parameters);

        return array_map(static function (array $row): CurrencyTotals {
            Assert::string($row['currency_code']);
            Assert::numeric($row['orders_count']);
            Assert::numeric($row['spend']);

            return new CurrencyTotals($row['currency_code'], (int) $row['orders_count'], (int) $row['spend']);
        }, $rows);
    }

    /** @return array<string, int|string> */
    private function parameters(int $companyId, Period $period, PurchaseFilter $filter): array
    {
        return [
            'company' => $companyId,
            'from' => CountedOrderSql::storedDate($period->from),
            'to' => CountedOrderSql::storedDate($period->to),
            ...$filter->parameters(),
        ];
    }
}
