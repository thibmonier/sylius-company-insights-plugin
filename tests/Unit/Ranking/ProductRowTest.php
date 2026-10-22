<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusCompanyInsightsPlugin\Unit\Ranking;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tmonier\SyliusCompanyInsightsPlugin\Ranking\ProductRow;

final class ProductRowTest extends TestCase
{
    #[DataProvider('provideNames')]
    public function testTheNameShowsTheVariantOnlyWhenItSaysSomethingMore(?string $product, ?string $variant, string $expected): void
    {
        $row = new ProductRow('GLOVES-M', $product, $variant, 1, 100, new \DateTimeImmutable());

        self::assertSame($expected, $row->displayName());
    }

    /** @return iterable<string, array{?string, ?string, string}> */
    public static function provideNames(): iterable
    {
        yield 'product and variant' => ['Gloves', 'Size M', 'Gloves - Size M'];
        yield 'variant named like its product' => ['Gloves', 'Gloves', 'Gloves'];
        yield 'same name but for the case and spaces' => ['Gloves', ' gloves ', 'Gloves'];
        yield 'no variant name' => ['Gloves', null, 'Gloves'];
        yield 'empty variant name' => ['Gloves', '', 'Gloves'];
        yield 'no product name' => [null, 'Size M', 'Size M'];
        yield 'no translation at all: the code' => [null, null, 'GLOVES-M'];
        yield 'blank names: the code' => ['  ', '', 'GLOVES-M'];
    }
}
