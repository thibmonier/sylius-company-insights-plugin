# ADR-0001: Charts with the ApexCharts bundled by Sylius, without CDN

## Status

Accepted (2026-10-21). Used by the monthly purchases chart (US-150): `assets/shop/entrypoint.js` registers a Stimulus controller that reads the data from `data-*` attributes of the page.

## Context

The dashboard will show charts (purchases per month compared with the previous year, tops). Constraints:

- the shop of the demo and of most B2B stores runs behind a strict Content-Security-Policy: no script or style from a CDN;
- accessibility AAA: every chart needs an equivalent data table, and colour cannot be the only carrier of information;
- Sylius already ships [ApexCharts](https://apexcharts.com) for the admin dashboard, so the library is already in the dependency tree of every Sylius 2 project;
- a plugin cannot assume a JavaScript build of its own in the shop.

## Decision

We reuse ApexCharts, as bundled by Sylius, and load it from the assets of the plugin (a shop entrypoint compiled by the application Webpack Encore build). No CDN, no inline script. The charts are progressive enhancement over a data table rendered by Twig: without JavaScript, or for assistive technologies, the table carries the same figures.

## Alternatives considered

### Chart.js
- Good accessibility story and small size.
- A new dependency to install, audit and pin in every shop; nothing in Sylius uses it.

### Charts rendered server-side as SVG
- No JavaScript, no CSP issue, accessible by construction.
- We would write and maintain axes, scales, tooltips and responsive behaviour ourselves (KISS and YAGNI argue against it for monthly bars and a few tops).

### CSS-only bars
- Enough for one series, not for the comparison with the previous year.

## Consequences

### Positive
- No new dependency; same look and feel as the Sylius admin.
- Compatible with a strict CSP.

### Negative
- The shop application must build the plugin entrypoint (`assets/shop/entrypoint.js`, as the other plugins of the family).
- ApexCharts upgrades follow Sylius.
