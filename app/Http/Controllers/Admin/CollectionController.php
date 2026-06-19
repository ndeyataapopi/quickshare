<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Collections\Models\CollectionCase;
use App\Modules\Collections\Services\CollectionService;
use Illuminate\Http\Request;

class CollectionController extends Controller
{
    public function __construct(
        private CollectionService $collectionService
    ) {}

    public function index(Request $request)
    {
        $query = CollectionCase::with(['loan.borrower'])->latest();

        if ($search = $request->input('search')) {
            $query->whereHas('loan.borrower', fn($u) => $u->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%"))
                ->orWhereHas('loan', fn($l) => $l->where('reference', 'like', "%{$search}%"));
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $cases = $query->paginate(20)->withQueryString();

        $stats = [
            'total' => CollectionCase::count(),
            'open' => CollectionCase::where('status', 'open')->count(),
            'resolved' => CollectionCase::where('status', 'resolved')->count(),
            'overdue_amount' => CollectionCase::sum('overdue_amount'),
            'recovered_amount' => CollectionCase::sum('amount_recovered'),
        ];

        return view('admin.collections.index', compact('cases', 'stats'));
    }

    public function show(CollectionCase $case)
    {
        return view('admin.collections.show', compact('case'));
    }

    public function update(Request $request, CollectionCase $case)
    {
        $validated = $request->validate([
            'resolution' => 'required|in:paid_in_full,partial_payment,payment_plan,written_off',
            'amount_recovered' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
        ]);

        $this->collectionService->resolveCase($case, $validated);

        return redirect()->route('admin.collections.index')->with('success', 'Case resolved successfully');
    }
}
