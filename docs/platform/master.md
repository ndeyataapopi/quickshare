# QUICKSHARE — MASTER PLATFORM DOCUMENTATION

**Documentation Version:** 0.4.0  
**Platform Status:** Private Beta / Production Preparation  
**Last Updated:** 2026-08-28  
**Last Reviewed:** 2026-08-28  
**Owner:** QuickShare Engineering  
**Environment:** `https://quickshare.nepticgroup.com` (configured in `.env`)

---

## Document Control

This is the single source of truth for the QuickShare platform. It is maintained by QuickShare Engineering and is intended for authorised administrators, developers, auditors and product owners.

Every significant feature or architectural change must update this documentation before the change is considered complete. Status labels are used throughout:

- **Implemented / Current** — in production and operating.
- **Historical** — a previous version or decision that has been superseded.
- **Proposed** — under active design or review.
- **Planned** — on the roadmap but not yet scheduled.

---

## 1. Platform Overview

QuickShare is a Namibian peer-to-peer lending platform that connects borrowers and lenders. It operates with real users and real financial transactions.

### 1.1 What QuickShare Does

- User registration, email/phone verification and referral tracking.
- KYC document submission, encryption, fraud scanning and admin review.
- Affordability assessment for borrower credit capacity.
- Loan application, admin review, approval/rejection and marketplace listing.
- Lender funding intentions and manual payment verification.
- Borrower disbursement and confirmation.
- Repayment scheduling, repayment proof submission and admin confirmation.
- Lender return allocation and payout recording.
- Activity logging, audit trails and reporting.

### 1.2 What QuickShare Does NOT Do

- It does **not** execute the movement of money automatically in production today.
- It does **not** provide a public payment-provider API for live Namibian bank transfers.
- It does **not** hold a banking or lending licence unless separately obtained by the operating entity.
- It does **not** expose KYC documents, API keys or bank credentials in documentation.

### 1.3 Target Users

| User | Role in QuickShare |
|---|---|
| Borrower | Client who requests and repays a loan. |
| Lender | Client who funds loans and receives capital plus return. |
| Client | A registered user who may act as borrower and/or lender. |
| Admin | Full system access; user, KYC, loan and configuration management. |
| Compliance Officer | KYC review, fraud monitoring, regulatory checks. |
| Finance Officer | Funding, disbursement, repayment and reconciliation. |

### 1.4 Current Production Status

QuickShare is in private beta. The platform is live with controlled onboarding (referral-only). Money moves via manual bank/wallet transfers that are recorded and reconciled inside QuickShare; no automated payment execution is enabled by default.

---

## 2. QuickShare Evolution

### 2.1 Version 0.1 — Original Concept

- **Problem:** Limited access to small, short-term credit in Namibia; friction between informal borrowers and lenders.
- **Concept:** A digital marketplace where individuals can lend small amounts to other verified individuals.
- **Assumptions:** Manual onboarding and manual payment verification would be acceptable for launch; regulation could be addressed incrementally.
- **Target users:** Employed or self-employed Namibians needing short-term liquidity.
- **Financial model:** Borrower repays principal + interest; lender receives principal + return; QuickShare charges a platform fee.

### 2.2 Version 0.2 — Product Evolution

- Trust score introduced to inform risk and loan limits.
- KYC requirement added before borrowing or lending.
- Affordability assessment introduced as an advisory tool for admins.
- Fraud detection and activity logging added.
- Referral-only registration introduced for private beta.

### 2.3 Version 0.3 — Private Beta

- Modular Laravel architecture (Auth, KYC, Loans, Funding, Repayments, Marketplace, Notifications, TrustScore, Collections, Admin).
- Manual payment workflow for lender funding, disbursement and repayments.
- Loan marketplace with marketplace/partially_funded/funded status flow.
- SMS/Email notifications, queue workers, Redis.
- Document archive: the original private beta runbook remains in `docs/operations-manual.md`.

### 2.4 Version 0.4 — Production Preparation

- Master documentation system introduced.
- Mifos X integration prepared but remains disabled by default.
- Provider-agnostic payment architecture designed but not yet implemented.
- Security, audit and role/permission hardening.
- Reconciliation and collections workflows formalised.

### 2.5 Current Version

The current implementation is the source of truth. Where this documentation differs from historical specifications, the implementation governs and the difference is noted.

---

## 3. Current QuickShare Lifecycle

```
USER REGISTRATION
        ↓
EMAIL + PHONE VERIFICATION
        ↓
KYC SUBMISSION  (pending)
        ↓
KYC REVIEW      → approved / rejected / resubmission_required
        ↓
AFFORDABILITY ASSESSMENT (advisory)
        ↓
LOAN REQUEST    (draft → pending_review)
        ↓
LOAN REVIEW     → approved (marketplace) / rejected / cancelled
        ↓
LOAN FUNDING    (lender intentions → pending → confirmed)
        ↓
DISBURSEMENT    (awaiting → processing → pending_borrower_confirmation → disbursed → active)
        ↓
REPAYMENT SCHEDULE
        ↓
REPAYMENT DUE   → pending / partial / overdue
        ↓
BORROWER REPAYS (submits proof → pending_approval)
        ↓
ADMIN CONFIRMS  → paid
        ↓
LENDER RETURN ALLOCATION
        ↓
LOAN COMPLETED
```

### 3.1 KYC

- `KycSubmission` status: `pending`, `approved`, `rejected`, `resubmission_required`.
- Documents: `national_id`, `selfie`, `payslip`, `bank_statement`.
- Required documents (per validation): all four.
- AES encryption at rest, SHA-256 hashes for integrity, queued scanning.

### 3.2 Loan

- `Loan` status: `draft`, `pending_review`, `marketplace`, `partially_funded`, `funded`, `awaiting_disbursement`, `active`, `disbursed`, `completed`, `defaulted`, `cancelled`, `rejected`.
- Interest, platform fee and total repayment are calculated on approval.
- Loan agreement is generated as a PDF; borrower consent is recorded with IP and user agent.
- Cancellable only while `draft` or `pending_review`.

### 3.3 Funding

- `FundingTransaction` status: `pending`, `confirmed`, `rejected`, `cancelled`, `refunded`.
- Lender submits an amount, payment method, reference and proof.
- Finance officer/admin confirms after verifying the external transfer.
- Confirmed funding creates an `Investment` (`pending` → `active` → `completed`).

### 3.4 Disbursement

- `DisbursementTransaction` status: `awaiting_disbursement`, `awaiting_approval`, `processing`, `pending_borrower_confirmation`, `disbursed`, `failed`, `retried`, `rejected_by_borrower`.
- Admin triggers disbursement after funding is complete.
- Current code uses a simulation stub to produce a `BNK-*` reference; the real transfer is manual.
- Borrower confirms receipt before the loan becomes `active`.
- Failed disbursements may be retried up to three times.

### 3.5 Repayment

- `Repayment` status: `pending`, `partial`, `paid`, `overdue`, `defaulted`, `pending_approval`, `rejected`.
- Overdue is calculated daily for past-due `pending`/`partial` repayments.
- Borrower submits proof; admin reviews and approves or rejects.
- Late penalties may apply weekly up to a configured max ratio.

### 3.6 Lender Return

- `LenderRepayment` status: `pending`, `processed`, `failed`.
- Calculated from `Repayment` allocation: principal return, interest earned and penalty share.
- Proportional to the lender's funding percentage of the loan.

---

## 4. Manual Payment Workflow

The most important principle:

> **QuickShare records the business meaning of a payment. The actual movement of money happens outside QuickShare and is confirmed by an administrator.**

### 4.1 Lender Funding

1. Lender selects a loan on the marketplace.
2. Lender transfers the funding amount to the QuickShare receiving account listed in `config/payments.php`.
3. Lender records the payment method, reference and uploads proof.
4. QuickShare creates a `FundingTransaction` with status `pending`.
5. Finance officer reviews the proof and confirms or rejects.
6. On confirmation, `Investment` and `Earning` records are created; the loan may move to `funded`.

### 4.2 Borrower Disbursement

1. Admin verifies that the loan is fully funded.
2. Admin records an outgoing `DisbursementTransaction`.
3. The actual transfer is performed by QuickShare operations outside the application.
4. Borrower confirms receipt in the platform.
5. The disbursement status becomes `disbursed`; the loan becomes `active`.

### 4.3 Borrower Repayment

1. Borrower transfers the repayment amount to the appropriate QuickShare receiving account.
2. Borrower uploads proof and records the reference.
3. QuickShare creates a `Repayment` with status `pending_approval`.
4. Admin verifies the proof and marks the repayment `paid`.

### 4.4 Lender Return

1. QuickShare calculates each lender's allocation from the approved repayment.
2. Operations transfers the capital plus return to each lender outside the system.
3. `LenderRepayment` records are marked `processed` when confirmed.

---

## 5. Future Payment Architecture

> **Status: Proposed**

The intended architecture decouples the loan engine from any single payment provider:

```
            QUICKSHARE
                |
          LOAN ENGINE
                |
                ▼
      PAYMENT ORCHESTRATOR
                |
    ┌───────────┼───────────┐
    │           │           │
  MANUAL     FAKE/     PROVIDER A/B
    │       SANDBOX
    ▼           ▼           ▼
```

- **Manual** remains the production fallback.
- **Fake/Sandbox** (QuickShare internal) simulates `success`, `pending`, `failed`, `timeout`, `duplicate`, `reversed` and `webhook_duplicate`.
- **Provider integrations** (Collexia, MobiDebit, RealPay, PAYM8, future) are optional execution mechanisms.
- The loan engine defines the business meaning; the provider executes the movement of money.
- Mifos X is the only external financial integration currently prepared; it is **disabled by default** and defaults to the demo endpoint.

---

## 6. Payment Provider Configuration

> **Status: Planned**

Provider selection, environment, sandbox/live mode, credentials, webhooks, timeouts, retries and reconciliation are planned to be configurable through:

- `.env` variables (API keys, base URLs, webhook secrets).
- `config/payments.php` (receiving accounts).
- `config/mifos.php` (Mifos X integration, disabled by default).
- Future admin settings screen.

| Provider | Status |
|---|---|
| Manual | Implemented / Current |
| Fake/Sandbox | Planned |
| Mifos X | Prepared, disabled by default |
| Collexia | Not implemented |
| MobiDebit | Not implemented |
| RealPay | Not implemented |
| PAYM8 | Not implemented |

All API keys, account numbers and webhook secrets must be stored in `.env` only and must never be committed or exposed in documentation.

---

## 7. KYC and Affordability

### 7.1 KYC

- All borrowers and lenders must complete KYC before participating in the marketplace.
- Documents are encrypted, scanned and reviewed by a compliance officer or admin.
- Approved KYC promotes the selfie to the user's profile picture.
- KYC submissions create `ActivityLog` and `AuditLog` entries.

### 7.2 Affordability

- The affordability assessment is **advisory** and does not block loan approval.
- Inputs: monthly income, expenses, existing debt, payslip, bank statement data.
- Weighted score: DTI (30%), trust score (25%), repayment history (20%), disposable income (15%), bank stability (10%).
- Decision: `approve`, `manual_review`, `reject`.
- Hard reject rules: DTI > 50%, trust score below `loan.minimum_borrow_score`, 2+ defaults, requested amount above tier limit.

---

## 8. Loan Application, Approval and Rejection

### 8.1 Application

- Borrower creates a loan with purpose, description, amount and term.
- Saved as `draft` until submitted for review.
- Submitted loans move to `pending_review` and notify admins.

### 8.2 Approval

- Admin verifies KYC, affordability, trust score, existing exposure, fraud flags and agreement consent.
- Approved amount may be lower than requested.
- Loan moves to `marketplace`, interest/fee/repayment date are calculated and the agreement PDF is generated.

### 8.3 Rejection

- Admin provides a rejection reason; loan moves to `rejected`.
- Borrower cannot cancel a loan after it is on the marketplace.

---

## 9. Funding, Disbursement and Borrower Confirmation

### 9.1 Funding

- Loans are listed on the marketplace for lenders to fund.
- Marketplace `min_funding_amount` and `min_funding_percent` are configurable.
- Multiple lenders may fund the same loan; the loan becomes `funded` when the approved amount is fully covered.

### 9.2 Disbursement

- Only `funded` loans can be disbursed.
- Disbursement records `gross_amount`, `platform_fee`, `net_amount` and a transaction reference.
- The real transfer is performed outside the system.
- Borrower confirms receipt; otherwise the disbursement can be `rejected_by_borrower` or `failed`.

---

## 10. Repayment and Lender Return Allocation

### 10.1 Repayment

- A `Repayment` is created when the loan becomes `active`.
- The due date is calculated from `loan_term_days`.
- Borrower repays outside the system and submits proof.
- Admin verifies and marks `paid` or `rejected`.

### 10.2 Lender Return

- On a `paid` repayment, `LenderRepayment` records are generated for each confirmed funder.
- Allocation: `principal_return`, `interest_earned`, `penalty_share`.
- Proportional to the lender's percentage of total funding.
- Payout recorded as `processed` after the external transfer is confirmed.

---

## 11. Trust Score and Tiers

Trust tiers are defined in `config/loan.php`:

| Tier | Score | Maximum Loan | Lender Return % |
|---|---|---|---|
| Bronze | 0 – 49.99 | N$ 500 | 30% |
| Silver | 50 – 69.99 | N$ 1,000 | 29% |
| Gold | 70 – 84.99 | N$ 1,500 | 27% |
| Platinum | 85 – 100 | N$ 2,500 | 25% |

The trust score is recalculated on login and is affected by KYC status, repayment history, defaults, referrals and activity.

---

## 12. Technical Architecture

- **PHP:** `^8.2`
- **Laravel:** `12.60.2` (`composer.json`)
- **Frontend:** Vite 5, TailwindCSS 3, Alpine.js, Bootstrap 5.3, ApexCharts
- **Database:** MySQL (production), SQLite (tests)
- **Cache / Queue / Sessions:** Redis
- **Auth:** Laravel Breeze, Sanctum for API, Spatie Permission for RBAC
- **PDF:** barryvdh/laravel-dompdf for loan agreements
- **Modules:** Auth, KYC, Loans, Funding, Marketplace, Repayments, Notifications, TrustScore, Collections, Admin, Users
- **Testing:** Pest, PHPUnit 11.5
- **PDF generation:** `barryvdh/laravel-dompdf`

---

## 13. Database Architecture

Important tables (derived from migrations and models):

| Table | Purpose |
|---|---|
| `users` | Platform users, auth, trust score, verification timestamps. |
| `kyc_submissions` | KYC review status and reviewer. |
| `kyc_documents` | Individual KYC files, type, scan result, encrypted path. |
| `loans` | Loan applications, lifecycle, amounts, agreement, reviewer. |
| `affordability_assessments` | Advisory affordability results per loan. |
| `funding_transactions` | Lender funding records and proof. |
| `investments` | Confirmed lender investments per loan. |
| `earnings` | Expected/received lender earnings. |
| `disbursement_transactions` | Outgoing disbursement records. |
| `repayments` | Borrower repayment records and proof. |
| `lender_repayments` | Lender return allocations. |
| `collection_cases` / `collection_logs` | Overdue follow-up. |
| `fraud_flags` | Fraud alerts and review status. |
| `activity_logs` / `audit_logs` | Activity and state-change audit trail. |
| `roles` / `permissions` / `model_has_roles` / `model_has_permissions` | Spatie RBAC. |

No sensitive values (passwords, keys, tokens, bank numbers, KYC document content) are exposed in this documentation.

---

## 14. Security and RBAC

- Spatie Permission with guards: `web`.
- Roles: `admin`, `compliance_officer`, `finance_officer`, `client`.
- Permissions include `approve_kyc`, `reject_kyc`, `manage_users`, `manage_loans`, `approve_loans`, `reject_loans`, `manage_funding`, `manage_disbursements`, `manage_repayments`, `manage_collections`, `view_reports`, `view_audit_logs`, `manage_fraud_alerts`, `impersonate_users`, and client actions.
- Admin routes protected by `auth`, `verified` and `role:admin|compliance_officer|finance_officer`.
- Additional middleware: `active_user`, `kyc_verified`, `prevent_impersonation`.
- Rate limiting: 60 req/min API, 10 req/min auth.
- File uploads restricted by extension and size; KYC documents encrypted on a private disk.
- Passwords hashed, sessions in Redis, CSRF on web forms.
- `view_documentation` permission added for the documentation viewer.

---

## 15. Administrative Workflows

| Action | Required Permission / Role |
|---|---|
| User management | `manage_users` (admin/compliance) |
| KYC review | `approve_kyc` / `reject_kyc` |
| Loan approval/rejection | `approve_loans` / `reject_loans` |
| Run affordability | `manage_loans` |
| Funding confirmation | `manage_funding` |
| Disbursement | `manage_disbursements` |
| Repayment confirmation | `manage_repayments` |
| Collections | `manage_collections` |
| Fraud review | `manage_fraud_alerts` |
| View audit logs | `view_audit_logs` |
| View documentation | `view_documentation` (admin) |

---

## 16. Auditability and Accounting

- `AuditLog` records polymorphic auditable events with `old_values` and `new_values`.
- `ActivityLog` records the actor, subject, description, status transition and related financial identifiers.
- Every important state change is logged: KYC decisions, loan approvals/rejections, funding confirmations, disbursement, repayments, role/permission changes.
- Future improvement: link `Loan.reference`, `FundingTransaction.transaction_reference`, `Repayment.transaction_reference` and external provider references in a dedicated reconciliation record.

---

## 17. Payment Reconciliation

> **Status: Partially Implemented**

QuickShare records business references; providers and banks record transaction/settlement references. The three identifiers must eventually be linked:

| Record | Example |
|---|---|
| QuickShare business record | `QS-1024` |
| QuickShare payment transaction | `FUND-a1b2c3`, `DISB-d4e5f6`, `REPY-g7h8i9` |
| Provider transaction | `TXN-ABC123` (future) |
| Bank settlement | `SETTLEMENT-98765` (future) |

Currently, only the first two levels are implemented. Provider and bank reconciliation are planned.

---

## 18. Sandbox Strategy

> **Status: Proposed**

Three sandbox concepts are planned:

- **Sandbox A:** Internal fake provider for deterministic success/failure/timeout/duplicate/reversal testing.
- **Sandbox B:** Actual provider sandboxes (Collexia, MobiDebit, RealPay, PAYM8) where available.
- **Sandbox C:** Controlled live/regulatory testing with limited users and transaction caps.

No sandbox is enabled in production by default. `MIFOS_ENABLED=false` in `config/mifos.php`.

---

## 19. Deployment and Infrastructure

- Production: PHP-FPM + Nginx/Apache, MySQL, Redis.
- Queue worker managed by Supervisor or SystemD; see `docs/queue-deployment.md`.
- GitHub Actions deployment workflow in `.github/workflows/deploy.yml`.
- Environment variables in `.env` (local) or `.env.production`.
- Backups, logging and health checks are configured in `.env.production.example`.
- Required `.env` placeholders include `PAYMENT_PROVIDER_API_KEY=<configured-secret>` and `MIFOS_USERNAME=<configured-secret>`.

---

## 20. Testing and QA

| Workflow | Manual | Fake/Sandbox | Provider Sandbox | Production |
|---|---|---|---|---|
| Funding | Yes | Planned | Planned | Controlled |
| Disbursement | Yes | Planned | Planned | Controlled |
| Repayment | Yes | Planned | Planned | Controlled |
| Lender payout | Yes | Planned | Planned | Controlled |

Test suites: Pest/PHPUnit unit, feature, integration, authorisation, workflow, payment, webhook, failure and regression tests.

---

## 21. Compliance and Regulatory Considerations

- KYC/AML processes are implemented as an operational control; final legal interpretation requires local counsel.
- Data protection is addressed through encryption, access control and privacy/terms pages.
- QuickShare does not claim formal regulatory compliance; status is documented as **Implemented Control**, **Design Consideration** or **Pending Legal/Regulatory Confirmation**.
- Payment provider integrations will be **Provider-Dependent** and may require regulatory approval before activation.

---

## 22. Known Limitations

| Limitation | Impact | Status |
|---|---|---|
| Automated payment execution is not enabled. | Money moves manually; operational overhead. | Current / Accepted |
| Payment provider integrations are not implemented. | Scalability and speed depend on future work. | Planned |
| Provider/bank reconciliation is manual. | Reconciliation effort and potential mismatch risk. | Planned |
| Affordability is advisory; admin can override. | Risk of poor lending decisions. | Current |
| Mifos X integration is disabled and points to demo. | No live external loan ledger sync. | Prepared / Disabled |

---

## 23. Roadmap

### Implemented
- Modular Laravel architecture.
- KYC, trust score, loans, funding, disbursements, repayments, collections, audit.
- Spatie RBAC and admin panel.
- Manual payment workflow.
- Master documentation system (this document).

### In Progress
- Production hardening and regulatory review.

### Planned
- Fake/Sandbox payment provider.
- Mifos X production enablement.
- Collexia / MobiDebit / RealPay / PAYM8 integrations.
- Automated reconciliation reports.

### Deferred / Under Evaluation
- Admin inline documentation editor.
- Database-backed documentation versioning.

---

## 24. Architecture Decision Records

- ADR-001: Keep Manual Payments — `docs/adrs/001-manual-payments.md`
- ADR-002: Provider-Agnostic Payment Execution — `docs/adrs/002-provider-agnostic-payments.md`
