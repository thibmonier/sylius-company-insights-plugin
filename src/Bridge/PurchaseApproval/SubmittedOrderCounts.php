<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyInsightsPlugin\Bridge\PurchaseApproval;

use Doctrine\DBAL\Connection;
use Tmonier\SyliusCompanyInsightsPlugin\Period\Period;
use Tmonier\SyliusCompanyInsightsPlugin\Statistics\CountedOrderSql;
use Webmozart\Assert\Assert;

/**
 * How many orders each member sent through the checkout in the period, approval or not and whatever
 * became of them: the base on which "this member's orders need an approval" is measured. Only the
 * tables of the plugin (orders of a company), nothing of purchase approval.
 */
final readonly class SubmittedOrderCounts
{
    public function __construct(
        private Connection $connection,
        private CountedOrderSql $sql,
    ) {
    }

    /** @return array<int, int> customer id => number of orders */
    public function perCustomer(int $companyId, Period $period): array
    {
        $rows = $this->connection->fetchAllAssociative(
            sprintf('SELECT o.customer_id AS customer_id, COUNT(o.id) AS orders_count FROM %s WHERE %s AND o.checkout_completed_at >= :from AND o.checkout_completed_at < :to GROUP BY o.customer_id', CountedOrderSql::FROM, $this->sql->submitted()),
            ['company' => $companyId, 'from' => CountedOrderSql::storedDate($period->from), 'to' => CountedOrderSql::storedDate($period->to)],
        );

        $counts = [];
        foreach ($rows as $row) {
            Assert::numeric($row['customer_id']);
            Assert::numeric($row['orders_count']);
            $counts[(int) $row['customer_id']] = (int) $row['orders_count'];
        }

        return $counts;
    }
}
