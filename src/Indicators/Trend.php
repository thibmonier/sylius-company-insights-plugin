<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyInsightsPlugin\Indicators;

enum Trend: string
{
    case Up = 'up';
    case Down = 'down';
    case Flat = 'flat';

    /** No comparison is possible (nothing in the previous period). */
    case Unknown = 'unknown';
}
