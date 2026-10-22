<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyInsightsPlugin\Filter;

final readonly class MemberChoice
{
    public function __construct(
        public int $customerId,
        public string $name,
    ) {
    }
}
