<?php

namespace App\Http\Controllers;

use App\Modules\KYC\Models\KYCSubmission;
use App\Modules\KYC\Services\KYCService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class KYCController extends Controller
{
    public function __construct(
        private KYCService $kycService
    ) {}

    public function upload()
    {
        $submission = Auth::user()->kycSubmission;
        return view('kyc.upload', compact('submission'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'document_type'   => 'required|in:national_id,passport,drivers_license',
            'document_number' => 'required|string|max:255',
            'issuing_country' => 'required|string|size:2',
            'expiry_date'     => 'required|date|after:today',
            'national_id'     => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'payslip'         => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'bank_statement'  => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'selfie'          => 'required|file|mimes:jpg,jpeg,png|max:10240',
            'terms'           => 'required|accepted',
        ]);

        $this->kycService->submitKYC(Auth::user(), $validated);

        return redirect()->route('client.kyc.upload')->with('success', 'KYC documents submitted successfully. We will review within 1–2 business days.');
    }
}
