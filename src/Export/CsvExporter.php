<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyInsightsPlugin\Export;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Contracts\Translation\TranslatorInterface;
use Tmonier\SyliusCompanyInsightsPlugin\Breakdown\OrderDimensionProviderInterface;
use Tmonier\SyliusCompanyInsightsPlugin\Breakdown\OrderReferenceProviderInterface;

/**
 * One line per order line, tax exclusive, in the format a French spreadsheet opens as is: UTF-8 with a
 * byte order mark and `;` as separator. The text of the orders and of the application is neutralised
 * against formulas (`CsvCell`). The export is refused, before the first byte, beyond 24 months or
 * 50 000 lines. Each export is logged (who, when, which period), with no content.
 */
final readonly class CsvExporter
{
    private const BOM = "\xEF\xBB\xBF";

    private const CHUNK = 500;

    private LoggerInterface $logger;

    /**
     * @param iterable<OrderDimensionProviderInterface> $dimensionProviders
     * @param iterable<OrderReferenceProviderInterface> $referenceProviders
     */
    public function __construct(
        private ExportLinesInterface $lines,
        private TranslatorInterface $translator,
        private iterable $dimensionProviders = [],
        private iterable $referenceProviders = [],
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    /** @throws ExportRefused */
    public function assertAllowed(ExportRequest $request): void
    {
        if (!ExportLimits::allowsPeriod($request->period)) {
            throw ExportRefused::periodTooLong();
        }
        if ($this->lines->count($request) > ExportLimits::MAX_LINES) {
            throw ExportRefused::tooManyLines();
        }
    }

    /**
     * @param resource $stream
     *
     * @return int the number of lines written
     */
    public function write(ExportRequest $request, $stream): int
    {
        $dimensions = array_values([...$this->dimensionProviders]);
        fwrite($stream, self::BOM);
        $this->put($stream, $this->header($request->localeCode, $dimensions));

        $written = 0;
        foreach ($this->chunks($this->lines->lines($request, ExportLimits::MAX_LINES)) as $chunk) {
            $ids = array_values(array_unique(array_map(static fn (ExportLine $line): int => $line->orderId, $chunk)));
            $references = $this->references($ids);
            $values = array_map(fn (OrderDimensionProviderInterface $provider): array => $provider->values($ids), $dimensions);
            foreach ($chunk as $line) {
                $this->put($stream, $this->row($line, $references[$line->orderId] ?? null, array_map(static fn (array $perOrder): ?string => $perOrder[$line->orderId] ?? null, $values)));
                ++$written;
            }
        }

        $this->logger->info('Company insights: purchases exported to CSV.', [
            'company_id' => $request->companyId,
            'member_id' => $request->memberId,
            'period_from' => $request->period->from->format('Y-m-d'),
            'period_to' => $request->period->lastDay()->format('Y-m-d'),
            'lines' => $written,
            'at' => (new \DateTimeImmutable())->format(\DATE_ATOM),
        ]);

        return $written;
    }

    /**
     * @param iterable<ExportLine> $lines
     *
     * @return \Generator<int, list<ExportLine>>
     */
    private function chunks(iterable $lines): \Generator
    {
        $chunk = [];
        foreach ($lines as $line) {
            $chunk[] = $line;
            if (self::CHUNK === count($chunk)) {
                yield $chunk;
                $chunk = [];
            }
        }
        if ([] !== $chunk) {
            yield $chunk;
        }
    }

    /**
     * @param list<int> $ids
     *
     * @return array<int, string|null>
     */
    private function references(array $ids): array
    {
        foreach ($this->referenceProviders as $provider) {
            return $provider->references($ids);
        }

        return [];
    }

    /**
     * @param list<OrderDimensionProviderInterface> $dimensions
     *
     * @return list<string>
     */
    private function header(string $locale, array $dimensions): array
    {
        $columns = array_map(
            fn (string $key): string => $this->translator->trans('tmonier_company_insights.export.column.' . $key, [], null, $locale),
            ['date', 'number', 'po_reference', 'buyer', 'site'],
        );
        foreach ($dimensions as $dimension) {
            $columns[] = CsvCell::text($this->translator->trans($dimension->label(), [], null, $locale));
        }

        return [...$columns, ...array_map(
            fn (string $key): string => $this->translator->trans('tmonier_company_insights.export.column.' . $key, [], null, $locale),
            ['product_code', 'product_name', 'quantity', 'unit_price', 'total', 'currency'],
        )];
    }

    /**
     * @param list<string|null> $dimensionValues
     *
     * @return list<string>
     */
    private function row(ExportLine $line, ?string $reference, array $dimensionValues): array
    {
        return [
            $line->date->format('Y-m-d'),
            CsvCell::text($line->number),
            CsvCell::text($reference),
            CsvCell::text($line->buyer),
            CsvCell::text($line->site),
            ...array_map(CsvCell::text(...), $dimensionValues),
            CsvCell::text($line->productCode),
            CsvCell::text($line->productName),
            (string) $line->quantity,
            CsvCell::amount($line->unitPrice),
            CsvCell::amount($line->total),
            CsvCell::text($line->currencyCode),
        ];
    }

    /**
     * @param resource $stream
     * @param list<string> $cells
     */
    private function put($stream, array $cells): void
    {
        // The escape character is explicitly none (RFC 4180): the default is deprecated since PHP 8.4.
        fputcsv($stream, $cells, ';', '"', '', "\r\n");
    }
}
