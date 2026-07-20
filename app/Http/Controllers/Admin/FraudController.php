<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\FraudFlag;
use App\Modules\Admin\Services\FraudDetectionService;
use Illuminate\Http\Request;

class FraudController extends Controller
{
    public function __construct(
        private FraudDetectionService $fraudService
    ) {}

    public function index(Request $request)
    {
        $query = FraudFlag::with(['detector'])->latest();

        if ($search = $request->input('search')) {
            $query->whereHas('detector', fn($u) => $u->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%"))
                ->orWhere('description', 'like', "%{$search}%")
                ->orWhere('flag_type', 'like', "%{$search}%");
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($riskLevel = $request->input('risk_level')) {
            $query->where('severity', $riskLevel);
        }

        $alerts = $query->paginate(20)->withQueryString();

        $stats = [
            'total' => FraudFlag::count(),
            'open' => FraudFlag::where('status', 'open')->count(),
            'investigating' => FraudFlag::where('status', 'under_review')->count(),
            'resolved' => FraudFlag::where('status', 'resolved')->count(),
            'high_risk' => FraudFlag::whereIn('severity', ['high', 'critical'])->count(),
        ];

        return view('admin.fraud.index', compact('alerts', 'stats'));
    }

    public function show(FraudFlag $alert)
    {
        return view('admin.fraud.show', compact('alert'));
    }

    public function update(Request $request, FraudFlag $alert)
    {
        $validated = $request->validate([
            'decision' => 'required|in:confirm_fraud,false_positive,investigate_further,monitor',
            'notes' => 'nullable|string|max:1000',
            'action' => 'nullable|in:suspend_account,freeze_loans,flag_for_review,no_action',
        ]);

        $this->fraudService->resolveAlert($alert, $validated);

        return redirect()->route('admin.fraud.index')->with('success', 'Alert resolved successfully');
    }
}
