<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyInsightsPlugin\Statistics;

/** Orders and tax-exclusive spend (minor units) in one currency: amounts are never converted. */
final readonly class CurrencyTotals
{
    public function __construct(
        public string $currencyCode,
        public int $ordersCount,
        public int $spend,
    ) {
    }

    public function averageBasket(): int
    {
        return 0 === $this->ordersCount ? 0 : (int) round($this->spend / $this->ordersCount);
    }
}
