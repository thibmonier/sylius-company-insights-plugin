<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyInsightsPlugin\Export;

/** The export is not produced: the message is meant for the member (translation key), and holds nothing else. */
final class ExportRefused extends \DomainException
{
    public const PERIOD_TOO_LONG = 'tmonier_company_insights.export.refused.period_too_long';

    public const TOO_MANY_LINES = 'tmonier_company_insights.export.refused.too_many_lines';

    public static function periodTooLong(): self
    {
        return new self(self::PERIOD_TOO_LONG);
    }

    public static function tooManyLines(): self
    {
        return new self(self::TOO_MANY_LINES);
    }
}
