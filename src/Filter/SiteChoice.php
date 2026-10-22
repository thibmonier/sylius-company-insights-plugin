<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyInsightsPlugin\Filter;

final readonly class SiteChoice
{
    public function __construct(
        public int $addressId,
        public string $label,
    ) {
    }
}
