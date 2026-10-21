<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyInsightsPlugin\Statistics;

use Doctrine\DBAL\Connection;
use Tmonier\SyliusCompanyInsightsPlugin\Approval\PendingOrderConditionInterface;
use Tmonier\SyliusCompanyInsightsPlugin\Period\Period;
use Webmozart\Assert\Assert;

/**
 * Aggregates computed by the database (no entity is loaded), on plain SQL that runs on MySQL,
 * MariaDB and PostgreSQL. Every value goes through a bound parameter.
 *
 * Amounts are tax exclusive: the total of the order minus its tax adjustments, wherever Sylius
 * records them (order, unit of an item, shipment), included in the price or not.
 */
final readonly class DbalPurchaseStatistics implements PurchaseStatisticsInterface
{
    private const TAX_OF_ORDER = <<<'SQL'
        (SELECT COALESCE(SUM(a.amount), 0) FROM sylius_adjustment a WHERE a.type = 'tax' AND a.order_id = o.id)
        + (SELECT COALESCE(SUM(a.amount), 0) FROM sylius_adjustment a INNER JOIN sylius_order_item i ON a.order_item_id = i.id WHERE a.type = 'tax' AND i.order_id = o.id)
        + (SELECT COALESCE(SUM(a.amount), 0) FROM sylius_adjustment a INNER JOIN sylius_order_item_unit u ON a.order_item_unit_id = u.id INNER JOIN sylius_order_item i ON u.order_item_id = i.id WHERE a.type = 'tax' AND i.order_id = o.id)
        + (SELECT COALESCE(SUM(a.amount), 0) FROM sylius_adjustment a INNER JOIN sylius_shipment s ON a.shipment_id = s.id WHERE a.type = 'tax' AND s.order_id = o.id)
        SQL;

    public function __construct(
        private Connection $connection,
        private PendingOrderConditionInterface $pendingOrderCondition,
    ) {
    }

    public function counted(int $companyId, Period $period): PeriodStatistics
    {
        $where = $this->where($this->pendingOrderCondition->sql('o'), false);
        $parameters = $this->parameters($companyId, $period);

        $totals = [];
        foreach ($this->totalsPerCurrency($where, $parameters) as $row) {
            $totals[$row->currencyCode] = $row;
        }
        $buyers = $this->connection->fetchOne(sprintf('SELECT COUNT(DISTINCT o.customer_id) FROM sylius_order o INNER JOIN tmonier_company_order co ON co.order_id = o.id WHERE %s', $where), $parameters);

        Assert::numeric($buyers);

        return new PeriodStatistics($totals, (int) $buyers);
    }

    public function pending(int $companyId, Period $period): array
    {
        $condition = $this->pendingOrderCondition->sql('o');
        if (null === $condition) {
            return [];
        }

        return $this->totalsPerCurrency($this->where($condition, true), $this->parameters($companyId, $period));
    }

    /**
     * @param array<string, int|string> $parameters
     *
     * @return list<CurrencyTotals>
     */
    private function totalsPerCurrency(string $where, array $parameters): array
    {
        $rows = $this->connection->fetchAllAssociative(sprintf(
            'SELECT o.currency_code AS currency_code, COUNT(o.id) AS orders_count, COALESCE(SUM(o.total - (%s)), 0) AS spend FROM sylius_order o INNER JOIN tmonier_company_order co ON co.order_id = o.id WHERE %s GROUP BY o.currency_code ORDER BY o.currency_code',
            self::TAX_OF_ORDER,
            $where,
        ), $parameters);

        return array_map(static function (array $row): CurrencyTotals {
            Assert::string($row['currency_code']);
            Assert::numeric($row['orders_count']);
            Assert::numeric($row['spend']);

            return new CurrencyTotals($row['currency_code'], (int) $row['orders_count'], (int) $row['spend']);
        }, $rows);
    }

    private function where(?string $pendingCondition, bool $pendingOnly): string
    {
        $where = "co.company_id = :company AND o.checkout_state = 'completed' AND o.state <> 'cancelled' AND o.checkout_completed_at >= :from AND o.checkout_completed_at < :to";
        if (null === $pendingCondition) {
            return $where;
        }

        return $where . ($pendingOnly ? ' AND ' . $pendingCondition : ' AND NOT ' . $pendingCondition);
    }

    /** @return array<string, int|string> */
    private function parameters(int $companyId, Period $period): array
    {
        return [
            'company' => $companyId,
            'from' => $period->from->format('Y-m-d H:i:s'),
            'to' => $period->to->format('Y-m-d H:i:s'),
        ];
    }
}
