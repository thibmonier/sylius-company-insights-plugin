<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyInsightsPlugin\Bridge\PurchaseApproval;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Tmonier\SyliusCompanyInsightsPlugin\Period\Period;
use Tmonier\SyliusCompanyInsightsPlugin\Statistics\CountedOrderSql;
use Webmozart\Assert\Assert;

/**
 * SQL aggregates on the table of tmonier/sylius-purchase-approval-plugin
 * (`tmonier_purchase_approval_order_approval`, columns `state`, `created_at`, `decided_at`, `company_id`,
 * `order_id`), joined to the order. All of them are for ONE company, bound as a parameter, with no limit
 * but the explicit one of the list of the oldest orders.
 *
 * Amounts are tax exclusive, with the expression of {@see CountedOrderSql} (the amount stored on the
 * approval is tax included). The period of the decisions and of the buyers is the one of the checkout of the
 * order (`checkout_completed_at`), like every other figure of the dashboard, not the date of the request.
 * Runs on MySQL, MariaDB and PostgreSQL: the only platform-specific SQL is the difference of two dates.
 */
final readonly class DbalApprovalStatistics
{
    private const FROM = 'tmonier_purchase_approval_order_approval a INNER JOIN sylius_order o ON o.id = a.order_id LEFT JOIN sylius_customer c ON c.id = o.customer_id';

    private const DECISION = "a.state IN ('approved', 'rejected') AND a.decided_at IS NOT NULL";

    private const PERIOD = 'o.checkout_completed_at >= :from AND o.checkout_completed_at < :to';

    public function __construct(
        private Connection $connection,
        private CountedOrderSql $sql,
    ) {
    }

    /** @return array{count: int, amounts: array<string, int>} the requests waiting now, whatever their date */
    public function pendingTotals(int $companyId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            sprintf("SELECT o.currency_code AS currency, COUNT(a.id) AS requests, SUM(%s) AS amount FROM %s WHERE a.company_id = :company AND a.state = 'pending' GROUP BY o.currency_code ORDER BY o.currency_code", $this->sql->netTotal(), self::FROM),
            ['company' => $companyId],
        );

        $count = 0;
        $amounts = [];
        foreach ($rows as $row) {
            Assert::numeric($row['requests']);
            Assert::numeric($row['amount']);
            Assert::string($row['currency']);
            $count += (int) $row['requests'];
            $amounts[$row['currency']] = (int) $row['amount'];
        }

        return ['count' => $count, 'amounts' => $amounts];
    }

    /** @return list<array{number: string, buyer: string, amount: int, currency: string, createdAt: \DateTimeImmutable}> the oldest requests waiting, at most $limit */
    public function oldestPending(int $companyId, int $limit): array
    {
        Assert::positiveInteger($limit);
        $rows = $this->connection->fetchAllAssociative(
            sprintf("SELECT o.number AS number, c.first_name AS first_name, c.last_name AS last_name, %s AS amount, o.currency_code AS currency, a.created_at AS created_at FROM %s WHERE a.company_id = :company AND a.state = 'pending' ORDER BY a.created_at ASC, a.id ASC LIMIT %d", $this->sql->netTotal(), self::FROM, $limit),
            ['company' => $companyId],
        );

        $items = [];
        foreach ($rows as $row) {
            Assert::numeric($row['amount']);
            Assert::string($row['number']);
            Assert::string($row['currency']);
            Assert::string($row['created_at']);
            $items[] = [
                'number' => $row['number'],
                'buyer' => $this->name($row['first_name'], $row['last_name']),
                'amount' => (int) $row['amount'],
                'currency' => $row['currency'],
                'createdAt' => new \DateTimeImmutable($row['created_at']),
            ];
        }

        return $items;
    }

    /** @return array{decided: int, refused: int} approved and refused requests; expired and cancelled ones are not decisions */
    public function decisions(int $companyId, Period $period): array
    {
        $rows = $this->connection->fetchAllAssociative(
            sprintf('SELECT a.state AS state, COUNT(a.id) AS requests FROM %s WHERE a.company_id = :company AND %s AND %s GROUP BY a.state', self::FROM, self::DECISION, self::PERIOD),
            $this->parameters($companyId, $period),
        );

        $decided = 0;
        $refused = 0;
        foreach ($rows as $row) {
            Assert::numeric($row['requests']);
            $decided += (int) $row['requests'];
            $refused += 'rejected' === $row['state'] ? (int) $row['requests'] : 0;
        }

        return ['decided' => $decided, 'refused' => $refused];
    }

    /**
     * The median time to decide, in seconds: the one or two middle durations are read by OFFSET on the
     * ordered durations, so that nothing is loaded in memory and nothing is capped.
     */
    public function medianSecondsToDecide(int $companyId, Period $period, int $decided): ?int
    {
        if ($decided <= 0) {
            return null;
        }

        $offset = intdiv($decided - 1, 2);
        $middle = 1 === $decided % 2 ? 1 : 2;
        $durations = $this->connection->fetchFirstColumn(
            sprintf('SELECT %s AS seconds FROM %s WHERE a.company_id = :company AND %s AND %s ORDER BY seconds ASC LIMIT %d OFFSET %d', $this->secondsToDecide(), self::FROM, self::DECISION, self::PERIOD, $middle, $offset),
            $this->parameters($companyId, $period),
        );

        return ApprovalMetrics::median(array_map(static function (mixed $seconds): int {
            Assert::numeric($seconds);

            return (int) $seconds;
        }, $durations));
    }

    /** @return list<array{customerId: int, name: string, approvals: int}> every buyer with at least one request in the period, whatever its state */
    public function approvalsPerBuyer(int $companyId, Period $period): array
    {
        $rows = $this->connection->fetchAllAssociative(
            sprintf('SELECT o.customer_id AS customer_id, c.first_name AS first_name, c.last_name AS last_name, COUNT(a.id) AS requests FROM %s WHERE a.company_id = :company AND o.customer_id IS NOT NULL AND %s GROUP BY o.customer_id, c.first_name, c.last_name', self::FROM, self::PERIOD),
            $this->parameters($companyId, $period),
        );

        $buyers = [];
        foreach ($rows as $row) {
            Assert::numeric($row['customer_id']);
            Assert::numeric($row['requests']);
            $buyers[] = ['customerId' => (int) $row['customer_id'], 'name' => $this->name($row['first_name'], $row['last_name']), 'approvals' => (int) $row['requests']];
        }

        return $buyers;
    }

    /** The difference of two dates in whole seconds: the only SQL that differs between the platforms. */
    private function secondsToDecide(): string
    {
        return $this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform
            ? 'CAST(EXTRACT(EPOCH FROM (a.decided_at - a.created_at)) AS BIGINT)'
            : 'TIMESTAMPDIFF(SECOND, a.created_at, a.decided_at)';
    }

    /** @return array{company: int, from: string, to: string} */
    private function parameters(int $companyId, Period $period): array
    {
        return ['company' => $companyId, 'from' => CountedOrderSql::storedDate($period->from), 'to' => CountedOrderSql::storedDate($period->to)];
    }

    private function name(mixed $firstName, mixed $lastName): string
    {
        return trim((is_string($firstName) ? $firstName : '') . ' ' . (is_string($lastName) ? $lastName : ''));
    }
}
