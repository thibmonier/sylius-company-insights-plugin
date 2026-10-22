<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusCompanyInsightsPlugin\Unit\Export;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tmonier\SyliusCompanyInsightsPlugin\Export\CsvCell;

final class CsvCellTest extends TestCase
{
    #[DataProvider('dangerousStarts')]
    public function testACellThatCouldBeAFormulaIsExportedAsText(string $start): void
    {
        $value = $start . 'HYPERLINK("https://example.test")';

        self::assertSame("'" . $value, CsvCell::text($value));
    }

    /** @return iterable<string, array{string}> */
    public static function dangerousStarts(): iterable
    {
        yield 'equals' => ['='];
        yield 'plus' => ['+'];
        yield 'minus' => ['-'];
        yield 'at' => ['@'];
        yield 'tab' => ["\t"];
        yield 'carriage return' => ["\r"];
    }

    public function testTheCharacterIsOnlyDangerousAtTheStart(): void
    {
        self::assertSame('ATELIER-01', CsvCell::text('ATELIER-01'));
        self::assertSame('a=b+c@d', CsvCell::text('a=b+c@d'));
    }

    public function testAnEmptyOrMissingValueIsAnEmptyCell(): void
    {
        self::assertSame('', CsvCell::text(null));
        self::assertSame('', CsvCell::text(''));
    }

    public function testAMinorAmountIsWrittenWithADecimalComma(): void
    {
        self::assertSame('12,50', CsvCell::amount(1_250));
        self::assertSame('0,05', CsvCell::amount(5));
        self::assertSame('1000,00', CsvCell::amount(100_000));
        self::assertSame('-3,20', CsvCell::amount(-320));
        self::assertSame('0,00', CsvCell::amount(0));
    }
}
