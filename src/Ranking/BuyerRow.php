<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyInsightsPlugin\Ranking;

/**
 * A buyer of the ranking. Only the first and the last name are carried, never the e-mail address.
 * `active` is false for a member deactivated after his orders (or no longer a member).
 */
final readonly class BuyerRow
{
    public function __construct(
        public ?string $firstName,
        public ?string $lastName,
        public bool $active,
        public int $ordersCount,
        public int $spend,
    ) {
    }
}
