<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyInsightsPlugin\Ranking;

use Tmonier\SyliusCompanyInsightsPlugin\Statistics\CountedOrderSql;
use Webmozart\Assert\Assert;

/**
 * Quantity and tax-exclusive spend per variant, from the lines of the counted orders. The order
 * is a fixed fragment of {@see ProductSort}; the names are those of the locale, null when missing.
 */
final readonly class DbalProductRanking implements ProductRankingInterface
{
    use RankingQueryTrait;

    public function rows(RankingScope $scope, ProductSort $sort, PageRequest $page): array
    {
        $rows = $this->connection->fetchAllAssociative(
            sprintf('%s ORDER BY %s LIMIT %d OFFSET %d', $this->grouped(), $sort->orderBy(), $page->perPage, $page->offset()),
            $this->parameters($scope) + ['product_locale' => $scope->localeCode, 'variant_locale' => $scope->localeCode],
        );

        return array_map($this->row(...), $rows);
    }

    public function totals(RankingScope $scope): RankingTotals
    {
        return $this->totalsOf($this->grouped(), $this->parameters($scope) + ['product_locale' => $scope->localeCode, 'variant_locale' => $scope->localeCode]);
    }

    /** @param array<string, mixed> $row */
    private function row(array $row): ProductRow
    {
        Assert::string($row['code']);
        Assert::nullOrString($row['product_name']);
        Assert::nullOrString($row['variant_name']);
        Assert::numeric($row['quantity']);
        Assert::numeric($row['spend']);
        Assert::string($row['last_purchase']);
        $lastPurchase = new \DateTimeImmutable($row['last_purchase']);

        return new ProductRow(
            $row['code'],
            $row['product_name'],
            $row['variant_name'],
            (int) $row['quantity'],
            (int) $row['spend'],
            null === $this->timezone ? $lastPurchase : $lastPurchase->setTimezone(new \DateTimeZone($this->timezone)),
        );
    }

    private function grouped(): string
    {
        return sprintf(
            'SELECT v.id AS variant_id, v.code AS code, pt.name AS product_name, vt.name AS variant_name, SUM(i.quantity) AS quantity, SUM(%s) AS spend, MAX(o.checkout_completed_at) AS last_purchase FROM %s %s INNER JOIN sylius_product_variant v ON v.id = i.variant_id LEFT JOIN sylius_product_translation pt ON pt.translatable_id = v.product_id AND pt.locale = :product_locale LEFT JOIN sylius_product_variant_translation vt ON vt.translatable_id = v.id AND vt.locale = :variant_locale WHERE %s GROUP BY v.id, v.code, pt.name, vt.name',
            $this->sql->lineNetTotal(),
            CountedOrderSql::FROM,
            CountedOrderSql::LINES,
            $this->where(),
        );
    }
}
