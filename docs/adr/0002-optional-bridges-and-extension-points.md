# ADR-0002: Optional bridges, widgets and extension points

## Status

Accepted (2026-10-22). Used by the breakdown, the CSV export and the widgets of 0.2.0 (US-152, US-153).

## Context

0.2.0 adds data that the plugin does not own: an analytical axis (cost centre), a customer reference (PO number), and indicators of two companion plugins (purchase approval, trade credit). Constraints:

- no mandatory dependency on a companion plugin, and a missing companion must not produce an error;
- figures come from the public services of a companion, never from its tables;
- the dashboard must survive a companion that fails;
- the plugin must not decide what a "cost centre" is.

## Decision

- **Application data goes through ports** (`OrderDimensionProviderInterface`, `OrderReferenceProviderInterface`), tagged services that the plugin autoconfigures. They are batch-oriented (`values(list<int> $orderIds)`), so that the application can answer with one query, and the plugin ships no implementation. The order amounts stay in the plugin's own SQL; the application only labels orders.
- **Companion data goes through widgets** (`InsightsWidgetProviderInterface`) collected by a registry. A bridge is registered only when the extension of the companion exists at container build time (same technique as `Approval/`); there is no `require`, only `suggest`.
- **Failure isolation sits in the registry**, not in each bridge: an exception becomes an "unavailable" widget and a log line that holds the widget code and the class of the exception, never the message (it may carry names or amounts).
- **Authorisation**: each widget may declare a security attribute, checked against the company before the widget is computed; the dashboard permission remains the floor.
- **Companion services are used as they are.** Purchase approval has no aggregate for the median delay, the refusal rate and the buyers submitted to approval: they are computed from its public repository (the approvals of the period, 2 000 at most). We did not add an aggregate to the companion for this release; if its volumes require it, it should offer one and the bridge would switch to it.

## Alternatives considered

### SQL on the tables of the companions
- Faster and unbounded.
- Couples the plugin to their schema, which they may change in a minor release. Rejected.

### One interface per companion, called directly by the controller
- Simple for two companions.
- Every new companion edits the controller and the template (OCP); and the "absent companion" case becomes a condition in the controller.

### Dimension as an SQL expression
- Lets the database group by the axis.
- Leaks the schema of the application into the plugin and puts SQL written by the application into our queries. Rejected for the batch contract above, at the price of reading the orders of the selection into memory (one id and one amount per order).
