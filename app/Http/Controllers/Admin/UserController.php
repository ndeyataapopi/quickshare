<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::with(['roles', 'kycSubmission'])->latest();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($role = $request->input('role')) {
            $query->whereHas('roles', fn($r) => $r->where('name', $role));
        }

        if ($kyc = $request->input('kyc')) {
            if ($kyc === 'none') {
                $query->doesntHave('kycSubmission');
            } else {
                $query->whereHas('kycSubmission', fn($k) => $k->where('status', $kyc));
            }
        }

        if ($from = $request->input('date_from')) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to = $request->input('date_to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        $users = $query->paginate(20)->withQueryString();
        return view('admin.users.index', compact('users'));
    }

    public function show(User $user)
    {
        $user->load(['address', 'sourceOfIncome', 'kycSubmission', 'loans', 'fundingTransactions']);
        return view('admin.users.show', compact('user'));
    }

    public function updateStatus(Request $request, User $user)
    {
        $validated = $request->validate([
            'status' => 'required|in:active,suspended,pending',
        ]);

        $user->update(['status' => $validated['status']]);

        return redirect()->route('admin.users.show', $user)
            ->with('success', "User status updated to {$validated['status']}.");
    }
}
