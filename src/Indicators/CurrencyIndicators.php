<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyInsightsPlugin\Indicators;

/** Tax-exclusive spend and average basket in one currency (minor units). */
final readonly class CurrencyIndicators
{
    public function __construct(
        public string $currencyCode,
        public Indicator $spend,
        public Indicator $averageBasket,
    ) {
    }
}
