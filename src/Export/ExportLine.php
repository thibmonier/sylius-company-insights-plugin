<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyInsightsPlugin\Export;

final readonly class ExportLine
{
    public function __construct(
        public int $orderId,
        public \DateTimeImmutable $date,
        public string $number,
        public string $buyer,
        public string $site,
        public string $productCode,
        public string $productName,
        public int $quantity,
        public int $unitPrice,
        public int $total,
        public string $currencyCode,
    ) {
    }
}
