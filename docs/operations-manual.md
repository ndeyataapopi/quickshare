# QuickShare Private Beta Operations Manual v1.1

**Document Version:** 1.1  
**Status:** Private Beta  
**Classification:** Internal — Operations Team Only  
**Last Updated:** August 2026

---

## Table of Contents

1. [Introduction & System Overview](#1-introduction--system-overview)
2. [User Onboarding Process](#2-user-onboarding-process)
3. [KYC Verification Checklist](#3-kyc-verification-checklist)
4. [Loan Approval Process](#4-loan-approval-process)
5. [Affordability Assessment](#5-affordability-assessment)
6. [Funding Verification Process](#6-funding-verification-process)
7. [Disbursement Process](#7-disbursement-process)
8. [Repayment Verification Process](#8-repayment-verification-process)
9. [Trust Score System](#9-trust-score-system)
10. [Collections Process](#10-collections-process)
11. [Daily Reconciliation Checklist](#11-daily-reconciliation-checklist)
12. [Support Process](#12-support-process)
13. [Incident Handling](#13-incident-handling)
14. [Backup and Recovery](#14-backup-and-recovery)
15. [Go-Live Checklist](#15-go-live-checklist)

---

## 1. Introduction & System Overview

### 1.1 Purpose

This manual defines the standard operating procedures for the QuickShare private beta. It covers the full lifecycle from user registration through loan repayment, plus operational duties such as reconciliation, support, incident response, and backups.

### 1.2 Platform Description

QuickShare is a peer-to-peer lending platform connecting borrowers and lenders. The platform manages:

- User registration and identity verification (KYC)
- Loan application, review, and approval
- Marketplace funding by multiple lenders
- Disbursement of funds to borrowers
- Repayment collection and lender distribution
- Financial reconciliation and audit trails

### 1.3 User Roles

| Role | Description |
|---|---|
| **Client** | Registered user who can act as both borrower and lender |
| **Admin** | Full system access — user management, loan approval, funding confirmation, disbursement, repayments |
| **Compliance Officer** | KYC review, fraud monitoring, regulatory compliance |
| **Finance Officer** | Funding verification, disbursement processing, repayment approval, reconciliation |

### 1.4 Key System Components

- **Auth Module** — Registration, OTP verification, email verification, referral codes
- **KYC Module** — Document submission, encryption, admin review, approval/rejection, selfie-to-profile-picture promotion
- **Loans Module** — Loan application, approval, marketplace, lifecycle management, affordability assessment
- **Funding Module** — Lender funding transactions, investment records, payment proof
- **Disbursement Module** — Outgoing payments to borrowers, borrower confirmation
- **Repayments Module** — Repayment schedules, borrower submissions, admin approval, lender distribution
- **Trust Score Module** — Event-driven score adjustments, tier classification, daily recalculation on login
- **Collections Module** — Overdue repayment escalation, referrer notification, trust score penalties
- **Notifications Module** — Email and SMS notifications for all key events
- **Fraud Detection** — Automated fraud scanning and admin review queue

### 1.5 Loan Lifecycle States

```
draft → pending_review → marketplace → partially_funded → funded
  → awaiting_disbursement → active → completed
                                                    ↘ defaulted
```

Additional states: `cancelled` (from draft or pending_review), `rejected` (from pending_review).

---

## 2. User Onboarding Process

### 2.1 Registration Requirements

All new users must register via the QuickShare platform. Registration is **referral-only** during private beta — a valid 8-character referral code is required.

### 2.2 Registration Steps

1. **Validate Referral Code**
   - User enters referral code on the registration page
   - System validates the code is active and usable
   - Referrer name is displayed for confirmation

2. **Complete Registration Form**
   - **First name** and **last name** (required)
   - **National ID** — 11-digit South African ID number (required, unique)
     - First 6 digits must match date of birth in YYMMDD format
   - **Email address** (required, unique)
   - **Phone number** (required, unique)
   - **Date of birth** (required, must be 18+ years)
   - **Password** (minimum 8 characters, confirmed)
   - **Address** — country, city, street, house number (required); suburb (optional)
   - **Source of income** — profession (employed/self-employed/unemployed), company name, city, country (required if employed or self-employed)

3. **Account Creation**
   - System creates user record with `status = pending`
   - Initial trust score set to **50.00**
   - User is assigned the `client` role
   - Referral tracking record is created
   - User's own referral code is generated

4. **Email Verification**
   - Laravel email verification is triggered automatically
   - User receives a verification link via email
   - User must click the link to verify their email address

5. **Phone Verification (OTP)**
   - An OTP is sent to the user's phone number via SMS
   - User must enter the OTP to verify their phone number

6. **Account Activation**
   - Once both email and phone are verified, the user account can be activated
   - Admin may manually activate accounts during private beta
   - User status changes from `pending` to `active`

### 2.3 Onboarding Checklist for Operations Team

- [ ] Monitor new registrations daily
- [ ] Verify referral codes are being used correctly
- [ ] Confirm email verification rates
- [ ] Confirm phone OTP completion rates
- [ ] Activate verified user accounts (if manual activation is required)
- [ ] Flag any suspicious registrations for fraud review

### 2.4 Admin User Creation

Admin users are created through the admin panel (not self-registration). Required fields:

- First name, last name, national ID, email, phone, date of birth, password
- Status (active/pending/suspended)
- Roles (admin, compliance_officer, finance_officer)
- Permissions (granular, as needed)

**Security rules:**
- An admin cannot remove their own admin role
- The last administrator cannot be demoted
- All role and permission changes are logged in the activity log

---

## 3. KYC Verification Checklist

### 3.1 Overview

All users must complete KYC (Know Your Customer) verification before they can borrow or lend on the platform. KYC submissions are reviewed by compliance officers or admins.

### 3.2 Document Requirements

| Document Type | Required? | Description |
|---|---|---|
| **National ID** | Required | Photo/scan of national identity document |
| **Selfie** | Required | Live selfie photo for identity matching |
| **Payslip** | Optional | Latest payslip (employed users) |
| **Bank Statement** | Optional | Recent bank statement (3 months) |

### 3.3 File Validation Rules

- **Allowed formats:** JPEG, PNG, WebP, PDF
- **Maximum file size:** 10 MB per document
- **Blocked extensions:** `.exe`, `.bat`, `.cmd`, `.sh`, `.php`, `.js`, `.vbs`, `.ps1`, `.scr`
- All documents are **encrypted at rest** using AES encryption
- SHA-256 file hashes are stored for integrity verification
- Documents are scanned asynchronously via queued jobs

### 3.4 KYC Submission Process

1. User navigates to KYC submission page
2. User selects document type, enters document number, issuing country, and expiry date
3. User uploads national ID and selfie (required)
4. User optionally uploads payslip and bank statement
5. System validates file format and size
6. Files are encrypted and stored securely
7. Submission status set to `pending`
8. Compliance officer/admin is notified of new submission

### 3.5 KYC Review Checklist

For each KYC submission, the reviewer must verify:

- [ ] **National ID is valid** — clear, legible, not expired, matches user's registered name
- [ ] **National ID number matches** — the ID number on the document matches the registered national ID
- [ ] **Selfie matches ID photo** — the person in the selfie is the same person on the ID
- [ ] **Selfie is live** — not a photo of a photo, no signs of manipulation
- [ ] **Date of birth is consistent** — ID DOB matches registered DOB
- [ ] **Payslip (if provided)** — employer name matches source of income, amount is reasonable
- [ ] **Bank statement (if provided)** — account holder name matches user, transactions are consistent
- [ ] **No duplicate accounts** — check for existing accounts with same ID number or similar details
- [ ] **Fraud scan** — run fraud detection scan if any red flags are present

### 3.6 KYC Decision Options

| Decision | Status Set | Action |
|---|---|---|
| **Approve** | `approved` | All documents marked approved. User can now borrow/lend. Selfie is decrypted and promoted as the user's profile picture. Trust score increased by +10. |
| **Reject with resubmission** | `resubmission_required` | Specific documents may be rejected with reasons. User can resubmit. |
| **Reject permanently** | `rejected` | No resubmission allowed. User cannot use the platform. |

### 3.7 Resubmission Process

When a submission requires resubmission:
1. User is notified with specific rejection reasons per document
2. User uploads new documents for the rejected types
3. Old documents of the same type are deleted
4. Submission status returns to `pending`
5. Reviewer re-evaluates the new documents

### 3.8 Profile Picture Promotion

When a KYC submission is approved:
1. The listener `UpdateUserVerificationStatus` fires on the `KycApproved` event
2. The encrypted selfie is decrypted from the `uploads` disk
3. The decrypted image is stored on the `public` disk at `profile-pictures/{userId}/selfie.{ext}`
4. The user's `profile_picture` attribute is updated with the relative path
5. The profile picture is displayed across all views (navigation, dashboards, profile edit, admin user list, admin user detail, trust score page)
6. If no profile picture exists, initials are shown as fallback

### 3.9 KYC Review SLA (Private Beta)

- Submissions should be reviewed within **24 hours** during business days
- Submissions received after 17:00 on Friday should be reviewed by Monday 12:00
- Escalate any submission older than 48 hours to the operations lead

---

## 4. Loan Approval Process

### 4.1 Overview

Borrowers submit loan applications which are reviewed and approved by admins. Approved loans are listed on the marketplace for lenders to fund.

### 4.2 Loan Application Steps

1. **Borrower creates loan application**
   - Loan purpose and description
   - Requested amount
   - Loan term (in days)
   - Application is saved as `draft`

2. **Borrower submits application for review**
   - Status changes to `pending_review`
   - Submitted at timestamp is recorded
   - Admin is notified of new application

3. **Borrower signs loan agreement**
   - Loan agreement is generated (PDF)
   - Borrower must consent to the agreement terms
   - IP address and user agent are recorded for audit

### 4.3 Admin Review Checklist

Before approving a loan, the admin must verify:

- [ ] **Borrower KYC is approved** — user must have approved KYC status
- [ ] **Loan purpose is legitimate** — no suspicious or prohibited purposes
- [ ] **Requested amount is reasonable** — within platform limits and borrower profile
- [ ] **Loan term is appropriate** — within allowed term ranges
- [ ] **Risk score is acceptable** — system-calculated risk score is within tolerance
- [ ] **Borrower trust score is adequate** — current trust score supports the loan
- [ ] **No active defaults** — borrower has no defaulted loans
- [ ] **Existing loan exposure** — check borrower's total outstanding debt on platform
- [ ] **Loan agreement is signed** — borrower has consented to the agreement
- [ ] **Fraud scan is clean** — no active fraud flags on the borrower's account

### 4.4 Approval Actions

The admin can:

- **Approve** — Loan moves to `marketplace` status. The system calculates:
  - Interest rate
  - Platform fee
  - Total repayment amount
  - Risk score
  - Repayment date (based on loan term)
  - Admin may adjust the approved amount (lower than requested)
  - Loan agreement PDF is generated automatically

- **Reject** — Loan moves to `rejected` status. A rejection reason must be provided.

### 4.5 Borrower Cancellation

Borrowers can cancel their own loans only when in `draft` or `pending_review` status. Once approved and on the marketplace, cancellation is not possible.

### 4.6 Affordability Check (Advisory)

During the loan review process, the admin should run an affordability assessment before approving the loan:

1. On the loan detail page (`/admin/loans/{id}`), scroll to the **Affordability Assessment** card
2. Click **"Run Assessment"** and enter the borrower's monthly income, expenses, and debt obligations
3. Review the results: affordability score, DTI ratio, risk classification, and the system's recommendation
4. Use this information to inform the approve/reject decision
5. The assessment is stored and linked to the loan for audit purposes

> **Note:** The affordability assessment is advisory and does not block approval. However, admins should exercise caution when approving loans against the assessment's recommendation.

### 4.7 Approval SLA (Private Beta)

- Loan applications should be reviewed within **48 hours** during business days
- Escalate any application older than 72 hours to the operations lead

---

## 5. Affordability Assessment

### 5.1 Overview

The affordability module evaluates a borrower's financial capacity to repay a loan before approval. It uses a weighted scoring model that combines debt-to-income ratio, trust score, repayment history, disposable income, and bank stability data.

The assessment is **advisory** — it does not block loan approval, but it provides the admin with a score, risk classification, and recommendation (approve / reject / manual review) to inform their decision.

### 5.2 Where It Lives

The affordability module is part of the **Loans Module** and is located at:

- **Service:** `app/Modules/Loans/Services/AffordabilityService.php`
- **Controller:** `app/Modules/Loans/Controllers/AffordabilityController.php`
- **DTO:** `app/Modules/Loans/DTOs/AffordabilityInput.php`
- **Model:** `app/Modules/Loans/Models/AffordabilityAssessment.php`
- **Config:** `config/loan.php` under the `affordability` key
- **API Routes:** `app/Modules/Loans/Routes/api.php` under `/affordability/*`
- **Tests:** `tests/Feature/Loans/AffordabilityTest.php`

### 5.3 API Endpoints

| Method | Endpoint | Description |
|---|---|---|
| POST | `/api/loans/affordability/assess` | Run a standalone affordability assessment |
| POST | `/api/loans/affordability/max-loan` | Calculate maximum loan amount for a user |
| GET | `/api/loans/affordability/history` | Get assessment history |
| POST | `/api/loans/admin/{loan}/affordability` | Run affordability assessment for a specific loan (admin API) |
| POST | `/admin/loans/{loan}/affordability` | Run affordability assessment from admin web panel |

### 5.4 Admin Loan Review Integration

When an admin views a loan at `pending_review` status on the admin panel (`/admin/loans/{id}`):

1. The page displays an **Affordability Assessment** card
2. If a previous assessment exists for this loan, its results are shown (score, DTI, risk, decision, disposable income, max loan, repayment reliability, decision reasons)
3. The admin clicks **"Run Assessment"** to open a form with financial input fields
4. The admin enters the borrower's monthly income, expenses, debt repayments, and optional bank/payslip data
5. On submission, the `AffordabilityService::approveOrRejectLoan()` method runs the full assessment
6. The result is stored as an `AffordabilityAssessment` record linked to the loan
7. The page reloads showing the assessment results
8. The admin uses this information to inform their approve/reject decision — **it does not block approval**

The API endpoint `GET /api/v1/loans/admin/{loan}` also returns the latest affordability assessment for the loan if one exists.

### 5.5 Input Data

The assessment accepts the following financial inputs:

| Field | Required | Description |
|---|---|---|
| `monthly_income` | Yes | User's gross monthly income |
| `monthly_expenses` | No | User's declared monthly living expenses |
| `existing_debt` | No | Total outstanding debt |
| `monthly_debt_repayments` | No | Monthly debt repayment obligations |
| `payslip_gross` | No | Gross salary from payslip |
| `payslip_net` | No | Net salary from payslip |
| `bank_avg_balance` | No | Average bank account balance |
| `bank_avg_income` | No | Average monthly income per bank statements |
| `bank_avg_expenses` | No | Average monthly expenses per bank statements |

### 5.6 Scoring Model

The affordability score (0–100) is calculated using weighted components:

| Component | Weight | Description |
|---|---|---|
| **Debt-to-Income Ratio** | 30% | Lower DTI = higher score. Excellent: ≤20%, Good: ≤35%, Fair: ≤50% |
| **Trust Score** | 25% | User's current platform trust score (0–100) |
| **Repayment History** | 20% | Reliability based on completed vs defaulted loans |
| **Disposable Income** | 15% | Income minus expenses minus debt repayments, as % of gross income |
| **Bank Stability** | 10% | Balance coverage and income consistency from bank data |

### 5.7 Decision Engine

The system produces one of three decisions:

| Decision | Condition | Action |
|---|---|---|
| **approve** | Score ≥ 75 AND DTI ≤ 35% | Auto-approve recommended |
| **reject** | Score ≤ 30 OR DTI > 50% OR trust score below minimum OR ≥2 defaults | Auto-reject |
| **manual_review** | Score between 30–75 | Requires admin manual review |

### 5.8 Risk Classification

| Classification | Criteria |
|---|---|
| `very_low` | Avg signal ≥ 80 AND DTI ≤ 20% |
| `low` | Avg signal ≥ 65 AND DTI ≤ 35% |
| `moderate` | Avg signal ≥ 50 AND DTI ≤ 50% |
| `high` | Avg signal ≥ 35 |
| `very_high` | Avg signal < 35 |

### 5.9 Hard Reject Rules

Regardless of affordability score, the system will reject if **any** of these conditions are true:

- DTI ratio exceeds 50%
- Trust score is below the minimum borrowing threshold (configurable via `loan.minimum_borrow_score`)
- Borrower has 2 or more defaulted loans
- Requested loan amount exceeds the borrower's trust-tier maximum loan limit

### 5.10 Trust Tier Loan Limits

| Tier | Score Range | Max Loan Amount |
|---|---|---|
| Bronze | 0 – 49.99 | N$500 |
| Silver | 50 – 69.99 | N$1,000 |
| Gold | 70 – 84.99 | N$1,500 |
| Platinum | 85 – 100 | N$2,500 |

---

## 6. Funding Verification Process

### 6.1 Overview

Once a loan is approved and on the marketplace, lenders can fund it. Each lender submits a funding transaction with payment proof. Finance officers must verify each payment before it is applied to the loan.

### 6.2 Funding Transaction States

```
pending → confirmed (or) rejected
```

### 6.3 Lender Funding Steps

1. **Lender selects a loan from the marketplace**
   - Views loan details, interest rate, and remaining funding needed
   - Chooses an amount to fund

2. **Lender makes payment externally**
   - Transfers funds via EFT, mobile wallet, or other approved payment method
   - System generates a unique payment reference for tracking

3. **Lender submits payment proof**
   - Uploads payment confirmation/receipt
   - Enters payment method, reference number, and transaction number
   - Transaction status remains `pending`

### 6.4 Funding Verification Checklist

The finance officer must verify:

- [ ] **Payment proof is legible** — the uploaded document is clear and readable
- [ ] **Payment amount matches** — the amount in the proof matches the funding transaction amount
- [ ] **Payment reference matches** — the reference number on the proof matches the system-generated reference
- [ ] **Payment date is recent** — payment was made within the last 5 business days
- [ ] **Payment method is valid** — the method used is an approved payment channel
- [ ] **Lender account is in good standing** — no fraud flags or suspensions
- [ ] **Loan is still on marketplace** — loan has not been cancelled or expired
- [ ] **Funding does not exceed remaining amount** — the transaction plus existing funding does not exceed the approved loan amount

### 6.5 Confirmation Actions

When the finance officer confirms funding:

1. **Loan funded amount is updated** — transaction amount added to loan's `funded_amount`
2. **Loan status is updated:**
   - If fully funded → status becomes `funded`
   - If partially funded → status becomes `partially_funded`
3. **Investment record is created** — links lender to the loan with amount, interest rate, and expected return
4. **Notifications are sent:**
   - Lender is notified that their payment was approved
   - If loan is fully funded, borrower is notified
   - If loan is fully funded, all participating lenders are notified
5. **Marketplace cache is cleared** — loan is removed from marketplace listings

### 6.6 Rejection Actions

If the funding proof is invalid:
1. Transaction status set to `rejected`
2. Lender is notified with rejection reason
3. Lender can submit a new funding transaction with corrected proof

### 6.7 Funding Verification SLA (Private Beta)

- Funding proofs should be verified within **12 hours** during business days
- Escalate any proof older than 24 hours to the finance lead

---

## 7. Disbursement Process

### 7.1 Overview

Once a loan is fully funded, the platform disburses the net amount to the borrower (gross amount minus platform fee). The disbursement process involves admin initiation, payment execution, and borrower confirmation.

### 7.2 Disbursement Transaction States

```
awaiting_disbursement → processing → pending_borrower_confirmation
  → disbursed (borrower confirms)
  → rejected_by_borrower (borrower rejects)
  → failed (payment failure, can retry up to 3 times)
```

### 7.3 Disbursement Steps

#### Step 1: Initiate Disbursement (System/Admin)

- Triggered when loan status is `funded`
- System validates the loan is disbursable
- Calculates amounts:
  - **Gross amount** = total funded amount
  - **Platform fee** = calculated at loan approval
  - **Net amount** = gross − platform fee
- Ledger entries are created (debit lender contributions, credit platform fee, credit borrower)
- Disbursement transaction created with status `awaiting_disbursement`
- Loan status updated to `awaiting_disbursement`

#### Step 2: Process Disbursement (Admin/Finance Officer)

- Admin executes the actual bank transfer or payment to the borrower
- Admin records:
  - Payment method used
  - External reference number (from bank/payment provider)
  - Payment proof (receipt/confirmation)
- Transaction status changes to `pending_borrower_confirmation`
- Borrower is notified to confirm receipt of funds

#### Step 3: Borrower Confirms Receipt

- Borrower logs in and confirms they received the funds
- Transaction status changes to `disbursed`
- Loan status changes to `active`
- `disbursed_at` timestamp is recorded
- **Repayment schedule is created** (bullet repayment — single payment due on repayment date)
- `LoanActivated` event is dispatched
- Borrower is notified that the loan is now active

#### Step 3 Alternative: Borrower Rejects Receipt

- Borrower indicates they did not receive the funds
- Transaction status changes to `rejected_by_borrower`
- Loan status returns to `awaiting_disbursement`
- Admins are notified immediately
- Admin must investigate and re-initiate disbursement if needed

### 7.4 Disbursement Failure Handling

- If the payment fails, transaction status becomes `failed`
- **Retry policy:** Up to 3 retries with escalating delays:
  - 1st retry: after 5 minutes
  - 2nd retry: after 15 minutes
  - 3rd retry: after 1 hour
- After 3 failed attempts, manual intervention is required
- Failure reason is recorded for each attempt

### 7.5 Disbursement Checklist for Finance Officers

- [ ] **Loan is fully funded** — verify `funded_amount` equals `approved_amount`
- [ ] **Borrower bank details are correct** — verify recipient account before executing transfer
- [ ] **Net amount is positive** — gross minus platform fee must be > 0
- [ ] **Payment proof is uploaded** — receipt/confirmation from bank or payment provider
- [ ] **External reference is recorded** — bank reference number for traceability
- [ ] **Borrower has been notified** — confirmation request sent to borrower
- [ ] **Monitor for borrower confirmation** — follow up if not confirmed within 48 hours

### 7.6 Disbursement SLA (Private Beta)

- Disbursement should be initiated within **24 hours** of loan being fully funded
- Borrower should confirm receipt within **48 hours** of notification
- Escalate unconfirmed disbursements after 72 hours

---

## 8. Repayment Verification Process

### 8.1 Overview

When a loan is active, the borrower must repay according to the repayment schedule. The borrower submits a repayment with payment proof, and the admin/finance officer verifies and approves it. Upon approval, funds are distributed to all participating lenders.

### 8.2 Repayment States

```
pending → pending_approval → paid
                            ↘ (rejected back to pending/partial/overdue)
```

Additional states: `partial`, `overdue`

### 8.3 Repayment Schedule

- Currently, QuickShare uses **bullet repayment** — a single repayment covering principal + interest + platform fee
- Due date is calculated from the loan term (loan activation date + loan term days)
- A penalty amount may be applied for overdue payments

### 8.4 Borrower Repayment Submission

1. **Borrower selects repayment(s) to pay**
   - Can pay one or multiple installments at once
   - System groups repayments by loan

2. **Borrower makes payment externally**
   - Transfers funds via EFT, mobile wallet, or cash deposit
   - Payment methods: `eft`, `mobile_wallet`, `cash_deposit`

3. **Borrower submits repayment request**
   - Uploads payment proof
   - Enters payment method and optional external reference
   - Each selected repayment status changes to `pending_approval`
   - An incoming disbursement transaction is created (Borrower → QuickShare)
   - Transaction status: `awaiting_approval`

### 8.5 Repayment Verification Checklist

The finance officer must verify:

- [ ] **Payment proof is legible** — document is clear and readable
- [ ] **Payment amount matches** — amount in proof matches the repayment amount + any penalty
- [ ] **Payment reference is present** — transaction can be traced to a bank record
- [ ] **Payment date is recent** — payment was made within the last 5 business days
- [ ] **Payment method is valid** — method used is an approved channel
- [ ] **Borrower account is active** — no suspensions or fraud flags
- [ ] **Loan is active** — loan status is `active` or `disbursed`
- [ ] **Repayment is in `pending_approval` status** — has not already been processed

### 8.6 Repayment Approval Actions

When the finance officer approves a repayment:

1. **Repayment status** changes to `paid` with `paid_date` recorded
2. **Incoming disbursement** status changes to `confirmed`
3. **Lender distribution** — `LenderRepayment` records are created for each participating lender
4. **Investment earnings updated** — each lender's `Investment.actual_return` is increased
5. **LenderRepayment records** are marked as `processed`
6. **Borrower is notified** that repayment was received and confirmed
7. **Loan completion check:**
   - If all repayments for the loan are `paid`, loan status changes to `completed`
   - `completed_at` timestamp is recorded
   - Borrower and all lenders are notified

### 8.7 Repayment Rejection

If the repayment proof is invalid:
1. Repayment status returns to its previous state (`pending`, `partial`, or `overdue`)
2. Incoming disbursement is rejected
3. Borrower is notified with rejection reason
4. Borrower can resubmit with corrected proof

### 8.8 Overdue Repayment Handling

- Repayments past their due date automatically become `overdue`
- Penalty amounts may be applied
- Collections process should be initiated (see Collections module)
- Admin should contact borrower directly for overdue payments
- Escalate to legal collection process if repayment is more than 30 days overdue

### 8.9 Repayment Verification SLA (Private Beta)

- Repayment submissions should be verified within **12 hours** during business days
- Escalate any submission older than 24 hours to the finance lead

---

## 9. Trust Score System

### 9.1 Overview

The trust score is a 0–100 numeric value that represents a user's reliability and standing on the platform. It determines borrowing eligibility, loan tier, and maximum loan amount. The score is adjusted in real-time when events occur and fully recalculated from ground-truth data on every user login.

### 9.2 Where It Lives

- **Service:** `app/Modules/TrustScore/Services/TrustScoreService.php`
- **Controller (web):** `app/Http/Controllers/TrustScoreController.php`
- **Controller (API):** `app/Modules/TrustScore/Controllers/TrustScoreController.php`
- **Model:** `app/Modules/TrustScore/Models/TrustScoreHistory.php`
- **Events:** `app/Modules/TrustScore/Events/TrustScoreCalculated.php`
- **Listeners:** `app/Modules/TrustScore/Listeners/`
- **Provider:** `app/Modules/TrustScore/TrustScoreServiceProvider.php`
- **Console Command:** `app/Console/Commands/RecalculateTrustScores.php`
- **View:** `resources/views/client/trust-score.blade.php`

### 9.3 Score Adjustment Weights

| Event | Points | Trigger |
|---|---|---|
| On-time repayment | +3.00 | `RepaymentMade` event |
| Late repayment | -5.00 | `RepaymentOverdue` event (scaled by days overdue) |
| Loan default | -15.00 | `LoanDefaulted` event / `CollectionService` |
| Loan fully repaid | +5.00 | `LoanFullyRepaid` event |
| KYC approved | +10.00 | `KycApproved` event |
| Referral completed | +2.00 | `ReferralService::rewardReferrer` |
| Referral defaulted | -3.00 | `CollectionService` when referred user defaults |
| Account age ≥ 1 year | +1.00 | Recalculation only |
| No defaults for 1 year | +15.00 | Recalculation only (requires ≥1 completed loan) |

### 9.4 Trust Tiers

| Tier | Score Range | Max Loan | Color |
|---|---|---|---|
| Bronze | 0 – 49.99 | N$500 | Warning (yellow) |
| Silver | 50 – 69.99 | N$1,000 | Secondary (gray) |
| Gold | 70 – 84.99 | N$1,500 | Info (blue) |
| Platinum | 85 – 100 | N$2,500 | Primary (dark blue) |

### 9.5 How Scores Stay Up to Date

The trust score is kept current through two mechanisms:

**1. Real-time event-driven adjustments** — When platform events fire (repayment made, loan defaults, KYC approved, etc.), queued listeners adjust the score immediately and log a `TrustScoreHistory` entry.

**2. Full recalculation on login** — When a user logs in, the `RecalculateScoreOnLogin` listener (queued) runs `TrustScoreService::recalculateForUser()`, which recomputes the score from scratch using the user's actual account data:
- KYC approval status
- All repayment records (on-time, overdue, defaulted)
- All loan records (completed, defaulted)
- All referral records (completed, defaulted)
- Account age
- Default-free history for past year

This ensures the score is always accurate even if individual events were missed or failed.

### 9.6 Manual Recalculation

Admins can manually trigger a full recalculation:

```bash
# Recalculate for all users
php artisan trust-score:recalculate

# Recalculate for a specific user
php artisan trust-score:recalculate --user=6
```

### 9.7 Trust Score History

Every score change is recorded in `trust_score_histories` with:
- Previous score
- New score
- Points changed
- Reason
- Event type
- Metadata (e.g., loan ID, repayment count, days overdue)

Users can view their score history on the Trust Score page, including a line chart and a table of recent changes.

### 9.8 Minimum Borrowing Threshold

Users must have a trust score of at least **50.00** (Silver tier) and an active account status to be eligible to borrow. This is configurable via `loan.minimum_borrow_score`.

---

## 10. Collections Process

### 10.1 Overview

The collections module handles overdue repayments through an escalating escalation process. It applies trust score penalties and notifies referrers when a referred borrower's repayments become overdue.

### 10.2 Where It Lives

- **Service:** `app/Modules/Collections/Services/CollectionService.php`
- **Controller:** `app/Modules/Collections/Controllers/CollectionsController.php`
- **Model:** `app/Modules/Collections/Models/CollectionCase.php`, `CollectionLog.php`
- **Jobs:** `app/Modules/Collections/Jobs/` (ProcessDailyCollectionsJob, NotifyReferrerJob, SendNotificationJob)
- **Routes:** `app/Modules/Collections/Routes/api.php`

### 10.3 Escalation Levels

| Level | Trigger | Trust Score Penalty |
|---|---|---|
| `escalation_level_1` | Repayment overdue | -3.00 |
| `escalation_level_2` | Extended overdue | -5.00 |
| `escalation_level_3` | Severe overdue | -8.00 |
| Loan default | Unrecoverable | -15.00 |

### 10.4 Referrer Notification

When a referred borrower's repayment becomes overdue, the referrer is notified via a queued job (`NotifyReferrerJob`). If the referred user defaults on a loan, the referrer's trust score is penalized by -3.00.

---

## 11. Daily Reconciliation Checklist

### 11.1 Purpose

Daily reconciliation ensures that all financial transactions in the system match actual bank/payment provider records. This is critical for financial integrity and audit compliance.

### 11.2 Reconciliation Scope

The following transaction types must be reconciled daily:

1. **Funding transactions** (Lender → QuickShare)
2. **Disbursement transactions** (QuickShare → Borrower, outgoing)
3. **Repayment transactions** (Borrower → QuickShare, incoming)
4. **Lender repayment distributions** (QuickShare → Lenders)

### 11.3 Daily Reconciliation Procedure

Perform the following checks **every business day** before 10:00:

#### Funding Reconciliation

- [ ] Pull bank statement for the previous day
- [ ] Match each `confirmed` funding transaction to a bank deposit
- [ ] Verify amounts match exactly
- [ ] Flag any unmatched deposits (potential funding without system record)
- [ ] Flag any confirmed transactions without matching bank deposits (potential fraud)
- [ ] Record reconciliation timestamp and reviewer in the system

#### Disbursement Reconciliation

- [ ] List all disbursement transactions with status `disbursed` from the previous day
- [ ] Match each to a bank withdrawal or payment provider debit
- [ ] Verify net amounts match
- [ ] Confirm `borrower_confirmed_at` exists for all disbursed transactions
- [ ] Flag any disbursements where borrower has not confirmed within 48 hours
- [ ] Check for any `failed` disbursements and verify retry status

#### Repayment Reconciliation

- [ ] List all repayments with status `paid` from the previous day
- [ ] Match each to a bank deposit or payment provider credit
- [ ] Verify amounts match (repayment amount + penalty)
- [ ] Confirm incoming disbursement transactions are `confirmed`
- [ ] Verify lender distributions (LenderRepayment records) are `processed`
- [ ] Flag any unmatched deposits

#### Investment Earnings Reconciliation

- [ ] For each repaid loan, verify `Investment.actual_return` matches expected distribution
- [ ] Cross-check total lender distributions against total repayment received
- [ ] Verify platform fee collection matches expected amounts

### 11.4 Reconciliation Discrepancy Handling

| Severity | Description | Action |
|---|---|---|
| **Low** | Timing difference (< R100) | Document and monitor; resolve within 3 days |
| **Medium** | Amount mismatch (R100–R1,000) | Investigate same day; notify finance lead |
| **High** | Missing transaction or large mismatch (> R1,000) | Escalate immediately to operations lead; freeze affected transactions |
| **Critical** | Suspected fraud or missing funds | Escalate to CEO and compliance officer immediately; initiate incident response |

### 11.5 Reconciliation Records

- All reconciliation results must be recorded with:
  - Date reconciled
  - Reviewer name
  - Transactions checked
  - Discrepancies found
  - Resolution actions taken
- Reconciliation data is stored in the `reconciliation_data` field on each `DisbursementTransaction`
- The `reconciled_at` and `reconciled_by` fields track when each transaction was reconciled

---

## 12. Support Process

### 12.1 Support Channels

During private beta, support is provided through:

- **In-app support** — Users can submit support requests through the platform
- **Email** — support@quickshare.co.za (or designated support email)
- **Phone** — During business hours (09:00–17:00, Monday–Friday)

### 12.2 Support Ticket Categories

| Category | Examples | Priority |
|---|---|---|
| **Account/Access** | Login issues, OTP not received, account locked | High |
| **KYC Issues** | Upload failing, submission rejected, resubmission help | Medium |
| **Loan Questions** | Application status, approval questions, terms | Medium |
| **Funding Issues** | Payment proof rejected, transaction not confirmed | High |
| **Disbursement** | Funds not received, confirmation issues | High |
| **Repayment** | Payment not reflecting, schedule questions | High |
| **Technical Bug** | Platform errors, broken pages, incorrect data | High |
| **General** | FAQs, how-to questions, feedback | Low |

### 12.3 Support Workflow

1. **Receive ticket** — via any support channel
2. **Categorize and prioritize** — assign category and priority
3. **Acknowledge** — respond to user within **2 hours** during business hours
4. **Investigate** — check user's account, transaction history, system logs
5. **Resolve or escalate** — resolve directly or escalate to appropriate team member
6. **Communicate resolution** — inform user of outcome and any actions taken
7. **Close ticket** — record resolution and close

### 12.4 Escalation Matrix

| Issue Type | Escalate To | When |
|---|---|---|
| Account suspension | Admin | Immediate |
| KYC dispute | Compliance Officer | Same day |
| Financial discrepancy | Finance Officer | Same day |
| Technical platform error | Development Team | Immediate |
| Fraud suspicion | Compliance Officer + Admin | Immediate |
| Legal threat or regulator contact | CEO + Legal Counsel | Immediate |

### 12.5 Common User Issues & Resolutions

- **OTP not received:** Check phone number is correct; resend OTP; verify SMS gateway is operational
- **Email verification link expired:** Generate new verification link from admin panel
- **KYC upload failing:** Check file format (JPEG/PNG/WebP/PDF), size (< 10MB), and network connection
- **Funding not confirmed:** Check if payment proof was submitted; verify with bank; confirm transaction
- **Disbursement not received:** Verify bank details; check payment proof; contact payment provider; re-initiate if needed
- **Repayment not reflecting:** Check if proof was submitted; verify with bank; approve if valid

### 12.6 Support Metrics to Track

- Average response time
- Average resolution time
- Ticket volume by category
- User satisfaction score
- Repeat issue rate

---

## 13. Incident Handling

### 13.1 Incident Classification

| Severity | Description | Response Time | Examples |
|---|---|---|---|
| **SEV-1 (Critical)** | Platform down or data breach | Immediate, 24/7 | Site unavailable, database failure, security breach |
| **SEV-2 (High)** | Major feature broken, financial impact | 1 hour | Payments not processing, KYC module down, notifications failing |
| **SEV-3 (Medium)** | Partial functionality impaired | 4 hours | Specific page errors, non-critical feature broken |
| **SEV-4 (Low)** | Minor issues, cosmetic | Next business day | UI glitches, typo, non-blocking bug |

### 13.2 Incident Response Process

#### Step 1: Detect & Report

- Monitor system alerts, error logs, user reports
- Anyone can report an incident via the incident channel
- Record: what happened, when, who is affected, severity

#### Step 2: Assess & Classify

- On-call responder assesses severity
- Classify incident (SEV-1 through SEV-4)
- Assign incident commander (for SEV-1 and SEV-2)

#### Step 3: Contain

- Take immediate action to prevent further damage:
  - SEV-1: May require taking the platform offline
  - Security breach: Revoke affected tokens, lock affected accounts
  - Payment issue: Pause affected transaction processing

#### Step 4: Communicate

- **Internal:** Notify operations team, development team, and leadership
- **Users:** Post status update on platform if user-facing
- **Stakeholders:** Notify investors/partners if financial impact

#### Step 5: Resolve

- Development team investigates root cause
- Apply fix or workaround
- Verify resolution in production
- Monitor for recurrence

#### Step 6: Post-Incident Review

- Conduct within **48 hours** of resolution for SEV-1 and SEV-2
- Document:
  - Timeline of events
  - Root cause analysis
  - Actions taken
  - Impact (users affected, financial impact, downtime)
  - Preventive measures
- Share learnings with the team

### 13.3 Common Incident Scenarios

#### Payment Processing Failure

1. Check payment provider status
2. Verify queue worker is running (`systemctl status quickshare-worker` or `supervisorctl status`)
3. Check for failed disbursement transactions (`status = failed`)
4. Retry failed transactions if appropriate
5. Contact payment provider if systemic issue

#### Database Performance Issues

1. Check slow query log
2. Identify resource-intensive queries
3. Check available disk space and memory
4. Consider temporarily disabling non-critical background jobs
5. Contact hosting provider if infrastructure issue

#### Security Incident (Suspected Breach)

1. Immediately isolate affected systems
2. Revoke all active sessions and API tokens
3. Force password reset for affected users
4. Audit access logs for unauthorized activity
5. Notify compliance officer and CEO
6. Document everything for regulatory reporting
7. Engage security consultant if needed

#### Queue Worker Failure

1. Check if queue worker process is running
2. Review worker logs for errors
3. Restart queue worker: `php artisan queue:restart` or `supervisorctl restart quickshare-worker`
4. Check for stuck jobs: `php artisan queue:failed`
5. Retry failed jobs: `php artisan queue:retry all`
6. Monitor after restart

### 13.4 Incident Communication Templates

#### User-Facing (SEV-1/SEV-2)

> **Service Notice:** We are currently experiencing technical issues with [description]. Our team is actively working to resolve this. We apologize for the inconvenience and will update you once service is restored. Estimated resolution time: [time or "as soon as possible"].

#### Resolution Notice

> **Service Update:** The issue with [description] has been resolved. All services are now operating normally. Thank you for your patience.

---

## 14. Backup and Recovery

### 14.1 Backup Strategy

#### Database Backups

- **Frequency:** Daily automated backups (minimum)
- **Retention:** 30 days of daily backups, 12 months of monthly backups
- **Method:** Automated database dump via hosting provider or scheduled task
- **Verification:** Weekly backup restoration test on staging environment

#### File/System Backups

- **KYC documents:** Backed up with encryption (files are already encrypted at rest)
- **Loan agreements:** Backed up with daily file system backup
- **Funding payment proofs:** Backed up with daily file system backup
- **Application code:** Version controlled via Git; ensure off-site Git mirror

#### Configuration Backups

- `.env` files (production): Backed up securely, stored separately from application
- Configuration files: Version controlled
- Deployment scripts: Version controlled

### 14.2 Backup Verification

- **Weekly:** Restore database backup to staging and verify integrity
- **Monthly:** Perform full disaster recovery drill (restore database + files + config)
- **Quarterly:** Review backup retention policy and storage capacity

### 14.3 Recovery Procedures

#### Database Recovery

1. Identify the backup to restore from (date and time)
2. Notify operations team and stakeholders of planned downtime
3. Put platform in maintenance mode
4. Stop queue workers
5. Restore database from backup:
   ```
   php artisan maintenance:down
   # Restore database from backup file
   php artisan migrate --force
   php artisan maintenance:up
   ```
6. Restart queue workers
7. Verify data integrity — check recent transactions, user counts, loan statuses
8. Monitor for any consistency issues

#### File Recovery

1. Identify the files to restore (KYC documents, agreements, proofs)
2. Restore from backup to appropriate storage disk
3. Verify file integrity using stored SHA-256 hashes
4. Verify encryption status of KYC documents

#### Full System Recovery (Disaster Scenario)

1. Provision new server infrastructure
2. Restore application code from Git repository
3. Restore `.env` configuration
4. Restore database from most recent backup
5. Restore file storage from backup
6. Run database migrations: `php artisan migrate --force`
7. Clear and rebuild caches: `php artisan optimize:clear`
8. Restart queue workers and web server
9. Run smoke tests on all critical paths
10. Switch DNS/load balancer to new infrastructure
11. Notify users of service restoration

### 14.4 Recovery Time Objectives (RTO)

| Scenario | RTO | RPO |
|---|---|---|
| Database failure | 4 hours | 24 hours |
| File storage failure | 8 hours | 24 hours |
| Full system failure | 24 hours | 24 hours |
| Security breach | 48 hours | 0 hours (point-in-time) |

*RPO = Recovery Point Objective (maximum acceptable data loss)*  
*RTO = Recovery Time Objective (maximum acceptable downtime)*

### 14.5 Backup Checklist (Daily)

- [ ] Verify automated database backup completed successfully
- [ ] Verify file system backup completed successfully
- [ ] Check backup storage capacity (alert if < 20% free)
- [ ] Review any backup failure notifications
- [ ] Log backup completion in operations log

---

## 15. Go-Live Checklist

### 15.1 Pre-Launch (1 Week Before)

#### Infrastructure

- [ ] Production server provisioned and configured
- [ ] SSL certificate installed and valid
- [ ] DNS records configured (A record, MX, SPF, DKIM)
- [ ] Firewall rules configured (only ports 80/443 open)
- [ ] Queue worker configured and running (Supervisor or systemd)
- [ ] Cron/scheduled tasks configured (`php artisan schedule:run`)
- [ ] Database backups automated and verified
- [ ] File storage configured with adequate capacity
- [ ] Monitoring and alerting configured (uptime, disk space, error rates)
- [ ] Log rotation configured

#### Application

- [ ] Production `.env` file configured with correct values
- [ ] `APP_ENV=production` and `APP_DEBUG=false`
- [ ] Database credentials are correct and use least-privilege
- [ ] Encryption keys generated and stored securely
- [ ] KYC file encryption key backed up securely
- [ ] Payment provider API keys configured
- [ ] SMS gateway credentials configured (for OTP)
- [ ] Email service configured (for verification and notifications)
- [ ] All migrations run successfully: `php artisan migrate --force`
- [ ] Application cache cleared and warmed: `php artisan optimize`
- [ ] Frontend assets built and deployed: `npm run build`

#### Security

- [ ] Default admin passwords changed
- [ ] Admin accounts created with strong passwords
- [ ] 2FA enabled for all admin accounts (if available)
- [ ] Rate limiting configured on auth endpoints
- [ ] CORS configured correctly
- [ ] Sanitization and input validation verified
- [ ] Security scan completed (no critical vulnerabilities)
- [ ] KYC document encryption verified
- [ ] Sensitive data (API keys, passwords) not in version control

#### Data & Seed

- [ ] Roles and permissions seeded (`RoleSeeder`, `ImpersonatePermissionSeeder`)
- [ ] Admin user seeded (`AdminSeeder`)
- [ ] Referral codes generated for initial beta users
- [ ] Test data removed from production database
- [ ] All test/sandbox API keys replaced with production keys

### 15.2 Launch Day

#### Final Verification

- [ ] User registration flow works end-to-end
- [ ] Email verification works
- [ ] Phone OTP verification works
- [ ] KYC submission and admin review works
- [ ] Loan application and approval works
- [ ] Marketplace displays approved loans
- [ ] Funding submission and confirmation works
- [ ] Disbursement initiation, processing, and borrower confirmation works
- [ ] Repayment schedule creation works
- [ ] Repayment submission and admin approval works
- [ ] Lender distribution works
- [ ] Notifications (email and SMS) are sending correctly
- [ ] Activity logs and audit trails are recording
- [ ] Queue workers processing jobs without errors

#### Go-Live Actions

- [ ] Put platform in live mode (remove maintenance mode)
- [ ] Send launch notification to beta users
- [ ] Distribute referral codes to initial beta cohort
- [ ] Monitor error logs in real-time for first 4 hours
- [ ] Have development team on standby for first 24 hours
- [ ] Document any issues encountered and their resolutions

### 15.3 Post-Launch (First Week)

- [ ] Daily reconciliation performed every business day
- [ ] Monitor user registration and onboarding metrics
- [ ] Track KYC submission and approval rates
- [ ] Monitor loan application volume and approval rates
- [ ] Verify funding transactions are being confirmed correctly
- [ ] Verify disbursements are processing correctly
- [ ] Monitor repayment submissions and approvals
- [ ] Review support ticket volume and categories
- [ ] Check system performance metrics (response times, error rates)
- [ ] Conduct daily standup to review issues and priorities
- [ ] Document any process changes needed based on real-world experience

### 15.4 Sign-Off

| Role | Name | Signature | Date |
|---|---|---|---|
| Operations Lead | | | |
| Technical Lead | | | |
| Compliance Officer | | | |
| CEO | | | |

---

## Appendix A: Key System Commands

```bash
# Queue worker management
supervisorctl start quickshare-worker
supervisorctl stop quickshare-worker
supervisorctl restart quickshare-worker
supervisorctl status quickshare-worker

# Failed queue jobs
php artisan queue:failed
php artisan queue:retry all
php artisan queue:flush

# Maintenance mode
php artisan down
php artisan up

# Cache management
php artisan optimize:clear
php artisan optimize

# Database
php artisan migrate --force
php artisan migrate:status
php artisan db:seed --class=AdminSeeder

# Storage symlink (profile pictures)
php artisan storage:link

# Trust score recalculation
php artisan trust-score:recalculate
php artisan trust-score:recalculate --user=6

# Logs
tail -f storage/logs/laravel.log
```

## Appendix B: Contact Directory

| Role | Name | Contact | Availability |
|---|---|---|---|
| Operations Lead | [To be filled] | [Phone/Email] | Business hours + on-call |
| Technical Lead | [To be filled] | [Phone/Email] | Business hours + on-call |
| Finance Officer | [To be filled] | [Phone/Email] | Business hours |
| Compliance Officer | [To be filled] | [Phone/Email] | Business hours |
| Hosting Provider Support | [To be filled] | [Phone/Email] | 24/7 |
| Payment Provider Support | [To be filled] | [Phone/Email] | Business hours |
| SMS Gateway Support | [To be filled] | [Phone/Email] | Business hours |

---

*This document is confidential and intended solely for the QuickShare operations team. Distribution outside the organization is prohibited.*

*© 2026 QuickShare. All rights reserved.*
