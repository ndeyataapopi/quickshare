<?php

namespace App\Modules\Loans\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffordabilityAssessment extends Model
{
    protected $fillable = [
        'user_id',
        'loan_id',
        'monthly_income',
        'monthly_expenses',
        'existing_debt',
        'monthly_debt_repayments',
        'payslip_gross',
        'payslip_net',
        'bank_avg_balance',
        'bank_avg_income',
        'bank_avg_expenses',
        'debt_to_income_ratio',
        'disposable_income',
        'affordability_score',
        'max_loan_amount',
        'max_monthly_repayment',
        'trust_score',
        'trust_tier',
        'total_loans',
        'completed_loans',
        'defaulted_loans',
        'late_repayments',
        'repayment_reliability',
        'risk_classification',
        'decision',
        'decision_reasons',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'monthly_income' => 'decimal:2',
            'monthly_expenses' => 'decimal:2',
            'existing_debt' => 'decimal:2',
            'monthly_debt_repayments' => 'decimal:2',
            'payslip_gross' => 'decimal:2',
            'payslip_net' => 'decimal:2',
            'bank_avg_balance' => 'decimal:2',
            'bank_avg_income' => 'decimal:2',
            'bank_avg_expenses' => 'decimal:2',
            'debt_to_income_ratio' => 'decimal:2',
            'disposable_income' => 'decimal:2',
            'affordability_score' => 'decimal:2',
            'max_loan_amount' => 'decimal:2',
            'max_monthly_repayment' => 'decimal:2',
            'trust_score' => 'decimal:2',
            'repayment_reliability' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    // ─── Relationships ───────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    // ─── Helpers ─────────────────────────────────────────────────────

    public function isApproved(): bool
    {
        return $this->decision === 'approve';
    }

    public function isRejected(): bool
    {
        return $this->decision === 'reject';
    }

    public function requiresManualReview(): bool
    {
        return $this->decision === 'manual_review';
    }

    // ─── Scopes ──────────────────────────────────────────────────────

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeLatestForUser($query, int $userId)
    {
        return $query->where('user_id', $userId)->latest()->first();
    }
}
