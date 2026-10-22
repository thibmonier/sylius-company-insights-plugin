<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyInsightsPlugin\Breakdown;

final readonly class CurrencyBreakdowns
{
    /** @param list<Breakdown> $dimensions one per analytical axis supplied by the application */
    public function __construct(
        public string $currencyCode,
        public Breakdown $sites,
        public array $dimensions,
    ) {
    }

    public function hasDimensions(): bool
    {
        return [] !== $this->dimensions;
    }
}
