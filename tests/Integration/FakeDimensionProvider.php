<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusCompanyInsightsPlugin\Integration;

use Tmonier\SyliusCompanyInsightsPlugin\Breakdown\OrderDimensionProviderInterface;

final readonly class FakeDimensionProvider implements OrderDimensionProviderInterface
{
    /** @param array<int, string|null> $values */
    public function __construct(private array $values, private bool $failing = false)
    {
    }

    public function code(): string
    {
        return 'cost-center';
    }

    public function label(): string
    {
        return 'Cost centre';
    }

    public function values(array $orderIds): array
    {
        if ($this->failing) {
            throw new \RuntimeException('The provider is down.');
        }

        return array_intersect_key($this->values, array_flip($orderIds));
    }
}
