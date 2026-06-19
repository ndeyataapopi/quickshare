<?php

namespace Tests\Unit\KYC;

use App\Modules\KYC\Services\FileEncryptionService;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FileEncryptionTest extends TestCase
{
    protected FileEncryptionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new FileEncryptionService();
        Storage::fake('uploads');
    }

    public function test_can_encrypt_and_decrypt_content(): void
    {
        $original = 'This is sensitive KYC document content.';

        $encrypted = $this->service->encryptContent($original);

        $this->assertNotEquals($original, $encrypted);

        $decrypted = $this->service->decryptContent($encrypted);

        $this->assertEquals($original, $decrypted);
    }

    public function test_can_encrypt_and_decrypt_file_on_disk(): void
    {
        $content = 'Sensitive file content for encryption test.';
        Storage::disk('uploads')->put('test/source.txt', $content);

        $this->service->encrypt('test/source.txt', 'test/encrypted.enc', 'uploads');

        $this->assertTrue(Storage::disk('uploads')->exists('test/encrypted.enc'));

        $encryptedContent = Storage::disk('uploads')->get('test/encrypted.enc');
        $this->assertNotEquals($content, $encryptedContent);

        $decrypted = $this->service->decrypt('test/encrypted.enc', 'uploads');
        $this->assertEquals($content, $decrypted);
    }

    public function test_encrypted_content_is_base64(): void
    {
        $encrypted = $this->service->encryptContent('test data');

        // Valid base64 should decode without issue
        $this->assertNotFalse(base64_decode($encrypted, true));
    }

    public function test_different_encryptions_produce_different_output(): void
    {
        $content = 'Same content encrypted twice';

        $encrypted1 = $this->service->encryptContent($content);
        $encrypted2 = $this->service->encryptContent($content);

        // Due to random IV, same content should produce different ciphertext
        $this->assertNotEquals($encrypted1, $encrypted2);

        // But both should decrypt to the same content
        $this->assertEquals($content, $this->service->decryptContent($encrypted1));
        $this->assertEquals($content, $this->service->decryptContent($encrypted2));
    }

    public function test_decrypt_fails_with_corrupted_data(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->service->decryptContent('not-valid-encrypted-data');
    }
}
