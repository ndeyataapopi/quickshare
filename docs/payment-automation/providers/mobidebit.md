# MobiDebit / MobiPay Namibia Capability Discovery

**Sources inspected**

* https://www.mpay-namibia.com/developers
* https://docs.mobipaid.com/ (Mobipaid developer API reference)
* https://www.mpay-namibia.com/accept-payments/request-payment-link
* https://www.mpay-namibia.com/mobipaid-portal
* https://www.linkedin.com/posts/mobipay-namibia_trust-in-digital-financial-services-must-activity-7485578656048771072-9DM1

**Date:** 2026-09-02  
**Note:** MobiPay Namibia appears to operate on the **Mobipaid** platform (`mpay-namibia.com`). Public MobiDebit API documentation was not found. The MobiDebit product is referenced on LinkedIn as a future Namibian debit-order / disbursement solution, but no REST endpoint list, auth scheme, or webhook format is published. This matrix therefore reflects only **confirmed Mobipaid** capabilities plus explicit **unconfirmed** MobiDebit items.

---

## Authentication & Environment

| Item | Detail |
|------|--------|
| Sandbox base URL | `https://test.mobipaid.io/` |
| Production base URL | `https://live.mobipaid.io/` |
| Auth | HTTP Bearer token (`Authorization: Bearer mp_test_...`) |
| API key prefixes | `mp_test_` (sandbox), `mp_live_` (production) |
| Onboarding | Developer account + merchant connection + application published + merchant enabled |

---

## Capability Matrix

| Operation | Payment Method | Supported | Evidence | Sandbox Tested | Implemented |
|-----------|--------------|-----------|----------|----------------|-------------|
| Lender funding | payment_link | **Yes** | Mobipaid "Payment Requests" API creates an invoice/link sent to customer email/mobile. `POST /v2/payment-requests/` returns `short_url`, `long_url`, `qrcode_link`. | No live credentials obtained | Yes |
| Lender funding | debit_order | **No** | Lender funding is a payment *to* the platform; a debit order pulls from the payer's account, which is conceptually the wrong direction for this operation. Mobipaid does not document a "debit order funding" product. | N/A | No |
| Borrower disbursement | bank_payout | **No** | Mobipaid is a merchant collection gateway. No outbound bank-payout / disbursement API is documented. MobiDebit marketing mentions disbursements but provides no API contract. | N/A | No |
| Borrower repayment | payment_link | **Yes** | Same Payment Requests API can request payment from a borrower via email/SMS/web link. | No live credentials obtained | Yes |
| Borrower repayment | debit_order | **Yes** | Mobipaid supports `payment_type: "DB"` (Direct Debit) and lists Direct Debit as a payment method. Payment request can be configured for debit collection. | No live credentials obtained | Yes |
| Lender returns | bank_payout | **No** | No outbound payout endpoint found. | N/A | No |
| Lender returns | wallet_payout | **No** | Mobipaid Wallet is described as a stored-payment-method wallet for *paying* the merchant, not for outbound wallet payouts. No payout API documented. | N/A | No |
| Account verification | — | **No** | No AVS / bank-account verification API documented. | N/A | No |
| Reconciliation | — | **Partial** | `GET /v2/payments` and `GET /v2/payment-requests/{id}` return payment history. Exact reconciliation file/schedule format is not documented. | No | Partial (status check only) |

---

## API Endpoints Used

| Purpose | Method | Mobipaid Endpoint | Notes |
|---------|--------|-------------------|-------|
| Create payment request / collection | POST | `/v2/payment-requests/` | Used for payment-link funding and repayments, plus debit-order repayments (`payment_type: DB`). |
| Check status | GET | `/v2/payment-requests/{reference}` | Poll for result of a previously created request. |
| Payment callback | POST | Configured `response_url` | Mobipaid POSTs a `response` parameter containing a JSON payload to the merchant's `response_url`. Not a true signed webhook. |
| Refund | POST | `/v2/payment-history/{payment_id}/refund` | Confirmed in docs but not used by QuickShare flows in this phase. |
| Reversal | POST | `/v2/payment-history/{payment_id}/reversal` | Confirmed in docs but not used by QuickShare flows in this phase. |

---

## Webhook / Callback Behaviour

Mobipaid does **not** publish a signed webhook format. Instead it sends a browser/callback POST to the `response_url` supplied when creating the payment request. The POST body contains a single parameter `response`, whose value is a JSON string with fields such as:

* `result` — `ACK` (success) or `NOK` (failure)
* `result_code` — e.g. `000.000.000`, `000.100.110`, `000.200.000`
* `transaction_id`
* `payment_id`
* `amount`
* `currency`
* `result_description`

Because the callback is not signed, the adapter treats signature verification as optional: if a `webhook_secret` is configured, an HMAC-SHA256 signature over the raw `response` parameter is verified; otherwise the callback is accepted.

---

## Known Gaps & Risks

1. **No public MobiDebit API docs.** The MobiPay/LinkedIn posts position MobiDebit as a future Namibian debit-order + disbursement product, but endpoint names, payloads, and authentication are not published. The implementation therefore uses the **Mobipaid** API (the platform MobiPay Namibia currently exposes).
2. **No outbound payouts.** Borrower disbursements and lender returns cannot be automated through Mobipaid based on public docs. These remain on Collexia or manual fallback.
3. **No account verification endpoint.** QuickShare cannot pre-verify borrower/lender bank accounts before creating a payment request.
4. **Callback is not a signed webhook.** Idempotency and amount validation are still enforced by the generic webhook service, but cryptographic origin verification is only possible if Mobipaid later documents a signature mechanism.
5. **Currency support.** Mobipaid supports many currencies; QuickShare defaults to `NAD`. Actual NAD settlement availability must be confirmed during onboarding.

---

## Configuration Required

```text
PAYMENT_PROVIDER_DEFAULT=manual
# or for a specific operation
LENDER_FUNDING_PROVIDER=mobidebit
LENDER_FUNDING_METHOD=payment_link
BORROWER_REPAYMENT_PROVIDER=mobidebit
BORROWER_REPAYMENT_METHOD=debit_order

MOBIDEBIT_BASE_URL=https://test.mobipaid.io
MOBIDEBIT_API_KEY=mp_test_...
MOBIDEBIT_SANDBOX=true
MOBIDEBIT_RESPONSE_URL=https://quickshare.example.com/api/payments/webhooks/mobidebit
MOBIDEBIT_REDIRECT_URL=https://quickshare.example.com/payment/receipt
# Optional: only needed if Mobipaid signs callbacks.
MOBIDEBIT_WEBHOOK_SECRET=
```
