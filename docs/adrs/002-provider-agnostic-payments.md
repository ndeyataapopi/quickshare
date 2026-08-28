# ADR-002 — Provider-Agnostic Payment Execution

## Status

**Proposed / Accepted in Principle**

## Context

QuickShare may integrate multiple regulated payment providers over time. Locking the loan engine to one provider would create commercial, regulatory and operational risk.

## Decision

QuickShare will not depend directly on a single payment provider. A Payment Orchestrator abstraction will dispatch execution to the configured provider while the loan engine owns the business meaning of the transaction.

## Consequences

### Positive

- Commercial flexibility when negotiating with providers.
- Provider redundancy and failover options.
- Easier sandbox and regulatory testing.
- Manual execution can coexist with automated execution.

### Negative

- Additional abstraction layer to design, build and maintain.
- Each provider requires its own adapter, webhook handling and reconciliation.

## Notes

The architecture supports:

- `PAYMENT_MODE=manual`
- `PAYMENT_MODE=automated`
- `PAYMENT_MODE=hybrid`

At the time of this document, only `manual` is enabled in production.
