# ADR-001 — Keep Manual Payments as the Production Fallback

## Status

**Accepted**

## Context

QuickShare was launched in private beta before any live payment-provider integrations were available or approved. Money needs to move between lenders, the platform and borrowers, but the platform cannot wait for provider contracts or regulatory testing before operating.

## Decision

QuickShare will retain a manual payment workflow even after optional provider automation is introduced.

## Consequences

### Positive

- Operational fallback if a provider is unavailable.
- Provider outage resilience.
- Gradual rollout of automation.
- Time to complete regulatory and sandbox testing.
- Customer support can resolve payment issues directly.

### Negative

- Higher operational overhead for confirming payments.
- Human verification becomes a bottleneck.
- Reconciliation depends on manual matching.

## Notes

The manual workflow is the current production mode. Automated execution is an optional capability that must be explicitly enabled and approved.
