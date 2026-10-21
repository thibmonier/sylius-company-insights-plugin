# Sylius Company Insights Plugin

Purchasing dashboard for [Sylius](https://sylius.com) 2 B2B company accounts. The administrator of a company sees, in the customer account, how much the company spends and how fast it orders: spend, number of orders, average basket and active buyers of the chosen period, each with its variation against the previous period of the same duration, and the orders waiting for approval apart.

Built on [`tmonier/sylius-company-accounts-plugin`](https://github.com/tmonier/sylius-company-accounts-plugin) (companies, members, roles, company orders). English | [Français](README.fr.md)

## What it does

- **Page** `/{_locale}/account/company/insights`, linked from a "Purchasing dashboard" tab of the "My company" area.
- **Indicators** of the period, with the variation in percent against the previous period of the same duration:
  - spend, tax exclusive;
  - number of orders;
  - average basket, tax exclusive;
  - active buyers (customers with at least one order);
  - orders waiting for approval (number and amount), shown apart and not counted in the spend.
- **Periods**: last 30 days, current quarter, last 12 months (default, the current calendar month and the 11 before it), current year. The period is in the title and in the URL (`?period=current_quarter`). The quarter and the year so far are compared with the same elapsed days of the previous quarter and year.
- **Purchases per month**: bar chart of the spend of the last 12 months next to the same months of the previous year. A switch (`?metric=amount|orders`, allow-listed) shows the number of orders instead of the amount.
  - Months without order are zeros, never gaps; the month in progress is marked "in progress" and is not a complete month.
  - Months follow a timezone: the default timezone of PHP, or the parameter `tmonier_company_insights.timezone` (for example `Europe/Paris`; Sylius channels have no timezone of their own). An order placed on the 31st at 23:30 counts in the right month, summer time included.
  - One series per currency, no conversion and no total mixing currencies.
  - The 12 bars add up to the spend of the "last 12 months" indicator (same orders, same rules).
  - Accessibility: a data table (month, spend, orders, same figures of the previous year, with `th scope` headers) sits under the chart and is what screen readers read; the drawing is `aria-hidden`. Series differ by fill (solid / hatched) and legend, not by colour alone; colours are at least 7:1 against white.
  - No CDN and no inline script: the data is in `data-*` attributes read by a Stimulus controller (see [ADR-0001](docs/adr/0001-charts-library.md)). Without JavaScript, the table remains.
- **Access**: company administrators only (`COMPANY_VIEW_INSIGHTS`). Buyers and approvers get an explained 403 and no tab.
- **Isolation between companies**: the company is the one of the signed-in member, never a request parameter. The only parameter is the period, from an allow-list; any other value is replaced by the default period.
- **Counted orders**: checkout completed, not cancelled, linked to the company by `CompanyOrder`, not waiting for approval.
- **Amounts** are tax exclusive, in the currency of the order. With several currencies, there is one line per currency and nothing is converted.
- Aggregates are computed by the database (no entity loaded) on plain SQL: MySQL, MariaDB and PostgreSQL.
- Labels in English and French. The page does not rely on colour alone (arrows and sentences) and avoids low-contrast text classes.

## Installation

```bash
composer require tmonier/sylius-company-insights-plugin
```

1. Register the bundle (after `TmonierSyliusCompanyAccountsPlugin`) in `config/bundles.php`:

    ```php
    Tmonier\SyliusCompanyInsightsPlugin\TmonierSyliusCompanyInsightsPlugin::class => ['all' => true],
    ```

2. Import the configuration in `config/packages/_sylius.yaml`:

    ```yaml
    imports:
        - { resource: "@TmonierSyliusCompanyInsightsPlugin/config/config.yaml" }
    ```

3. Import the routes in `config/routes.yaml`:

    ```yaml
    tmonier_sylius_company_insights:
        resource: "@TmonierSyliusCompanyInsightsPlugin/config/routes.yaml"
    ```

4. Run the migration (one index on `tmonier_company_order`, declared in the Doctrine mapping so that schema diffs keep it):

    ```bash
    bin/console doctrine:migrations:migrate
    ```

5. Build the chart (optional, the tables work without it). Add the dependency `apexcharts` (already used by the Sylius admin) and import the entrypoint of the plugin in your shop `assets/shop/entrypoint.js`:

    ```js
    import '@vendor/tmonier/sylius-company-insights-plugin/assets/shop/entrypoint';
    ```

The timezone of the months can be set with a parameter (default: the timezone of PHP):

```yaml
parameters:
    tmonier_company_insights.timezone: 'Europe/Paris'
```

The Sylius shop access control must protect `^/(?:[a-z]{2,4}(?:_[A-Za-z]{2})?)/account` (it does in the default Sylius configuration).

## Permission

| Attribute | Subject | Granted to |
|-----------|---------|------------|
| `COMPANY_VIEW_INSIGHTS` | company | an enabled member with the `admin` role of the enabled company, for that company only |

```twig
{% if is_granted('COMPANY_VIEW_INSIGHTS', company) %}...{% endif %}
```

## Orders waiting for approval

With [`tmonier/sylius-purchase-approval-plugin`](https://github.com/tmonier/sylius-purchase-approval-plugin) installed, the orders it holds are reported in their own block and are not part of the spend until an approver approves them. Without it, the block says that no order is waiting. There is no hard dependency: the integration is a port (`PendingOrderConditionInterface`) that you can implement for another approval workflow by redefining the service `tmonier_company_insights.pending_order_condition`.

## Supported versions (PHP, Symfony, Sylius) and impact of version

| | Supported |
|-|-----------|
| PHP | 8.2, 8.3, 8.4 |
| Symfony | 6.4, 7.4, 8.0 |
| Sylius | 2.2 (`~2.2.0`), 2.3 (`~2.3.0`) |
| Database | MySQL 8.4, MariaDB 11.4, PostgreSQL 17 |
| Dependency | `tmonier/sylius-company-accounts-plugin` ^1.1 |

The CI matrix is the one of `tmonier/sylius-company-accounts-plugin` and `tmonier/sylius-agentic-feeds-plugin`: Sylius 2.2 on PHP 8.2 to 8.4 with Symfony 6.4 and 7.4, Sylius 2.3 on PHP 8.3 and 8.4 with Symfony 6.4, 7.4 and 8.0, plus MariaDB and PostgreSQL cells. PHP 8.5 has no cell yet, as none of the companion plugins has one. Impact of version: none of the plugin code depends on a version-specific API; the voter keeps the `mixed $vote = null` signature of the Symfony 6.4 to 8.0 range and the SQL is portable.

## Development

```bash
composer install
vendor/bin/ecs check
vendor/bin/phpstan analyse            # level max
vendor/bin/rector process --dry-run
vendor/bin/phpunit --testsuite unit
vendor/bin/phpunit --testsuite non-unit   # needs the test database, see the Makefile
```

Design decisions are recorded in [`docs/adr`](docs/adr).

## License

MIT
