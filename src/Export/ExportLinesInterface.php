<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyInsightsPlugin\Export;

interface ExportLinesInterface
{
    public function count(ExportRequest $request): int;

    /** @return iterable<ExportLine> the order lines (tax exclusive), oldest first, at most `$limit` */
    public function lines(ExportRequest $request, int $limit): iterable;
}
