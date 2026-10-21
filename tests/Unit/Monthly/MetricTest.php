<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusCompanyInsightsPlugin\Unit\Monthly;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tmonier\SyliusCompanyInsightsPlugin\Monthly\Metric;

final class MetricTest extends TestCase
{
    #[DataProvider('values')]
    public function testOnlyAnExactKnownValueIsKept(mixed $value, Metric $expected): void
    {
        self::assertSame($expected, Metric::fromRequest($value));
    }

    /** @return iterable<string, array{mixed, Metric}> */
    public static function values(): iterable
    {
        yield 'orders' => ['orders', Metric::Orders];
        yield 'amount' => ['amount', Metric::Amount];
        yield 'missing' => [null, Metric::Amount];
        yield 'unknown' => ['revenue', Metric::Amount];
        yield 'case' => ['ORDERS', Metric::Amount];
        yield 'injection' => ["orders' OR 1=1", Metric::Amount];
        yield 'array' => [['orders'], Metric::Amount];
        yield 'empty' => ['', Metric::Amount];
    }
}
