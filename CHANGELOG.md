# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/).

## [Unreleased]

## [0.2.0] - Unreleased

### Added
- Filters of the whole dashboard (indicators, chart, rankings, breakdown) and of the export: a member of the company (`?member=`), a delivery site of its address book (`?site=`) and a custom period of two dates (`?from=&to=`, compared with the period of the same duration just before). The member and the site are checked against the company of the signed-in member; any other value is ignored.
- Purchases per delivery site, with the label of the address book of the company ("Other addresses" for the rest), and purchases per analytical axis through the new extension point `OrderDimensionProviderInterface` (the plugin ships no axis; without one, no block).
- CSV export of the selection (`/account/company/insights/export`, same permission and filters as the dashboard): one line per order line, UTF-8 with BOM, `;` separator, decimal comma, cells starting with `=`, `+`, `-`, `@` neutralised, 24 months and 50 000 lines at most (nothing is produced beyond), content-free log of each export. `OrderReferenceProviderInterface` fills the "PO reference" column.
- Dashboard widgets: `InsightsWidgetProviderInterface` and the widget registry (permission per widget, a failing widget becomes "Data temporarily unavailable" and is logged without personal data). Two bridges detected when the companion is installed, with no new required dependency: **Approvals** (`tmonier/sylius-purchase-approval-plugin`: orders waiting with their age, median time to decide, refusal rate, buyers most often submitted to approval) and **Credit** (`tmonier/sylius-trade-credit-plugin`: limit, used, available, overdue, next due date, usage gauge).
- English and French labels for all of the above.

### Fixed
- CSV export: the "PO reference" column merges all the `OrderReferenceProviderInterface` services (first non-empty reference per order, in the order of the tag priority) instead of using the first service only; the export log date comes from the injected clock.
- CSV export: the 24-month limit is exact on the 29 February (it overflowed to 1 March, accepting one day too many).

### Changed
- Approvals widget: the figures are SQL aggregates on the table of the purchase-approval plugin instead of the 2 000 latest entities loaded without ordering, so nothing is truncated silently and no customer is loaded one by one; the amounts are tax exclusive like the rest of the dashboard (they were tax included) and the period is the one of the checkout of the order (`checkout_completed_at`), not the creation date of the request. The median time to decide is read by OFFSET on the ordered durations (MySQL, MariaDB, PostgreSQL).
- Breakdown by analytical axis: the spend per order (tax exclusive, one row per counted order, ids and amounts only) is still read in full because the axes are supplied by the application and cannot be grouped in SQL; the values are requested by chunks of 1 000 ids. Documented bound, see `BreakdownProvider`.
- CI and `composer.json`: `tmonier/sylius-trade-credit-plugin` joins the dev dependencies (tests of the Credit widget); `suggest` lists both companions.

## [0.1.1] - 2026-10-22

### Fixed
- Top products: a variant named like its product (the usual single-variant product) shows the product name once instead of "Gloves - Gloves"; names differing only by case or surrounding spaces count as the same.

### Changed
- CI: PHP 8.5 added to the matrix (Sylius 2.3, Symfony 8), actions pinned by commit SHA with read-only `permissions`, Dependabot watches the GitHub Actions.
- README: screenshots of the dashboard (English and French).

## [0.1.0] - 2026-10-21

### Added
- Rankings of the period on the dashboard, one set per currency: top buyers (name only, no e-mail address, deactivated members marked, share of the spend), top products (by spend or by quantity with `?sort=`, last purchase date, code when the name has no translation) and top categories (shares adding up to 100 %, donut with its equivalent table). Each is limited to 10 rows with a "See all" link to a paginated page `/account/company/insights/{buyers|products|categories}`; the ranking, the sort, the page and the currency are allow-listed.
- Purchasing dashboard `/account/company/insights` for company administrators: spend, orders, average basket and active buyers of the period (last 30 days, current quarter, last 12 months, current year) with the variation against the previous period of the same duration. "Last 12 months" is the current calendar month and the 11 before it, so that the 12 monthly bars add up to its spend; days and months follow `tmonier_company_insights.timezone` when set.
- Orders waiting for approval reported apart (optional `tmonier/sylius-purchase-approval-plugin` port).
- `COMPANY_VIEW_INSIGHTS` permission, "Purchasing dashboard" tab of the company area, English and French labels.
- Purchases per month: bar chart of the last 12 months next to the same months of the previous year, with a switch between the amount and the number of orders, a data table with headers under the chart, the month in progress marked, one series per currency, months in the timezone of the report (parameter `tmonier_company_insights.timezone`). ApexCharts is drawn by a Stimulus controller from data attributes, without CDN or inline script.
- Migration of a (company, order) index on `tmonier_company_order`.
