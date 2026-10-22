<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyInsightsPlugin\Breakdown;

/**
 * The customer reference of an order (purchase order number...) for the "PO reference" column of the CSV export,
 * supplied by the application. Tag the service `tmonier_company_insights.order_reference_provider` (autoconfigured
 * when the service implements this interface). With several providers, each order takes the first non-empty reference in the order of the services
 * (the `priority` of the tag, highest first); a provider is only asked for the orders the previous ones left
 * without reference. Without any, the column is empty.
 */
interface OrderReferenceProviderInterface
{
    /**
     * @param list<int> $orderIds
     *
     * @return array<int, string|null> order id => reference
     */
    public function references(array $orderIds): array;
}
