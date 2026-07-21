<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Modules\Loans\Models\Loan;
use App\Modules\KYC\Models\KycSubmission;
use App\Modules\Admin\Models\FraudFlag;
use App\Models\User;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'total_users'   => User::count(),
            'pending_loans' => Loan::where('status', 'pending_review')->count(),
            'active_loans'  => Loan::whereIn('status', ['active', 'disbursed'])->count(),
            'total_funded'  => Loan::sum('funded_amount'),
            'pending_kyc'   => KycSubmission::where('status', 'pending')->count(),
            'fraud_alerts'  => FraudFlag::where('status', 'open')->count(),
        ];
        
        $recentLoans = Loan::with('borrower:id,first_name,last_name')->latest()->take(10)->get();
        $recentActivity = ActivityLog::with('user:id,first_name,last_name')
            ->latest()
            ->take(10)
            ->get();
        
        return view('admin.dashboard', compact('stats', 'recentLoans', 'recentActivity'));
    }
}
