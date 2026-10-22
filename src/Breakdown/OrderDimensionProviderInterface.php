<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyInsightsPlugin\Breakdown;

/**
 * An analytical axis of the purchases of a company (cost centre, project, department...), supplied by
 * the application: the plugin ships none. Tag the service `tmonier_company_insights.order_dimension_provider`
 * (autoconfigured when the service implements this interface). Without any provider, no block is shown.
 */
interface OrderDimensionProviderInterface
{
    /** Stable identifier of the axis (letters, digits, dashes), used in the markup. */
    public function code(): string;

    /** Title of the block: a translation key of the application or a plain text, both passed to the translator. */
    public function label(): string;

    /**
     * The value of the axis for each order, in one call for all of them.
     *
     * @param list<int> $orderIds
     *
     * @return array<int, string|null> order id => value; an order missing from the result, null or blank has no value
     */
    public function values(array $orderIds): array;
}
