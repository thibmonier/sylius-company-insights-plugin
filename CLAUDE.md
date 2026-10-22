# CLAUDE.md

Sylius 2 plugin "Company Insights": purchasing dashboard (`/account/company/insights`) for the
administrators of a `tmonier/sylius-company-accounts-plugin` company. Docs: `README.md`, ADRs in `docs/adr`.

## Commands

PHP needs more than 128 MB to compile the test application container: prefix console, PHPStan
and functional PHPUnit calls with `php -d memory_limit=2G` when running locally.

```bash
vendor/bin/phpunit --testsuite unit
vendor/bin/phpunit --testsuite non-unit   # functional + integration, test DB + Sylius fixtures
vendor/bin/phpstan analyse                # level max
vendor/bin/ecs check
vendor/bin/console lint:container
```

The test application reads `tests/TestApplication/.env` (database `tmonier_sylius_company_insights_<env>`);
shop pages need the Webpack entrypoints of the test application (`composer frontend-clear`).

## Architecture

- `Period/` (allow-listed `PeriodKey`, `PeriodResolver`: period and previous period of the same duration).
- `Statistics/` (`DbalPurchaseStatistics`, `CountedOrderSql`: SQL aggregates of ONE company, tax exclusive, per currency; the SQL is shared with `Monthly/`).
- `Monthly/` (`MonthlyPurchasesProvider`, `DbalMonthlyPurchaseStatistics`: 12 months + previous year, one series per currency, months in the report timezone; allow-listed `Metric`).
- `Ranking/` (top buyers, products and categories: `RankingsProvider`, `Dbal*Ranking` on the SQL of `CountedOrderSql`, allow-listed `RankingKind`, `ProductSort`, `PageRequest`; categories are read whole so that `Shares` adds up to 100).
- `Approval/` (optional port for orders waiting for approval).
- `Filter/` (`PurchaseFilter` member + site, `SelectionFactory`: period, dates, member and site validated against the company of the signed-in member; the filter SQL is appended to every query through `PurchaseFilter::sql()` / `parameters()`).
- `Breakdown/` (delivery sites and application axes: `OrderDimensionProviderInterface`, `OrderReferenceProviderInterface`; the plugin ships no axis).
- `Export/` (`CsvExporter`, `CsvCell` formula neutralisation, `ExportLimits` 24 months / 50 000 lines; refused before any output).
- `Widget/` (`InsightsWidgetProviderInterface`, `InsightsWidgetRegistry`: permission per widget, a failing widget is "unavailable" and logged without its message) and `Bridge/PurchaseApproval`, `Bridge/TradeCredit` (loaded only when the companion is installed; `composer.json` only suggests them, trade credit is a dev dependency for the tests).
- `Indicators/` (`DashboardProvider`, `Variation`), `Security/InsightsVoter`, `Controller/Shop/InsightsController`.
- Rule: the company comes from the signed-in member, never from the request.
