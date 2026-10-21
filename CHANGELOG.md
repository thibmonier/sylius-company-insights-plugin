# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/).

## [Unreleased]

### Added
- Purchasing dashboard `/account/company/insights` for company administrators: spend, orders, average basket and active buyers of the period (last 30 days, current quarter, last 12 months, current year) with the variation against the previous period of the same duration.
- Orders waiting for approval reported apart (optional `tmonier/sylius-purchase-approval-plugin` port).
- `COMPANY_VIEW_INSIGHTS` permission, "Purchasing dashboard" tab of the company area, English and French labels.
- Migration of a (company, order) index on `tmonier_company_order`.
