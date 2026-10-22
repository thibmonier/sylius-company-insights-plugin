<?php

declare(strict_types=1);

namespace Tmonier\SyliusCompanyInsightsPlugin\Ranking;

use Tmonier\SyliusCompanyInsightsPlugin\Statistics\CountedOrderSql;
use Webmozart\Assert\Assert;

/** Tax-exclusive spend per main category of the product of the lines; the products without one are a row of their own. */
final readonly class DbalCategoryRanking implements CategoryRankingInterface
{
    use RankingQueryTrait;

    public function rows(RankingScope $scope): array
    {
        $rows = $this->connection->fetchAllAssociative(
            sprintf('%s ORDER BY spend DESC, t.code ASC', $this->grouped($scope)),
            $this->parameters($scope) + ['locale' => $scope->localeCode],
        );

        return array_map(static function (array $row): CategoryRow {
            Assert::nullOrString($row['code']);
            Assert::nullOrString($row['name']);
            Assert::numeric($row['spend']);

            return new CategoryRow($row['code'], $row['name'], (int) $row['spend']);
        }, $rows);
    }

    private function grouped(RankingScope $scope): string
    {
        return sprintf(
            'SELECT t.id AS taxon_id, t.code AS code, tt.name AS name, SUM(%s) AS spend FROM %s %s INNER JOIN sylius_product_variant v ON v.id = i.variant_id INNER JOIN sylius_product p ON p.id = v.product_id LEFT JOIN sylius_taxon t ON t.id = p.main_taxon_id LEFT JOIN sylius_taxon_translation tt ON tt.translatable_id = t.id AND tt.locale = :locale WHERE %s GROUP BY t.id, t.code, tt.name',
            $this->sql->lineNetTotal(),
            CountedOrderSql::FROM,
            CountedOrderSql::LINES,
            $this->where($scope),
        );
    }
}
