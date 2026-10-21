# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/).

## [Unreleased]

## [0.1.0] - 2026-10-21

### Added
- Rankings of the period on the dashboard, one set per currency: top buyers (name only, no e-mail address, deactivated members marked, share of the spend), top products (by spend or by quantity with `?sort=`, last purchase date, code when the name has no translation) and top categories (shares adding up to 100 %, donut with its equivalent table). Each is limited to 10 rows with a "See all" link to a paginated page `/account/company/insights/{buyers|products|categories}`; the ranking, the sort, the page and the currency are allow-listed.
- Purchasing dashboard `/account/company/insights` for company administrators: spend, orders, average basket and active buyers of the period (last 30 days, current quarter, last 12 months, current year) with the variation against the previous period of the same duration. "Last 12 months" is the current calendar month and the 11 before it, so that the 12 monthly bars add up to its spend; days and months follow `tmonier_company_insights.timezone` when set.
- Orders waiting for approval reported apart (optional `tmonier/sylius-purchase-approval-plugin` port).
- `COMPANY_VIEW_INSIGHTS` permission, "Purchasing dashboard" tab of the company area, English and French labels.
- Purchases per month: bar chart of the last 12 months next to the same months of the previous year, with a switch between the amount and the number of orders, a data table with headers under the chart, the month in progress marked, one series per currency, months in the timezone of the report (parameter `tmonier_company_insights.timezone`). ApexCharts is drawn by a Stimulus controller from data attributes, without CDN or inline script.
- Migration of a (company, order) index on `tmonier_company_order`.
