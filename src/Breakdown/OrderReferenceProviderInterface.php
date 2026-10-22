<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyInsightsPlugin\Breakdown;

/**
 * The customer reference of an order (purchase order number...) for the "PO reference" column of the CSV export,
 * supplied by the application. Tag the service `tmonier_company_insights.order_reference_provider` (autoconfigured
 * when the service implements this interface). Only the first provider is used; without one, the column is empty.
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
