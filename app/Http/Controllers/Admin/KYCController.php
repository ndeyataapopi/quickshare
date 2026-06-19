<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\KYC\Models\KYCSubmission;
use App\Modules\KYC\Services\KYCService;
use Illuminate\Http\Request;

class KYCController extends Controller
{
    public function __construct(
        private KYCService $kycService
    ) {}

    public function index(\Illuminate\Http\Request $request)
    {
        $query = KYCSubmission::with('user')->latest();

        if ($search = $request->input('search')) {
            $query->whereHas('user', fn($u) => $u->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%"));
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $submissions = $query->paginate(20)->withQueryString();
        return view('admin.kyc.index', compact('submissions'));
    }

    public function show(KYCSubmission $submission)
    {
        $submission->load('documents');
        return view('admin.kyc.show', compact('submission'));
    }

    public function viewDocument(\App\Modules\KYC\Models\KycDocument $document)
    {
        $fileContent = $this->kycService->getDecryptedDocument($document);
        
        return response($fileContent)
            ->header('Content-Type', $document->mime_type)
            ->header('Content-Disposition', 'inline; filename="' . $document->original_filename . '"');
    }

    public function update(Request $request, KYCSubmission $submission)
    {
        $validated = $request->validate([
            'decision' => 'required|in:approve,reject,resubmit',
            'notes'    => 'nullable|string|max:1000',
        ]);

        $reviewer = $request->user();

        if ($validated['decision'] === 'approve') {
            $this->kycService->approve($submission, $reviewer, $validated['notes'] ?? null);
        } elseif ($validated['decision'] === 'resubmit') {
            $this->kycService->reject($submission, $reviewer, $validated['notes'] ?? 'Please resubmit with clearer documents.', [], true);
        } else {
            $this->kycService->reject($submission, $reviewer, $validated['notes'] ?? 'KYC rejected by admin.', [], false);
        }

        return redirect()->route('admin.kyc.index')->with('success', 'KYC review completed.');
    }
}
