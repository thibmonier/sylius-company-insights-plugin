<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusCompanyInsightsPlugin\Unit\Ranking;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tmonier\SyliusCompanyInsightsPlugin\Ranking\PageRequest;
use Tmonier\SyliusCompanyInsightsPlugin\Ranking\ProductSort;

final class PageRequestTest extends TestCase
{
    #[DataProvider('provideWhatIsNotAPageNumber')]
    public function testAnythingButAPositiveWholeNumberIsThePageOne(mixed $value): void
    {
        self::assertSame(1, PageRequest::fromRequest($value, 25)->page);
    }

    /** @return iterable<string, array{mixed}> */
    public static function provideWhatIsNotAPageNumber(): iterable
    {
        yield 'missing' => [null];
        yield 'empty' => [''];
        yield 'zero' => ['0'];
        yield 'negative' => ['-3'];
        yield 'text' => ['abc'];
        yield 'sql' => ["2' OR 1=1"];
        yield 'decimal' => ['2.5'];
        yield 'array' => [['2']];
        yield 'huge' => ['99999999999999999999'];
    }

    public function testAPageNumberIsKept(): void
    {
        $page = PageRequest::fromRequest('3', 25);

        self::assertSame(3, $page->page);
        self::assertSame(25, $page->perPage);
        self::assertSame(50, $page->offset());
    }

    public function testTheSortIsAllowListed(): void
    {
        self::assertSame(ProductSort::Quantity, ProductSort::fromRequest('quantity'));
        self::assertSame(ProductSort::Spend, ProductSort::fromRequest('spend'));

        foreach ([null, '', 'QUANTITY', 'price', 'quantity DESC', ['quantity']] as $value) {
            self::assertSame(ProductSort::Spend, ProductSort::fromRequest($value));
        }
    }
}
