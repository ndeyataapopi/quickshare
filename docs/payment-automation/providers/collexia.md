# Collexia Provider Discovery

**Provider:** Collexia Payments (Pty) Ltd  
**Homepage:** https://collexia.co  
**Headquarters:** Windhoek, Namibia  
**Status:** Licensed Bank of Namibia Payment Facilitator; registered with Payments Association of Namibia (PAN)  
**Discovery Date:** 2026-09-02  

---

## 1. Investigation Method

Public information was gathered from the official Collexia website and related search results. No private sandbox credentials or API documentation were available in the project repository or in publicly accessible developer documentation at the time of discovery. Collexia does not appear to publish open API documentation; integration details are likely shared under NDA or through direct onboarding.

## 2. Confirmed Capabilities

| Capability | Evidence | Notes |
|------------|----------|-------|
| Namibian credit payments (ENCR) | https://collexia.co/products/credit-payments | Bulk/single payouts to Namibian bank accounts (salaries, refunds, grants, supplier payouts). |
| Debit order collections (EnDO) | https://collexia.co/products/debit-orders | Automated collections; explicitly mentions loans, fees, subscriptions, donations. Real-time reporting. |
| API / Host-to-Host integration | https://collexia.co/solutions, FAQ | API and Host-to-Host are offered for enterprise integration. |
| XML / ISO 20022 file support | FAQ | File formats supported for transaction processing and reporting. |
| Real-time reporting | https://collexia.co/products/debit-orders | Merchants can check payment/collection status in real time. |
| SMS notifications | https://collexia.co/products | Transaction lifecycle alerts. |
| Bank of Namibia / PAN regulated | https://collexia.co/compliance | Licensed payment facilitator. |

## 3. Unconfirmed / Not Publicly Documented

The following items were **not confirmed** by official Collexia documentation discovered during this phase. The adapter therefore treats them as unsupported or uses conservative, configurable defaults that must be validated against actual Collexia sandbox evidence before going live.

* Exact REST/Host-to-Host endpoint URLs
* Authentication scheme details (API key, client code, OAuth, signature algorithm)
* Sandbox base URL
* Webhook payload format
* Webhook signature algorithm and header name
* Webhook retry behaviour
* Transaction status model and exact status enum values
* Reconciliation file format and schedule
* Settlement timing
* Transaction limits and sandbox limitations
* Payment links
* Wallet payouts
* Bank account verification

## 4. Inferred Integration Design

Because the exact API specification is not public, the `CollexiaPaymentProvider` adapter is built on configurable endpoints, headers, and status mappings so that the real Collexia API contract can be plugged in without code changes once it is available.

Inferences are based on common Namibian PSP patterns and the product descriptions above:

* **Authentication:** API key passed in `Authorization: Bearer` header, with optional `X-Client-Code` header. Configurable.
* **Payouts:** `POST {base_url}/api/v1/payments` for ENCR-style credit payments.
* **Collections:** `POST {base_url}/api/v1/collections` for EnDO-style debit order collections.
* **Status check:** `GET {base_url}/api/v1/transactions/{reference}`.
* **Webhook signature:** Configurable header (default `X-Webhook-Signature`) using HMAC-SHA256 over the raw request body. This is a common default and is **not confirmed** for Collexia.
* **Webhook payload:** JSON with `event_type`, `transaction_id` (provider reference), `reference` (merchant reference), `status`, `amount`, `currency`.

All inferred defaults are documented in `config/payment_providers.php` under the `collexia` driver.

## 5. Capability Matrix

| Operation | Method | Supported | Evidence | Implemented |
|-----------|--------|-----------|----------|-------------|
| Lender Funding | Manual | Yes | Internal QuickShare fallback | Yes |
| Lender Funding | Payment Link | No | Not mentioned on collexia.co | No |
| Lender Funding | Debit Order | No | EnDO is for collections from debtors; lender funding is an inbound receipt to QuickShare | No |
| Lender Funding | Bank Payout | No | Wrong direction for funding | No |
| Lender Funding | Wallet Payout | No | Not mentioned | No |
| Borrower Disbursement | Manual | Yes | Internal QuickShare fallback | Yes |
| Borrower Disbursement | Payment Link | No | Not mentioned | No |
| Borrower Disbursement | Debit Order | No | Wrong direction for a payout | No |
| Borrower Disbursement | Bank Payout | Yes | ENCR product: payouts to Namibian bank accounts | Yes |
| Borrower Disbursement | Wallet Payout | No | Not mentioned | No |
| Borrower Repayment | Manual | Yes | Internal QuickShare fallback | Yes |
| Borrower Repayment | Payment Link | No | Not mentioned | No |
| Borrower Repayment | Debit Order | Yes | EnDO product: collections including loans | Yes |
| Borrower Repayment | Bank Payout | No | Wrong direction for a collection | No |
| Borrower Repayment | Wallet Payout | No | Not mentioned | No |
| Lender Returns | Manual | Yes | Internal QuickShare fallback | Yes |
| Lender Returns | Payment Link | No | Not mentioned | No |
| Lender Returns | Debit Order | No | Wrong direction for a payout | No |
| Lender Returns | Bank Payout | Yes | ENCR product: refunds/supplier payouts to bank accounts | Yes |
| Lender Returns | Wallet Payout | No | Not mentioned | No |

## 6. Configuration

Environment variables required to enable the Collexia adapter:

```text
PAYMENT_PROVIDER_DEFAULT=collexia

# Sandbox credentials (obtain from Collexia onboarding team)
COLLEXIA_BASE_URL=https://sandbox-api.collexia.co
COLLEXIA_API_KEY=
COLLEXIA_CLIENT_CODE=
COLLEXIA_WEBHOOK_SECRET=
COLLEXIA_SIGNATURE_HEADER=X-Webhook-Signature
COLLEXIA_SIGNATURE_ALGORITHM=hmac-sha256
COLLEXIA_SANDBOX=true
```

When `COLLEXIA_API_KEY` or `COLLEXIA_BASE_URL` are empty, `isConfigured()` returns `false` and the provider will not be selected for automated operations. QuickShare remains in manual mode by default.

## 7. Risks & Next Steps

1. **API contract gap:** The exact REST payload, status enums, and webhook signature must be validated against real Collexia sandbox responses. The current adapter uses configurable placeholders.
2. **Sandbox access:** Sandbox credentials were not found in the repository; they must be obtained from Collexia support or onboarding.
3. **Webhook algorithm:** Default HMAC-SHA256 is unconfirmed. Update `signature_algorithm` config once Collexia confirms their scheme.
4. **Status mapping:** The `parseApiResponse` and `mapWebhookStatus` mappings are based on common PSP values. They should be refined against actual Collexia documentation.
5. **No MobiDebit/MobiPay/RealPay:** These providers are intentionally out of scope for this phase.
