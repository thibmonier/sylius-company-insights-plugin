<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyInsightsPlugin\Ranking;

/** A variant of the ranking. The names are null when there is no translation in the locale: the code is shown then. */
final readonly class ProductRow
{
    public function __construct(
        public string $code,
        public ?string $productName,
        public ?string $variantName,
        public int $quantity,
        public int $spend,
        public \DateTimeImmutable $lastPurchase,
    ) {
    }

    /** Name of the timezone the date is in, to format it without moving it. */
    public function timezoneName(): string
    {
        return $this->lastPurchase->getTimezone()->getName();
    }
}
