<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyInsightsPlugin\Ranking;

/** A main category of the ranking; no code for the products that have none. */
final readonly class CategoryRow
{
    public function __construct(
        public ?string $code,
        public ?string $name,
        public int $spend,
    ) {
    }
}
