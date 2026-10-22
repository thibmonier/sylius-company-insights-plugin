<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyInsightsPlugin\Filter;

use Tmonier\SyliusCompanyInsightsPlugin\Period\PeriodComparison;
use Tmonier\SyliusCompanyInsightsPlugin\Period\PeriodKey;

/**
 * What the member asked to see: the period (and the one before), the filter, and the parameters of
 * the query that carry it to the next link (period, dates, member, site), already validated.
 */
final readonly class Selection
{
    /** @param array<string, string> $query */
    public function __construct(
        public PeriodComparison $periods,
        public PurchaseFilter $filter,
        public array $query,
    ) {
    }

    /** The member and the site, without the period: what stays when the period changes. */
    public function filterQuery(): array
    {
        return array_diff_key($this->query, ['period' => true, 'from' => true, 'to' => true]);
    }

    public function isCustom(): bool
    {
        return PeriodKey::Custom === $this->periods->key;
    }
}
