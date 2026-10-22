<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyInsightsPlugin\Ranking;

use Tmonier\SyliusCompanyInsightsPlugin\Filter\PurchaseFilter;
use Tmonier\SyliusCompanyInsightsPlugin\Period\Period;

/** What a ranking is about: ONE company, one period, one currency (amounts are never mixed) and the locale of the names. */
final readonly class RankingScope
{
    public function __construct(
        public int $companyId,
        public Period $period,
        public string $currencyCode,
        public string $localeCode,
        public PurchaseFilter $filter = new PurchaseFilter(),
    ) {
    }
}
