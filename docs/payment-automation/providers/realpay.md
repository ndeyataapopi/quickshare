# RealPay Capability Discovery

**Sources inspected**

* https://www.realpay.co.za/integrations/api-integration
* https://www.realpay.co.za/features/api-access
* https://www.realpay.co.za/payment-flow/payouts
* https://www.realpay.co.za/payment-flow/collections
* https://www.realpay.co.za/payment-flow/verifications
* https://www.realpay.co.za/products/endo
* https://www.realpay.co.za/products/encr
* https://www.realpay.co.za/countries/namibia
* https://www.realpay.co.za/features/tracking

**Date:** 2026-09-02  
**Note:** RealPay publishes extensive **product** capability documentation but does **not** expose a public REST API reference. Endpoint names, request/response schemas, and webhook payload formats are therefore inferred from standard REST conventions and must be validated against the documentation provided during onboarding. All endpoints, auth headers, and status values are configurable via `.env`.

---

## Authentication & Environment

| Item | Detail |
|------|--------|
| Base URL (inferred) | `https://sandbox-api.realpay.co.za` (sandbox), `https://api.realpay.co.za` (production) — configurable |
| Auth | Secure API key; exact header name configurable (`X-API-Key` or `Authorization: Bearer`) |
| Format | RESTful JSON |
| Sandbox | Available; credentials and URL supplied during onboarding |

---

## Capability Matrix

| Operation | Payment Method | Supported | Evidence | Sandbox Tested | Implemented |
|-----------|--------------|-----------|----------|----------------|-------------|
| Lender funding | payment_link | **No** | No explicit "payment link" product in RealPay docs. E-Commerce supports online payments but a dedicated payment-link capability is not documented. | N/A | No |
| Lender funding | debit_order | **Yes** | RealPay Collections supports EFT Debits, DebiCheck, EnDO (Namibia), and Registered Mandate. A debit order can collect funds from a lender's bank account into the platform. | No live credentials obtained | Yes |
| Borrower disbursement | bank_payout | **Yes** | RealPay Payouts product explicitly disburses salaries, refunds, supplier payments, and insurance claims to bank accounts in South Africa and Namibia. | No live credentials obtained | Yes |
| Borrower disbursement | wallet_payout | **No** | Mobile Money product supports cross-border wallet disbursements, but Namibia availability is not confirmed. Payouts in Namibia is documented only for bank accounts. | N/A | No |
| Borrower repayment | payment_link | **No** | Same reasoning as lender funding payment_link; no explicit payment-link product documented. | N/A | No |
| Borrower repayment | debit_order | **Yes** | RealPay EnDO is "purpose-built for Namibia" and EFT Debits are documented for Southern Africa. Collections are a core RealPay flow. | No live credentials obtained | Yes |
| Lender returns | bank_payout | **Yes** | Same Payouts product used for refunds and supplier payments. | No live credentials obtained | Yes |
| Lender returns | wallet_payout | **No** | Same reasoning as borrower disbursement wallet_payout; Namibia availability not confirmed. | N/A | No |
| Account verification | — | **Partial** | AVS-R is documented for real-time South African bank account verification. Namibia availability is not confirmed. Implemented as an optional pre-payout preflight, not a primary QuickShare operation. | No | Partial |
| Reconciliation | — | **Partial** | Tracking feature and API provide transaction history and reconciliation data. Exact file/schedule format is not public. | No | Partial (status check only) |
| Webhooks | — | **Yes** | API Integration page states "Receive real-time transaction status updates via webhooks". Payload format is not public. | No | Yes (generic mapping) |
| Refund / reversal | — | **Partial** | Tracking mentions returns/failures; Payouts product handles refunds. Exact API operation names not public. | No | Not implemented as explicit operations; failed/reversed statuses mapped from webhooks |

---

## API Endpoints Used (Configurable)

| Purpose | Method | Inferred Endpoint | Notes |
|---------|--------|-------------------|-------|
| Submit collection (debit order) | POST | `/api/v1/collections` | Used for lender funding and borrower repayments. |
| Submit payout | POST | `/api/v1/payouts` | Used for borrower disbursements and lender returns. |
| Verify beneficiary | POST | `/api/v1/verifications` | Optional AVS-R-style pre-payout check; disabled if endpoint is empty. |
| Check status | GET | `/api/v1/transactions/{reference}` | Poll for transaction result. |
| Webhook | POST | Configurable webhook URL | RealPay pushes status events to a merchant endpoint. |

---

## Webhook / Callback Behaviour

RealPay confirms that webhooks deliver real-time transaction status updates. The public site does not specify payload fields or signature algorithm. The adapter therefore uses generic field extraction (`transaction_id`, `reference`, `status`, `amount`, `event_type`) and configurable HMAC-SHA256 signature verification. If no webhook secret is configured, incoming webhooks are accepted.

---

## Known Gaps & Risks

1. **No public API reference.** Endpoint names, payload shapes, and status values are inferred. They are fully configurable, but sandbox validation is required before going live.
2. **Payment links not implemented.** RealPay's E-Commerce product supports online payments, but a standalone payment-link product is not documented.
3. **Wallet payouts not implemented for Namibia.** Mobile Money is supported as a cross-border payout channel, but the Namibia country page only lists Payouts (bank) and EnDO (collections).
4. **AVS-R limited to South Africa.** The public docs position AVS-R as a South African bank verification service. Namibia verification is not confirmed.
5. **Webhook format inferred.** Signature header, algorithm, and payload fields must be confirmed during onboarding and updated in `.env`.
6. **Sandbox credentials required.** API key and sandbox URL are obtained through RealPay onboarding and must not be committed.

---

## Configuration Required

```text
PAYMENT_PROVIDER_DEFAULT=manual
# or for a specific operation
BORROWER_DISBURSEMENT_PROVIDER=realpay
BORROWER_DISBURSEMENT_METHOD=bank_payout
BORROWER_REPAYMENT_PROVIDER=realpay
BORROWER_REPAYMENT_METHOD=debit_order
LENDER_RETURNS_PROVIDER=realpay
LENDER_RETURNS_METHOD=bank_payout
LENDER_FUNDING_PROVIDER=realpay
LENDER_FUNDING_METHOD=debit_order

REALPAY_BASE_URL=https://sandbox-api.realpay.co.za
REALPAY_API_KEY=
REALPAY_SANDBOX=true
REALPAY_AUTH_HEADER=X-API-Key
REALPAY_WEBHOOK_SECRET=
REALPAY_SIGNATURE_HEADER=X-Webhook-Signature
REALPAY_SIGNATURE_ALGORITHM=hmac-sha256
REALPAY_HEALTH_ENDPOINT=
REALPAY_COLLECTIONS_ENDPOINT=/api/v1/collections
REALPAY_PAYOUTS_ENDPOINT=/api/v1/payouts
REALPAY_VERIFICATION_ENDPOINT=/api/v1/verifications
REALPAY_STATUS_CHECK_ENDPOINT=/api/v1/transactions/{reference}
```
