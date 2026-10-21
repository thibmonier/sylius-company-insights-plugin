<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyInsightsPlugin\Monthly;

use Doctrine\DBAL\Connection;
use Tmonier\SyliusCompanyInsightsPlugin\Statistics\CountedOrderSql;
use Webmozart\Assert\Assert;

/**
 * One query for all the months. The month of an order is found by comparing its date with the
 * boundaries of the months, computed in the timezone of the report: no timezone table is needed
 * in the database and a change of summer time is followed. Every value is a bound parameter.
 */
final readonly class DbalMonthlyPurchaseStatistics implements MonthlyPurchaseStatisticsInterface
{
    public function __construct(
        private Connection $connection,
        private CountedOrderSql $sql,
    ) {
    }

    public function perMonth(int $companyId, array $months): array
    {
        Assert::notEmpty($months);
        $parameters = ['company' => $companyId, 'from' => CountedOrderSql::storedDate($months[0]->from), 'to' => CountedOrderSql::storedDate($months[array_key_last($months)]->to)];
        $cases = '';
        foreach (array_slice($months, 1, null, true) as $index => $month) {
            $cases .= sprintf(' WHEN o.checkout_completed_at < :month%1$d THEN %2$d', $index, $index - 1);
            $parameters['month' . $index] = CountedOrderSql::storedDate($month->from);
        }

        $rows = $this->connection->fetchAllAssociative(sprintf(
            'SELECT o.currency_code AS currency_code, CASE%s ELSE %d END AS month_index, COUNT(o.id) AS orders_count, COALESCE(SUM(%s), 0) AS spend FROM %s WHERE %s AND o.checkout_completed_at >= :from AND o.checkout_completed_at < :to GROUP BY o.currency_code, month_index ORDER BY o.currency_code, month_index',
            $cases,
            count($months) - 1,
            $this->sql->netTotal(),
            CountedOrderSql::FROM,
            $this->sql->counted(),
        ), $parameters);

        return array_map(static function (array $row): MonthlyTotals {
            Assert::string($row['currency_code']);
            Assert::numeric($row['month_index']);
            Assert::numeric($row['orders_count']);
            Assert::numeric($row['spend']);

            return new MonthlyTotals($row['currency_code'], (int) $row['month_index'], (int) $row['orders_count'], (int) $row['spend']);
        }, $rows);
    }
}
