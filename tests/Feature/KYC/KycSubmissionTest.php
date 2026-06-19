<?php

namespace Tests\Feature\KYC;

use App\Models\User;
use App\Modules\KYC\Models\KycDocument;
use App\Modules\KYC\Models\KycSubmission;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class KycSubmissionTest extends TestCase
{
    use RefreshDatabase;

    protected User $borrower;
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Storage::fake('uploads');
        Queue::fake();

        $this->borrower = User::factory()->active()->create();
        $this->borrower->assignRole('borrower');

        $this->admin = User::factory()->active()->create();
        $this->admin->assignRole('admin');
    }

    protected function kycFiles(): array
    {
        return [
            'national_id_front' => UploadedFile::fake()->image('id_front.jpg', 800, 600)->size(500),
            'national_id_back' => UploadedFile::fake()->image('id_back.jpg', 800, 600)->size(500),
            'payslip' => UploadedFile::fake()->create('payslip.pdf', 1024, 'application/pdf'),
            'bank_statement' => UploadedFile::fake()->create('statement.pdf', 2048, 'application/pdf'),
        ];
    }

    // ─── Submission Tests ────────────────────────────────────────────

    public function test_borrower_can_submit_kyc_documents(): void
    {
        Sanctum::actingAs($this->borrower);

        $response = $this->postJson('/api/kyc/submit', $this->kycFiles());

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.submission.status', 'pending')
            ->assertJsonStructure([
                'data' => [
                    'submission' => [
                        'id', 'user_id', 'status', 'submitted_at',
                        'documents' => [
                            '*' => ['id', 'document_type', 'original_filename', 'status'],
                        ],
                    ],
                ],
            ]);

        $this->assertDatabaseHas('kyc_submissions', [
            'user_id' => $this->borrower->id,
            'status' => 'pending',
        ]);

        $this->assertDatabaseCount('kyc_documents', 4);
    }

    public function test_submission_fails_with_missing_documents(): void
    {
        Sanctum::actingAs($this->borrower);

        $response = $this->postJson('/api/kyc/submit', [
            'national_id_front' => UploadedFile::fake()->image('id_front.jpg'),
        ]);

        $response->assertStatus(422);
    }

    public function test_submission_fails_with_invalid_file_type(): void
    {
        Sanctum::actingAs($this->borrower);

        $files = $this->kycFiles();
        $files['national_id_front'] = UploadedFile::fake()->create('malware.exe', 500, 'application/x-msdownload');

        $response = $this->postJson('/api/kyc/submit', $files);

        $response->assertStatus(422);
    }

    public function test_submission_fails_with_oversized_file(): void
    {
        Sanctum::actingAs($this->borrower);

        $files = $this->kycFiles();
        $files['bank_statement'] = UploadedFile::fake()->create('huge.pdf', 11 * 1024, 'application/pdf');

        $response = $this->postJson('/api/kyc/submit', $files);

        $response->assertStatus(422);
    }

    public function test_cannot_submit_when_already_pending(): void
    {
        KycSubmission::create([
            'user_id' => $this->borrower->id,
            'status' => 'pending',
            'submitted_at' => now(),
        ]);

        Sanctum::actingAs($this->borrower);

        $response = $this->postJson('/api/kyc/submit', $this->kycFiles());

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_cannot_submit_when_already_approved(): void
    {
        KycSubmission::create([
            'user_id' => $this->borrower->id,
            'status' => 'approved',
            'submitted_at' => now(),
        ]);

        Sanctum::actingAs($this->borrower);

        $response = $this->postJson('/api/kyc/submit', $this->kycFiles());

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    // ─── Status Tests ────────────────────────────────────────────────

    public function test_user_can_check_kyc_status(): void
    {
        $submission = KycSubmission::create([
            'user_id' => $this->borrower->id,
            'status' => 'pending',
            'submitted_at' => now(),
        ]);

        Sanctum::actingAs($this->borrower);

        $response = $this->getJson('/api/kyc/status');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.submissions');
    }

    // ─── Admin Review Tests ──────────────────────────────────────────

    public function test_admin_can_view_pending_submissions(): void
    {
        KycSubmission::create([
            'user_id' => $this->borrower->id,
            'status' => 'pending',
            'submitted_at' => now(),
        ]);

        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/kyc/pending');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_admin_can_approve_submission(): void
    {
        $submission = KycSubmission::create([
            'user_id' => $this->borrower->id,
            'status' => 'pending',
            'submitted_at' => now(),
        ]);

        Sanctum::actingAs($this->admin);

        $response = $this->postJson("/api/kyc/submissions/{$submission->id}/review", [
            'action' => 'approve',
            'admin_notes' => 'All documents verified.',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.submission.status', 'approved');

        $this->assertDatabaseHas('kyc_submissions', [
            'id' => $submission->id,
            'status' => 'approved',
            'reviewed_by' => $this->admin->id,
        ]);
    }

    public function test_admin_can_reject_submission_with_reason(): void
    {
        $submission = KycSubmission::create([
            'user_id' => $this->borrower->id,
            'status' => 'pending',
            'submitted_at' => now(),
        ]);

        Sanctum::actingAs($this->admin);

        $response = $this->postJson("/api/kyc/submissions/{$submission->id}/review", [
            'action' => 'reject',
            'reason' => 'ID photo is blurry, please resubmit.',
            'allow_resubmission' => true,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.submission.status', 'resubmission_required');

        $this->assertDatabaseHas('kyc_submissions', [
            'id' => $submission->id,
            'status' => 'resubmission_required',
            'rejection_reason' => 'ID photo is blurry, please resubmit.',
        ]);
    }

    public function test_admin_can_permanently_reject_submission(): void
    {
        $submission = KycSubmission::create([
            'user_id' => $this->borrower->id,
            'status' => 'pending',
            'submitted_at' => now(),
        ]);

        Sanctum::actingAs($this->admin);

        $response = $this->postJson("/api/kyc/submissions/{$submission->id}/review", [
            'action' => 'reject',
            'reason' => 'Fraudulent documents detected.',
            'allow_resubmission' => false,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.submission.status', 'rejected');
    }

    public function test_reject_requires_reason(): void
    {
        $submission = KycSubmission::create([
            'user_id' => $this->borrower->id,
            'status' => 'pending',
            'submitted_at' => now(),
        ]);

        Sanctum::actingAs($this->admin);

        $response = $this->postJson("/api/kyc/submissions/{$submission->id}/review", [
            'action' => 'reject',
        ]);

        $response->assertStatus(422);
    }

    public function test_cannot_review_already_approved_submission(): void
    {
        $submission = KycSubmission::create([
            'user_id' => $this->borrower->id,
            'status' => 'approved',
            'submitted_at' => now(),
            'reviewed_at' => now(),
            'reviewed_by' => $this->admin->id,
        ]);

        Sanctum::actingAs($this->admin);

        $response = $this->postJson("/api/kyc/submissions/{$submission->id}/review", [
            'action' => 'approve',
        ]);

        $response->assertStatus(422);
    }

    // ─── RBAC Tests ──────────────────────────────────────────────────

    public function test_borrower_cannot_access_admin_kyc_routes(): void
    {
        Sanctum::actingAs($this->borrower);

        $this->getJson('/api/kyc/pending')->assertStatus(403);
    }

    public function test_unauthenticated_user_cannot_access_kyc(): void
    {
        $this->postJson('/api/kyc/submit', $this->kycFiles())->assertStatus(401);
        $this->getJson('/api/kyc/status')->assertStatus(401);
        $this->getJson('/api/kyc/pending')->assertStatus(401);
    }

    public function test_compliance_officer_can_review_kyc(): void
    {
        $officer = User::factory()->active()->create();
        $officer->assignRole('compliance_officer');

        $submission = KycSubmission::create([
            'user_id' => $this->borrower->id,
            'status' => 'pending',
            'submitted_at' => now(),
        ]);

        Sanctum::actingAs($officer);

        $response = $this->postJson("/api/kyc/submissions/{$submission->id}/review", [
            'action' => 'approve',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.submission.status', 'approved');
    }
}
