<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyInsightsPlugin\Statistics;

use Tmonier\SyliusCompanyInsightsPlugin\Approval\PendingOrderConditionInterface;

/**
 * The SQL fragments every aggregate shares, so that the indicators and the monthly chart count
 * exactly the same orders. Plain SQL that runs on MySQL, MariaDB and PostgreSQL; the company and
 * the dates are bound parameters (`:company`, `:from`, `:to`).
 *
 * Amounts are tax exclusive: the total of the order minus its tax adjustments, wherever Sylius
 * records them (order, unit of an item, shipment), included in the price or not.
 */
final readonly class CountedOrderSql
{
    public const FROM = 'sylius_order o INNER JOIN tmonier_company_order co ON co.order_id = o.id';

    private const TAX_OF_ORDER = <<<'SQL'
        (SELECT COALESCE(SUM(a.amount), 0) FROM sylius_adjustment a WHERE a.type = 'tax' AND a.order_id = o.id)
        + (SELECT COALESCE(SUM(a.amount), 0) FROM sylius_adjustment a INNER JOIN sylius_order_item i ON a.order_item_id = i.id WHERE a.type = 'tax' AND i.order_id = o.id)
        + (SELECT COALESCE(SUM(a.amount), 0) FROM sylius_adjustment a INNER JOIN sylius_order_item_unit u ON a.order_item_unit_id = u.id INNER JOIN sylius_order_item i ON u.order_item_id = i.id WHERE a.type = 'tax' AND i.order_id = o.id)
        + (SELECT COALESCE(SUM(a.amount), 0) FROM sylius_adjustment a INNER JOIN sylius_shipment s ON a.shipment_id = s.id WHERE a.type = 'tax' AND s.order_id = o.id)
        SQL;

    private const ORDERS_OF_COMPANY = "co.company_id = :company AND o.checkout_state = 'completed' AND o.state <> 'cancelled'";

    public function __construct(private PendingOrderConditionInterface $pendingOrderCondition)
    {
    }

    /** The tax-exclusive total of an order. */
    public function netTotal(): string
    {
        return 'o.total - (' . self::TAX_OF_ORDER . ')';
    }

    /** Completed, not cancelled and not waiting for approval; the period is up to the caller. */
    public function counted(): string
    {
        $pending = $this->pendingOrderCondition->sql('o');

        return null === $pending ? self::ORDERS_OF_COMPANY : self::ORDERS_OF_COMPANY . ' AND NOT ' . $pending;
    }

    /** Orders waiting for approval, or null when nothing can wait. */
    public function pending(): ?string
    {
        $pending = $this->pendingOrderCondition->sql('o');

        return null === $pending ? null : self::ORDERS_OF_COMPANY . ' AND ' . $pending;
    }

    /** A date as the database holds it: in the default timezone of PHP, which Doctrine writes. */
    public static function storedDate(\DateTimeImmutable $date): string
    {
        return $date->setTimezone(new \DateTimeZone(date_default_timezone_get()))->format('Y-m-d H:i:s');
    }
}
