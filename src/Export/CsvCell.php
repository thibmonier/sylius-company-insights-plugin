<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyInsightsPlugin\Export;

/**
 * What goes into a cell of the CSV. A spreadsheet runs a cell that starts with `=`, `+`, `-` or `@` (or a
 * tab or a carriage return, which some of them skip first) as a formula: every text coming from the
 * orders or the application is prefixed with an apostrophe, which turns it back into text.
 */
final class CsvCell
{
    private const FORMULA_STARTS = ['=', '+', '-', '@', "\t", "\r"];

    private function __construct()
    {
    }

    public static function text(?string $value): string
    {
        if (null === $value || '' === $value) {
            return '';
        }

        return in_array($value[0], self::FORMULA_STARTS, true) ? "'" . $value : $value;
    }

    /** An amount in minor units (cents), with a decimal comma for a spreadsheet in French. */
    public static function amount(int $minor): string
    {
        return sprintf('%s%d,%02d', $minor < 0 ? '-' : '', intdiv(abs($minor), 100), abs($minor) % 100);
    }
}
