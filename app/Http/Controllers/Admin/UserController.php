<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\ReferralCode;
use App\Models\SourceOfIncome;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

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

    public function create()
    {
        return view('admin.users.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'national_id' => ['required', 'numeric', 'digits:11', 'unique:users,national_id', function ($attribute, $value, $fail) use ($request) {
                // Validate that first 6 digits (YYMMDD) match date of birth
                $dateOfBirth = $request->input('date_of_birth');
                if ($dateOfBirth) {
                    $dob = \Carbon\Carbon::parse($dateOfBirth);
                    $yy = $dob->format('y');
                    $mm = $dob->format('m');
                    $dd = $dob->format('d');
                    $expectedPrefix = $yy . $mm . $dd;
                    $actualPrefix = substr($value, 0, 6);
                    
                    if ($actualPrefix !== $expectedPrefix) {
                        $fail('The first 6 digits of the National ID must match the date of birth in YYMMDD format.');
                    }
                }
            }],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:20', 'unique:users,phone'],
            'date_of_birth' => ['required', 'date', 'before:-18 years'],
            'password' => ['required', 'string', 'min:8'],
            'status' => ['sometimes', 'in:active,pending,suspended'],
            'skip_referral' => ['sometimes', 'accepted'],

            // Address
            'address.country' => ['required', 'string', 'max:255'],
            'address.city' => ['required', 'string', 'max:255'],
            'address.suburb' => ['nullable', 'string', 'max:255'],
            'address.street' => ['required', 'string', 'max:255'],
            'address.house_number' => ['required', 'string', 'max:50'],

            // Source of Income
            'source_of_income.profession' => ['required', 'string', 'in:employed,self-employed,unemployed'],
            'source_of_income.company_name' => ['nullable', 'required_unless:source_of_income.profession,unemployed', 'string', 'max:255'],
            'source_of_income.city' => ['nullable', 'required_unless:source_of_income.profession,unemployed', 'string', 'max:255'],
            'source_of_income.country' => ['nullable', 'required_unless:source_of_income.profession,unemployed', 'string', 'max:255'],
        ]);

        return DB::transaction(function () use ($validated, $request) {
            // Generate unique referral code for the new user
            $referralCode = $this->generateUniqueCode();

            // Create user
            $user = User::create([
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'national_id' => $validated['national_id'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'date_of_birth' => $validated['date_of_birth'],
                'password' => $validated['password'],
                'referral_code' => $referralCode,
                'referred_by' => null,
                'trust_score' => 50.00,
                'status' => $validated['status'] ?? 'pending',
            ]);

            // Create address
            $user->address()->create([
                'country' => $validated['address']['country'],
                'city' => $validated['address']['city'],
                'suburb' => $validated['address']['suburb'] ?? null,
                'street' => $validated['address']['street'],
                'house_number' => $validated['address']['house_number'],
            ]);

            // Create source of income
            $user->sourceOfIncome()->create([
                'profession' => $validated['source_of_income']['profession'],
                'company_name' => $validated['source_of_income']['company_name'] ?? null,
                'city' => $validated['source_of_income']['city'] ?? null,
                'country' => $validated['source_of_income']['country'] ?? null,
            ]);

            // Generate referral code record
            $user->referralCode()->create([
                'code' => $referralCode,
                'is_active' => true,
            ]);

            // Assign client role
            $user->assignRole(UserRole::CLIENT->value);

            // Send email verification notification
            $user->sendEmailVerificationNotification();

            // Send phone OTP for verification
            $otpService = app(\App\Modules\Auth\Services\OtpService::class);
            $otpService->sendOtp($user->phone);

            return redirect()->route('admin.users.show', $user)
                ->with('success', 'Client created successfully. Email verification and phone OTP have been sent.');
        });
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

    public function manageRolesAndPermissions(User $user)
    {
        $user->load(['roles', 'permissions']);

        return view('admin.users.roles', [
            'user' => $user,
            'roles' => Role::all(),
            'permissions' => Permission::all(),
        ]);
    }

    public function updateRoles(Request $request, User $user)
    {
        $validated = $request->validate([
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string', 'exists:roles,name'],
        ]);

        $user->syncRoles($validated['roles'] ?? []);

        return redirect()->route('admin.users.roles', $user)
            ->with('success', 'User roles updated successfully.');
    }

    public function updatePermissions(Request $request, User $user)
    {
        $validated = $request->validate([
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $user->syncPermissions($validated['permissions'] ?? []);

        return redirect()->route('admin.users.roles', $user)
            ->with('success', 'User permissions updated successfully.');
    }

    protected function generateUniqueCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (User::where('referral_code', $code)->exists());

        return $code;
    }
}
