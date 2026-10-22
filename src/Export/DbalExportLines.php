<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyInsightsPlugin\Export;

use Doctrine\DBAL\Connection;
use Tmonier\SyliusCompanyInsightsPlugin\Filter\AddressMatch;
use Tmonier\SyliusCompanyInsightsPlugin\Statistics\CountedOrderSql;
use Webmozart\Assert\Assert;

final readonly class DbalExportLines implements ExportLinesInterface
{
    public function __construct(
        private Connection $connection,
        private CountedOrderSql $sql,
        private ?string $timezone = null,
    ) {
    }

    public function count(ExportRequest $request): int
    {
        $count = $this->connection->fetchOne(
            sprintf('SELECT COUNT(i.id) FROM %s %s WHERE %s', CountedOrderSql::FROM, CountedOrderSql::LINES, $this->where($request)),
            $this->parameters($request),
        );
        Assert::numeric($count);

        return (int) $count;
    }

    public function lines(ExportRequest $request, int $limit): iterable
    {
        Assert::positiveInteger($limit);
        $rows = $this->connection->iterateAssociative(
            sprintf(
                'SELECT o.id AS order_id, o.number AS number, o.checkout_completed_at AS completed_at, c.first_name AS first_name, c.last_name AS last_name, o.currency_code AS currency_code, (SELECT MIN(book.label) FROM tmonier_company_address book INNER JOIN sylius_address book_address ON book_address.id = book.address_id WHERE book.company_id = co.company_id AND %s) AS site_label, delivery.street AS street, delivery.postcode AS postcode, delivery.city AS city, v.code AS variant_code, COALESCE(i.product_name, pt.name) AS product_name, COALESCE(i.variant_name, vt.name) AS variant_name, i.quantity AS quantity, %s AS line_total FROM %s %s LEFT JOIN sylius_customer c ON c.id = o.customer_id LEFT JOIN sylius_address delivery ON delivery.id = o.shipping_address_id LEFT JOIN sylius_product_variant v ON v.id = i.variant_id LEFT JOIN sylius_product_translation pt ON pt.translatable_id = v.product_id AND pt.locale = o.locale_code LEFT JOIN sylius_product_variant_translation vt ON vt.translatable_id = v.id AND vt.locale = o.locale_code WHERE %s ORDER BY o.checkout_completed_at, o.id, i.id LIMIT %d',
                AddressMatch::sql('book_address', 'delivery'),
                $this->sql->lineNetTotal(),
                CountedOrderSql::FROM,
                CountedOrderSql::LINES,
                $this->where($request),
                $limit,
            ),
            $this->parameters($request),
        );

        foreach ($rows as $row) {
            yield $this->line($row);
        }
    }

    private function line(array $row): ExportLine
    {
        Assert::numeric($row['order_id']);
        Assert::string($row['completed_at']);
        Assert::numeric($row['quantity']);
        Assert::numeric($row['line_total']);
        Assert::string($row['currency_code']);
        $quantity = (int) $row['quantity'];
        $total = (int) $row['line_total'];
        $date = new \DateTimeImmutable($row['completed_at'], new \DateTimeZone(date_default_timezone_get()));

        return new ExportLine(
            (int) $row['order_id'],
            null === $this->timezone ? $date : $date->setTimezone(new \DateTimeZone($this->timezone)),
            $this->string($row['number']),
            trim($this->string($row['first_name']) . ' ' . $this->string($row['last_name'])),
            $this->site($row),
            $this->string($row['variant_code']),
            $this->productName($this->string($row['product_name']), $this->string($row['variant_name'])),
            $quantity,
            0 === $quantity ? 0 : (int) round($total / $quantity),
            $total,
            $row['currency_code'],
        );
    }

    private function where(ExportRequest $request): string
    {
        return $this->sql->counted() . ' AND o.checkout_completed_at >= :from AND o.checkout_completed_at < :to' . $request->filter->sql();
    }

    /** @return array<string, int|string> */
    private function parameters(ExportRequest $request): array
    {
        return [
            'company' => $request->companyId,
            'from' => CountedOrderSql::storedDate($request->period->from),
            'to' => CountedOrderSql::storedDate($request->period->to),
            ...$request->filter->parameters(),
        ];
    }

    /** The label of the address book, or the address itself when it is not in the book. */
    private function site(array $row): string
    {
        $label = $this->string($row['site_label']);
        if ('' !== $label) {
            return $label;
        }

        return trim($this->string($row['street']) . ', ' . trim($this->string($row['postcode']) . ' ' . $this->string($row['city'])), ' ,');
    }

    private function productName(string $product, string $variant): string
    {
        return '' === $variant || 0 === strcasecmp($product, $variant) ? $product : $product . ' - ' . $variant;
    }

    private function string(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }
}
