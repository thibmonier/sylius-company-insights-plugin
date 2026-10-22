<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusCompanyInsightsPlugin\Integration;

use Tmonier\SyliusCompanyInsightsPlugin\Breakdown\OrderReferenceProviderInterface;

final readonly class FakeReferenceProvider implements OrderReferenceProviderInterface
{
    /** @param array<int, string|null> $references */
    public function __construct(private array $references)
    {
    }

    public function references(array $orderIds): array
    {
        return array_intersect_key($this->references, array_flip($orderIds));
    }
}
