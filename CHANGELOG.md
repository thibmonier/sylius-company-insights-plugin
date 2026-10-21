# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/).

## [Unreleased]

### Changed
- The "last 12 months" period is now the current calendar month and the 11 before it (it was 12 rolling months), so that the 12 monthly bars add up to its spend. Days and months follow `tmonier_company_insights.timezone` when set.

### Added
- Purchasing dashboard `/account/company/insights` for company administrators: spend, orders, average basket and active buyers of the period (last 30 days, current quarter, last 12 months, current year) with the variation against the previous period of the same duration.
- Orders waiting for approval reported apart (optional `tmonier/sylius-purchase-approval-plugin` port).
- `COMPANY_VIEW_INSIGHTS` permission, "Purchasing dashboard" tab of the company area, English and French labels.
- Purchases per month: bar chart of the last 12 months next to the same months of the previous year, with a switch between the amount and the number of orders, a data table with headers under the chart, the month in progress marked, one series per currency, months in the timezone of the report (parameter `tmonier_company_insights.timezone`). ApexCharts is drawn by a Stimulus controller from data attributes, without CDN or inline script.
- Migration of a (company, order) index on `tmonier_company_order`.
