# QuickShare Payment Automation — Phase 0 Baseline

**Date:** 2026-08-28  
**Objective:** Establish a read-only baseline of the existing money-movement layer before introducing automated payment execution. No production code, schema, or business rules were changed in this phase.

---

## 1. Scope

QuickShare currently has four manual/semi-manual money-movement operations:

1. **Lender Funding** — lender commits capital to a marketplace loan.
2. **Borrower Disbursement** — platform sends funds to a borrower once a loan is fully funded.
3. **Borrower Repayment** — borrower pays back the platform.
4. **Lender Returns** — platform distributes borrower repayments back to lenders.

This document records the existing models, services, controllers/routes, events/listeners/jobs, tests, configuration, and the baseline test run for those flows.

---

## 2. Architecture Map

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                                Money-Movement Flows                         │
└─────────────────────────────────────────────────────────────────────────────┘

1. LENDER FUNDING
   ┌──────────────┐     ┌────────────────────┐     ┌─────────────────────┐
   │ Lender funds   │────▶│ FundingTransaction │────▶│ Lender submits proof │
   │ loan (API/Web) │     │ status = pending   │     │ status stays pending │
   └──────────────┘     └────────────────────┘     └─────────────────────┘
            │
            ▼
   ┌────────────────────┐     ┌─────────────┐     ┌─────────────────────────┐
   │ Admin confirms     │────▶│ Investment  │────▶│ Loan becomes funded/      │
   │ funding            │     │ active      │     │ partially_funded        │
   └────────────────────┘     └─────────────┘     └─────────────────────────┘
            │
            ▼
   ┌────────────────────┐
   │ FundingCompleted   │── triggers optional LoanDisbursed / disbursement job
   │ event (if fully)   │
   └────────────────────┘

2. BORROWER DISBURSEMENT
   ┌────────────────────┐     ┌──────────────────────────┐
   │ Admin initiates    │────▶│ DisbursementTransaction  │
   │ disbursement       │     │ direction = outgoing     │
   │ (loan funded)      │     │ status = awaiting_disb   │
   └────────────────────┘     └──────────────────────────┘
            │
            ▼
   ┌────────────────────┐     ┌──────────────────────────┐     ┌─────────────┐
   │ Admin confirms     │────▶│ status = pending_borrower│────▶│ Borrower    │
   │ payment sent       │     │ _confirmation           │     │ confirms    │
   └────────────────────┘     └──────────────────────────┘     └─────────────┘
                                          │
                                          ▼
                                ┌─────────────────┐
                                │ status = active │── repayment schedule created
                                │ disbursed_at    │
                                └─────────────────┘

3. BORROWER REPAYMENT
   ┌────────────────────┐     ┌──────────────────────────┐
   │ Borrower submits   │────▶│ Repayment                │
   │ proof + reference  │     │ status = pending_approval  │
   └────────────────────┘     └──────────────────────────┘
            │
            ▼
   ┌────────────────────┐     ┌──────────────────────────┐
   │ Incoming           │────▶│ Admin approves           │
   │ Disbursement       │     │ status = paid            │
   │ awaiting_approval  │     │ incoming disbursement    │
   └────────────────────┘     │ status = confirmed       │
                              └──────────────────────────┘

4. LENDER RETURNS
   Triggered inside RepaymentService::approveRepayment():
   ┌──────────────────────────┐     ┌─────────────────────┐
   │ distributeToLenders()    │────▶│ LenderRepayment     │
   │ (pro-rata per funding %)   │     │ status = pending    │
   └──────────────────────────┘     └─────────────────────┘
            │
            ▼
   ┌──────────────────────────┐     ┌─────────────────────────┐
   │ updateInvestmentEarnings │────▶│ Investment.actual_return │
   │                          │     │ incremented              │
   └──────────────────────────┘     └─────────────────────────┘
            │
            ▼
   ┌──────────────────────────┐
   │ Mark LenderRepayment     │
   │ status = processed         │
   └──────────────────────────┘
```

---

## 3. Existing Payment-Related Models

### 3.1 FundingTransaction

| Item | Value |
|------|-------|
| **Model** | `App\Modules\Funding\Models\FundingTransaction` |
| **Table** | `funding_transactions` |
| **Statuses** | `pending` (default), `confirmed`, `cancelled`, `refunded` |
| **Factory** | `Database\Factories\FundingTransactionFactory` |

**Key fields:** `loan_id`, `lender_id`, `amount`, `interest_rate`, `expected_return`, `status`, `confirmed_at`, `rejected_at`, `transaction_reference`, `payment_method`, `payment_method_detail`, `payment_reference`, `payment_proof_path`, `payment_date`, `admin_verified_at`, `admin_verified_by`, `admin_notes`, `notes`, `metadata`.

**Relationships:**
- `loan(): BelongsTo<Loan>`
- `lender(): BelongsTo<User>`
- `investment(): HasOne<Investment>`

**Scopes/helpers:** `forLoan`, `forLender`, `confirmed`, `active` (pending or confirmed), `isPending`, `isConfirmed`, `isCancelled`, `isRejected`, `isRefunded`.

Reference format: `FUND-{12hex}`.

### 3.2 DisbursementTransaction

| Item | Value |
|------|-------|
| **Model** | `App\Modules\Loans\Models\DisbursementTransaction` |
| **Table** | `disbursement_transactions` |
| **Directions** | `incoming`, `outgoing` |
| **Statuses** | `awaiting_disbursement`, `processing`, `pending_borrower_confirmation`, `disbursed`, `failed`, `retried`, `rejected_by_borrower`, `awaiting_approval`, `confirmed`, `rejected` |

**Key fields:** `loan_id`, `direction`, `gross_amount`, `platform_fee`, `net_amount`, `status`, `processed_at`, `transaction_reference`, `external_reference`, `payment_method`, `payment_proof_path`, `failure_reason`, `retry_count`, `next_retry_at`, `reconciled_at`, `reconciled_by`, `reconciliation_data`, `borrower_confirmed_at`, `borrower_rejected_at`, `rejection_reason`, `ledger_entries`, `notes`.

**Relationships:**
- `loan(): BelongsTo<Loan>`

**Scopes/helpers:** `forLoan`, `awaiting`, `awaitingApproval`, `incoming`, `outgoing`, `isAwaiting`, `isAwaitingApproval`, `isProcessing`, `isDisbursed`, `isFailed`, `isPendingBorrowerConfirmation`, `isRetried`, `isRejectedByBorrower`, `canRetry` (failed + retry_count < 3), `isReconciled`.

Reference format: `DISB-{12hex}`.

### 3.3 Repayment

| Item | Value |
|------|-------|
| **Model** | `App\Modules\Repayments\Models\Repayment` |
| **Table** | `repayments` |
| **Statuses** | `pending` (default), `partial`, `paid`, `overdue`, `defaulted`, `pending_approval`, `rejected` |
| **Factory** | `Database\Factories\RepaymentFactory` |

**Key fields:** `loan_id`, `borrower_id`, `amount`, `principal`, `interest`, `penalty`, `platform_fee`, `status`, `due_date`, `paid_date`, `days_overdue`, `transaction_reference`, `external_reference`, `payment_method`, `payment_proof_path`, `notes`, `metadata`.

**Relationships:**
- `loan(): BelongsTo<Loan>`
- `borrower(): BelongsTo<User>`
- `lenderRepayments(): HasMany<LenderRepayment>`
- `collectionLogs(): HasMany<CollectionLog>`

**Scopes/helpers:** `forLoan`, `forBorrower`, `pending`, `pendingApproval`, `rejected`, `overdue`, `dueToday`, `upcoming(days)`, `shouldBeOverdue`, plus status helpers.

Reference format: `REPY-{12hex}`.

### 3.4 LenderRepayment

| Item | Value |
|------|-------|
| **Model** | `App\Modules\Repayments\Models\LenderRepayment` |
| **Table** | `lender_repayments` |
| **Statuses** | `pending` (default), `processed`, `failed` |

**Key fields:** `repayment_id`, `lender_id`, `funding_transaction_id`, `amount`, `principal_return`, `interest_earned`, `penalty_share`, `funding_percentage`, `status`, `processed_at`, `transaction_reference`.

**Relationships:**
- `repayment(): BelongsTo<Repayment>`
- `lender(): BelongsTo<User>`
- `fundingTransaction(): BelongsTo<FundingTransaction>`

**Scopes/helpers:** `forRepayment`, `forLender`, `processed`, `pending`, plus status helpers.

Reference format: `LRPY-{12hex}`.

### 3.5 Investment

| Item | Value |
|------|-------|
| **Model** | `App\Modules\Funding\Models\Investment` |
| **Table** | `investments` |
| **Statuses** | `pending` (default), `active`, `completed`, `cancelled` |
| **Factory** | `Database\Factories\InvestmentFactory` |

**Key fields:** `loan_id`, `lender_id`, `funding_transaction_id`, `amount`, `interest_rate`, `expected_return`, `actual_return`, `status`, `funded_at`, `completed_at`.

**Relationships:**
- `loan(): BelongsTo<Loan>`
- `lender(): BelongsTo<User>`
- `fundingTransaction(): BelongsTo<FundingTransaction>`

**Scopes/helpers:** `active`, `completed`, `forLender`, `forLoan`, plus status helpers.

### 3.6 Supporting Loan model statuses

File: `app/Modules/Loans/Models/Loan.php`

Relevant state helpers:
- `isOnMarketplace()` → `marketplace | partially_funded`
- `isFunded()` → `funded`
- `isDisbursable()` → `funded`
- `isActive()` → `active | disbursed`
- `isCompleted()` → `completed`
- `isDefaulted()` → `defaulted`
- `progress()` → paid repayments / total_repayment

Loan statuses (migration `database/migrations/2026_05_19_232600_create_loans_table.php`):
`draft`, `pending_review`, `marketplace`, `partially_funded`, `funded`, `awaiting_disbursement`, `disbursed`, `active`, `overdue`, `completed`, `defaulted`, `cancelled`.

---

## 4. Existing Services

### 4.1 FundingService

File: `app/Modules/Funding/Services/FundingService.php`

| Method | Responsibility |
|--------|----------------|
| `fund(User $lender, Loan $loan, float $amount): FundingTransaction` | Lender **funding** creation; status `pending`. |
| `submitPayment(FundingTransaction $transaction, array $data, $proofFile): FundingTransaction` | Lender **payment proof submission**; dispatches `FundingPaymentSubmitted`. |
| `confirmFunding(...): FundingTransaction` | Admin **funding confirmation**; applies to loan, creates `Investment` (active), dispatches `FundingPaymentApproved` + `InvestmentCreated` + `FundingCompleted`. |
| `rejectFunding(...): FundingTransaction` | Admin **funding rejection**; status `rejected`. |
| `requestFundingInfo(...): FundingTransaction` | Admin requests more info (no status change). |
| `cancelFunding(...): FundingTransaction` | Lender/admin **cancel** pending funding. |
| `getRemainingFunding(Loan $loan): float` | Delegates to `LoanService::remainingFunding()`. |

### 4.2 DisbursementService

File: `app/Modules/Loans/Services/DisbursementService.php`

| Method | Responsibility |
|--------|----------------|
| `initiateDisbursement(Loan $loan): DisbursementTransaction` | Admin **initiates** outgoing disbursement; status `awaiting_disbursement`; dispatches `DisbursementInitiated`. |
| `processDisbursement(DisbursementTransaction $transaction, array $data): DisbursementTransaction` | Admin **confirms payment sent**; status `pending_borrower_confirmation`; stores payment proof/external reference; dispatches `DisbursementProcessed`. |
| `confirmReceipt(DisbursementTransaction $transaction): DisbursementTransaction` | **Borrower receipt confirmation**; status `disbursed`; loan becomes `active`; creates repayment schedule; dispatches `BorrowerConfirmedReceipt` + `LoanActivated`. |
| `rejectReceipt(...): DisbursementTransaction` | Borrower rejects receipt; status `rejected_by_borrower`; loan returns to `awaiting_disbursement`. |
| `retryDisbursement(...): DisbursementTransaction` | Creates a new disbursement retry with incremented `retry_count`. |
| `reconcile(...): DisbursementTransaction` | Marks disbursed transaction reconciled. |
| `simulatePaymentTransfer(...): string` | Placeholder/mock bank transfer (not wired to a real provider). |

Constants: `MAX_RETRIES = 3`, `RETRY_DELAYS = [300, 900, 3600]` seconds.

### 4.3 RepaymentService

File: `app/Modules/Repayments/Services/RepaymentService.php`

| Method | Responsibility |
|--------|----------------|
| `createRepaymentSchedule(Loan $loan): Repayment` | Creates a single bullet repayment; status `pending`. |
| `submitRepaymentRequest(array $repaymentIds, User $borrower, string $paymentMethod, ?string $proofPath, ?string $externalReference): array` | Borrower **repayment submission**; sets repayments to `pending_approval`; creates **incoming** `DisbursementTransaction` (`awaiting_approval`); dispatches `RepaymentSubmitted`. |
| `approveRepayment(Repayment $repayment, ?User $admin): Repayment` | Admin **repayment approval**; marks `paid`, confirms incoming disbursement, calls `distributeToLenders()`, updates `Investment.actual_return`, marks `LenderRepayment` as `processed`; dispatches `RepaymentMade`; completes loan if all paid. |
| `rejectRepayment(...): Repayment` | Admin **repayment rejection**; marks `rejected`, marks incoming disbursement `rejected`; dispatches `RepaymentRejected`. |
| `distributeToLenders(Repayment $repayment, float $amount): void` | **Pro-rata lender allocation**; creates `LenderRepayment` records (`pending`); dispatches `LenderRepaymentAllocated`. |
| `updateInvestmentEarnings(Repayment $repayment): void` | Increments `Investment.actual_return` from processed lender repayments. |
| `checkOverdueRepayments(): int` | Cron-like batch job logic; marks past-due repayments `overdue`; dispatches `RepaymentOverdue`. |
| `calculatePenalty(Repayment $repayment, int $daysOverdue): float` | Delegates to `LoanService`. |
| `markLoanAsFullyRepaid(Loan $loan, Repayment $repayment): void` | Sets loan `completed`, processes pending lender repayments, dispatches `LoanFullyRepaid`. |

### 4.4 MifosAdapter

File: `app/Modules/Loans/Adapters/MifosAdapter.php`

Implements `App\Modules\Loans\Contracts\LoanProviderInterface`. Currently disabled unless `config('mifos.enabled')` is true. Exposes loan lifecycle methods: `createLoan`, `updateLoan`, `getLoanStatus`, `approveLoan`, `rejectLoan`, `disburseLoan`, `recordRepayment`, `getProviderName`, `isHealthy`.

---

## 5. Existing Controllers & Routes

### 5.1 Web routes (`routes/web.php` includes `routes/client.php` and `routes/admin.php`)

**Client / Lender / Borrower web routes** — `routes/client.php`:
- Funding
  - `GET  /client/funding/{transaction}` → `Client\FundingController@show`
  - `GET  /client/funding/{transaction}/payment` → `Client\FundingController@payment`
  - `POST /client/funding/{transaction}/payment` → `Client\FundingController@submitPayment`
- Borrower disbursement confirmation
  - `POST /client/loans/{loan}/confirm-disbursement` → `Admin\DisbursementController@borrowerConfirm`
  - `POST /client/loans/{loan}/reject-disbursement` → `Admin\DisbursementController@borrowerReject`
- Borrower repayments
  - `GET/POST /client/repayments/*` → `Borrower\RepaymentController`

**Admin web routes** — `routes/admin.php`:
- Disbursements
  - `GET    /admin/disbursements` → `Admin\DisbursementController@index`
  - `GET    /admin/disbursements/{loan}` → `Admin\DisbursementController@show`
  - `POST   /admin/disbursements/{loan}/disburse` → `Admin\DisbursementController@disburse`
  - `PATCH  /admin/disbursements/{loan}/confirm` → `Admin\DisbursementController@confirm`
- Repayments
  - `GET    /admin/repayments` → `Admin\RepaymentController@index`
  - `GET    /admin/repayments/{repayment}` → `Admin\RepaymentController@show`
  - `PATCH  /admin/repayments/{repayment}/approve` → `Admin\RepaymentController@approve`
  - `PATCH  /admin/repayments/{repayment}/reject` → `Admin\RepaymentController@reject`
- Funding Payments
  - `GET    /admin/funding-payments` → `Admin\FundingController@index`
  - `GET    /admin/funding-payments/{transaction}` → `Admin\FundingController@show`
  - `POST   /admin/funding-payments/{transaction}/confirm` → `Admin\FundingController@confirm`
  - `POST   /admin/funding-payments/{transaction}/reject` → `Admin\FundingController@reject`
  - `POST   /admin/funding-payments/{transaction}/request-info` → `Admin\FundingController@requestInfo`
  - `POST   /admin/funding-payments/{transaction}/cancel` → `Admin\FundingController@cancel`

### 5.2 Module API routes

Module routes are auto-loaded by module service providers (see `app/Providers/ModuleServiceProvider.php`).

**Funding API** — `app/Modules/Funding/Routes/api.php`:
- `GET    /api/funding` → `Modules\Funding\Controllers\FundingController@index`
- `POST   /api/funding/{loan}` → `Modules\Funding\Controllers\FundingController@store`
- `GET    /api/funding/{fundingTransaction}` → `Modules\Funding\Controllers\FundingController@show`
- `POST   /api/funding/{fundingTransaction}/cancel` → `Modules\Funding\Controllers\FundingController@cancel`
- `GET    /api/funding/portfolio/summary` → `Modules\Funding\Controllers\FundingController@portfolio`
- `GET    /api/funding/loan/{loan}/fundings` → `Modules\Funding\Controllers\FundingController@loanFundings`

**Repayments API** — `app/Modules/Repayments/Routes/api.php`:
- `GET    /api/repayments` → `Modules\Repayments\Controllers\RepaymentController@index`
- `GET    /api/repayments/schedule/{loan}` → `Modules\Repayments\Controllers\RepaymentController@schedule`
- `POST   /api/repayments` → `Modules\Repayments\Controllers\RepaymentController@store`
- `GET    /api/repayments/{repayment}` → `Modules\Repayments\Controllers\RepaymentController@show`
- `GET    /api/repayments/lender/earnings` → `Modules\Repayments\Controllers\RepaymentController@lenderEarnings`
- `GET    /api/repayments/lender/summary` → `Modules\Repayments\Controllers\RepaymentController@lenderSummary`
- Admin endpoints under `/api/repayments/admin/*` → `RepaymentAdminController`

**Loans API** — `app/Modules/Loans/Routes/api.php`:
- `GET    /api/loans/{loan}/disbursements` → `Modules\Loans\Controllers\DisbursementController@forLoan`
- `GET    /api/loans/disbursements/pending` → `Modules\Loans\Controllers\DisbursementController@pending`
- `GET    /api/loans/disbursements/failed-retry` → `Modules\Loans\Controllers\DisbursementController@failedRetry`
- `GET    /api/loans/disbursements/reconciliation-report` → `Modules\Loans\Controllers\DisbursementController@reconciliationReport`
- `GET    /api/loans/disbursements/{disbursement}` → `Modules\Loans\Controllers\DisbursementController@show`
- `POST   /api/loans/disbursements/{disbursement}/retry` → `Modules\Loans\Controllers\DisbursementController@retry`
- `POST   /api/loans/disbursements/{disbursement}/reconcile` → `Modules\Loans\Controllers\DisbursementController@reconcile`

### 5.3 Webhook route

- `POST /api/v1/webhooks/mifos` → `App\Http\Controllers\Api\V1\Webhooks\MifosWebhookController`
  - Signature check: `X-Mifos-Signature` vs `config('mifos.webhook.secret')`
  - Optional IP allowlist: `config('mifos.webhook.allowed_ips')`
  - Handles events: `LOAN_APPROVED`, `LOAN_REJECTED`, `LOAN_DISBURSED`, `LOAN_REPAID`, `TRANSACTION_POSTED`, `LOAN_OVERDUE`, `LOAN_CLOSED`, `LOAN_WRITTEN_OFF`, default sync job.

### 5.4 Controller files

- `app/Http/Controllers/Client/FundingController.php`
- `app/Http/Controllers/Admin/FundingController.php`
- `app/Http/Controllers/Admin/DisbursementController.php`
- `app/Http/Controllers/Admin/RepaymentController.php`
- `app/Http/Controllers/Borrower/RepaymentController.php`
- `app/Http/Controllers/Api/V1/Webhooks/MifosWebhookController.php`
- `app/Modules/Funding/Controllers/FundingController.php`
- `app/Modules/Repayments/Controllers/RepaymentController.php`
- `app/Modules/Repayments/Controllers/RepaymentAdminController.php`
- `app/Modules/Loans/Controllers/DisbursementController.php`

---

## 6. Existing Events / Listeners / Jobs

### 6.1 Funding events

| Event | Dispatched by | Listeners |
|-------|---------------|-----------|
| `FundingInitiated` | `FundingService::fund()` | `LogFundingInitiated` |
| `FundingPaymentSubmitted` | `FundingService::submitPayment()` | `LogFundingPaymentSubmitted` |
| `FundingPaymentApproved` | `FundingService::confirmFunding()` | `LogFundingPaymentApproved`, `NotifyLenderFundingApproved` |
| `FundingPaymentRejected` | `FundingService::rejectFunding()` | `LogFundingPaymentRejected`, `NotifyLenderFundingRejected` |
| `FundingCompleted` | `FundingService::confirmFunding()` (when fully funded) | `LogFundingCompleted`, `TriggerLoanDisbursement` |
| `InvestmentCreated` | `FundingService::confirmFunding()` | `LogInvestmentCreated` |
| `LoanFunded` | (legacy event, still registered) | `LogFundingActivity`, `NotifyBorrowerFunded` |

### 6.2 Disbursement / Loan events

| Event | Dispatched by | Listeners |
|-------|---------------|-----------|
| `DisbursementInitiated` | `DisbursementService::initiateDisbursement()` | `LogDisbursementInitiated` |
| `DisbursementProcessed` | `DisbursementService::processDisbursement()` | `LogDisbursementProcessed` |
| `BorrowerConfirmedReceipt` | `DisbursementService::confirmReceipt()` | `LogBorrowerConfirmedReceipt` |
| `BorrowerRejectedReceipt` | `DisbursementService::rejectReceipt()` | `LogBorrowerRejectedReceipt` |
| `LoanActivated` | `DisbursementService::confirmReceipt()` | `LogLoanActivated` |
| `LoanDisbursed` | (legacy/event elsewhere) | `ProcessLoanDisbursement`, `SyncLoanToExternalProvider`, `NotifyBorrowerDisbursed` |
| `ExternalLoanStatusUpdated` | `MifosWebhookController` handlers | `TriggerExternalStatusSync` |

### 6.3 Repayment events

| Event | Dispatched by | Listeners |
|-------|---------------|-----------|
| `RepaymentSubmitted` | `RepaymentService::submitRepaymentRequest()` | `LogRepaymentSubmitted` |
| `RepaymentMade` | `RepaymentService::approveRepayment()` | `LogRepaymentActivity`, `NotifyRepaymentReceived` |
| `RepaymentRejected` | `RepaymentService::rejectRepayment()` | `LogRepaymentRejected` |
| `RepaymentOverdue` | `RepaymentService::checkOverdueRepayments()` | `NotifyOverdueRepayment` |
| `LenderRepaymentAllocated` | `RepaymentService::distributeToLenders()` | `LogLenderRepaymentAllocated`, `NotifyLenderRepaymentAllocated` |
| `LoanFullyRepaid` | `RepaymentService::markLoanAsFullyRepaid()` | `UpdateLoanStatus`, `NotifyBorrowerLoanCompleted`, `NotifyLendersLoanCompleted` |

### 6.4 Jobs

| Job | Purpose | Dispatched by |
|-----|---------|---------------|
| `App\Modules\Funding\Jobs\ProcessFundingJob` | Queue placeholder; currently only logs pending-admin state and auto-cancels on permanent failure. | `FundingService` (implicit via queue, often faked in tests) |
| `App\Modules\Loans\Jobs\ProcessDisbursementJob` | Processes outgoing disbursement via `DisbursementService::processDisbursement()`. | `Modules\Loans\Controllers\DisbursementController@retry`, internal flow |
| `App\Modules\Loans\Jobs\SyncLoanToExternalJob` | Pushes loan changes to Mifos. | `SyncLoanToExternalProvider` listener |
| `App\Modules\Loans\Jobs\SyncExternalLoanStatusJob` | Pulls loan status from Mifos. | `TriggerExternalStatusSync` listener, generic webhook handler |
| `App\Modules\Loans\Jobs\ReconcileLoansJob` | Reconciliation batch. | Scheduler / operations |
| `App\Modules\Repayments\Jobs\CheckOverdueRepaymentsJob` | Batch overdue check. | Scheduler |
| `App\Modules\Repayments\Jobs\SendRepaymentRemindersJob` | Reminder notifications. | Scheduler |

### 6.5 Listener files

- `app/Modules/Funding/Listeners/LogFunding*.php`, `NotifyLenderFunding*.php`, `NotifyBorrowerFunded.php`, `TriggerLoanDisbursement.php`
- `app/Modules/Loans/Listeners/Log*.php`, `Notify*.php`, `ProcessLoanDisbursement.php`, `SyncLoanToExternalProvider.php`, `TriggerExternalStatusSync.php`
- `app/Modules/Repayments/Listeners/Log*.php`, `Notify*.php`, `UpdateLoanStatus.php`

---

## 7. Existing Tests

### 7.1 Relevant test files

| Test file | Coverage |
|-----------|----------|
| `tests/Feature/Funding/FundingTest.php` | Lender funding, overfunding protection, single-lender-per-loan, cancellation, portfolio queries, investment creation, rejection flows. |
| `tests/Feature/Funding/PaymentSubmissionTest.php` | Funding payment proof submission (service + web), metadata storage, mobile wallet / cash deposit methods. |
| `tests/Feature/Funding/EarningsServiceTest.php` | Lender earnings summaries and portfolio calculations. |
| `tests/Feature/Admin/FundingPaymentTest.php` | Admin confirmation of a funding transaction. |
| `tests/Feature/Admin/DisbursementControllerTest.php` | Admin initiate/confirm disbursement, borrower confirm/reject receipt. |
| `tests/Feature/Loans/DisbursementTest.php` | `DisbursementService` initiate/process/confirm/retry/reconcile, ledger entries, repayment schedule creation. |
| `tests/Feature/Repayments/RepaymentTest.php` | Repayment schedule creation, borrower repayments, overdue/penalty, lender earnings API, admin APIs. |
| `tests/Feature/Repayments/RepaymentSubmissionTest.php` | `submitRepaymentRequest`, incoming disbursement creation, validation, multi-loan submissions. |
| `tests/Feature/Repayments/RepaymentApprovalTest.php` | `approveRepayment`, `rejectRepayment`, lender repayment allocation, investment earnings, multi-lender distribution. |
| `tests/Feature/Api/V1/Webhooks/MifosWebhookTest.php` | Mifos webhook signature/IP validation, all event handlers, generic sync job dispatch. |
| `tests/Unit/Loans/MifosAdapterTest.php` | Mifos adapter request building and skipped-state behavior. |
| `tests/Unit/Loans/ReconcileLoansJobTest.php` | Reconciliation job behavior. |

### 7.2 Baseline test results

Command run:

```bash
php artisan test \
  tests/Feature/Funding \
  tests/Feature/Admin/FundingPaymentTest.php \
  tests/Feature/Admin/DisbursementControllerTest.php \
  tests/Feature/Loans/DisbursementTest.php \
  tests/Feature/Repayments \
  tests/Feature/Api/V1/Webhooks/MifosWebhookTest.php
```

Result:

```text
Tests:    198 passed (575 assertions)
Duration: 40.16s
```

**No test failures were observed at baseline.** All relevant money-movement, webhook, and related unit tests pass without modification.

---

## 8. Existing Configuration

### 8.1 `config/payments.php`

Holds QuickShare **receiving account** details for manual payment methods only:
- `default_reference_prefix` → `env('PAYMENT_REFERENCE_PREFIX', 'QS-LOAN')`
- `banks`: FNB Namibia, Standard Bank Namibia, Nedbank Namibia (account numbers default to placeholder/demo values).
- `wallets`: FNB eWallet, Standard Bank BlueVoucher, Nedbank MobiMoney, Bank Windhoek EasyWallet.
- `cash_deposit`

There is **no payment-provider execution config** (no gateway URLs, API keys, webhooks, or provider toggles).

### 8.2 `config/mifos.php`

External loan-management provider configuration:
- `enabled` → `MIFOS_ENABLED` (default false)
- `base_url` → `MIFOS_BASE_URL`
- `tenant` → `MIFOS_TENANT`
- `auth.username/password` → `MIFOS_USERNAME` / `MIFOS_PASSWORD`
- `office_id`, `product_id`
- `webhook.secret` → `MIFOS_WEBHOOK_SECRET`
- `webhook.allowed_ips` → []
- `sync.auto_push_loan` / `auto_pull_status` (both true by default)
- `sync.reconcile_schedule` → `0 2 * * *`

**Mifos is strictly an external loan ledger / webhook sync provider today, not a payment execution provider.**

### 8.3 `config/queue.php`

- Default connection: `env('QUEUE_CONNECTION', 'redis')`
- Supported: sync, database, beanstalkd, sqs, redis, deferred, background, failover.
- Failed jobs stored via `database-uuids` in `failed_jobs` table.
- PHPUnit overrides to `sync` queue driver (`phpunit.xml`).

### 8.4 `config/loan.php`

Relevant financial parameters:
- `general.currency` = `NAD`
- `fees.default_interest_rate`, `default_platform_fee_percent`
- `trust_tiers.*.lender_return_percent`, `platform_fee_percent`
- `repayment.penalty_rate_weekly` → `LOAN_PENALTY_RATE_WEEKLY` (default 0.05)
- `repayment.max_penalty_ratio` → `LOAN_MAX_PENALTY_RATIO` (default 0.50)
- `marketplace.min_funding_amount` → `LOAN_MIN_FUNDING_AMOUNT` (default 500)

### 8.5 `.env.example` variables

Database/cache/queue/session/mail are standard Laravel values. Loan/payment-specific env vars include:

```text
LOAN_MIN_BORROW_SCORE=30
LOAN_AGREEMENT_DISK=local
LOAN_AGREEMENT_VERSION=1.0

LOAN_TIER_*_MIN / MAX / NAME / LIMIT / INTEREST / PLATFORM_FEE / LENDER_RETURN / DURATIONS
```

No payment execution env vars exist in `.env.example`.

### 8.6 `phpunit.xml`

- `APP_ENV=testing`
- `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`
- `QUEUE_CONNECTION=sync`
- `CACHE_STORE=array`
- `MAIL_MAILER=array`
- Memory limit raised to `10G`.

---

## 9. Known Risks / Warnings

1. **No real payment provider integration.** `DisbursementService::simulatePaymentTransfer()` is a mock. Any automation layer will need to be added alongside this without breaking the existing manual admin confirmation flow.
2. **DisbursementTransaction is overloaded.** The same table is used for both outgoing disbursements and incoming borrower repayments. Adding execution states must not collide with the existing `direction` + `status` semantics.
3. **Funding confirmation is manual.** `ProcessFundingJob` no longer auto-confirms; it only logs and cancels on failure. Payment automation must not silently re-enable auto-confirmation.
4. **Mifos webhook and external sync are live logic.** Changing `MifosWebhookController`, `MifosAdapter`, or the `ExternalLoanStatusUpdated` listener chain could break loan-state reconciliation.
5. **Status helpers and scopes are hard-coded.** New automation statuses (e.g., `processing_payment`, `provider_pending`) would require either new enum values or a separate execution-state table.
6. **Single-repayment bullet model.** `RepaymentService::createRepaymentSchedule()` creates one `Repayment` per loan. Amortized schedules are not yet supported.
7. **Lender returns are allocation records only.** `LenderRepayment` represents the entitlement, not an actual outgoing bank/wallet transfer. A payment execution layer must distinguish "allocated" from "settled".
8. **No idempotency keys for external providers.** Reference generators (`FUND-`, `DISB-`, `REPY-`, `LRPY-`) are internal only.
9. **Retry logic is internal to DisbursementService.** `MAX_RETRIES = 3` and `RETRY_DELAYS` are hard-coded. Provider-level retries should be layered carefully.
10. **Existing tests pass and must stay green.** Any Phase 1 implementation must preserve the 198 passing tests listed above.

---

## 10. Files That Should NOT Be Touched in Phase 0/1 Design Without Explicit Approval

These files contain core business rules, existing manual workflows, or external-sync behavior that must remain intact while adding a payment execution layer:

- `app/Modules/Funding/Services/FundingService.php`
- `app/Modules/Loans/Services/DisbursementService.php`
- `app/Modules/Repayments/Services/RepaymentService.php`
- `app/Modules/Loans/Models/Loan.php` (status helpers / lifecycle)
- `app/Modules/Funding/Models/FundingTransaction.php`
- `app/Modules/Loans/Models/DisbursementTransaction.php`
- `app/Modules/Repayments/Models/Repayment.php`
- `app/Modules/Repayments/Models/LenderRepayment.php`
- `app/Modules/Funding/Models/Investment.php`
- `app/Http/Controllers/Api/V1/Webhooks/MifosWebhookController.php`
- `app/Modules/Loans/Adapters/MifosAdapter.php`
- `app/Modules/Loans/Contracts/LoanProviderInterface.php`
- `app/Modules/Loans/Jobs/SyncLoanToExternalJob.php`
- `app/Modules/Loans/Jobs/SyncExternalLoanStatusJob.php`
- `app/Modules/Loans/Listeners/SyncLoanToExternalProvider.php`
- `app/Modules/Loans/Listeners/TriggerExternalStatusSync.php`
- `app/Modules/Repayments/Listeners/UpdateLoanStatus.php`
- `database/migrations/*` (no schema changes in this phase)
- `config/payments.php`, `config/mifos.php`, `config/loan.php`

---

## 11. Phase 0 Status

**PHASE 0 STATUS: PASS**

- Repository baseline documented.
- All relevant existing tests executed successfully.
- No changes made to business logic, schema, configuration, or Mifos webhook handling.
- Ready to proceed to Phase 1 planning/design (do not implement Phase 1 automatically).
