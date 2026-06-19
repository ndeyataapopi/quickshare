<?php

namespace App\Modules\KYC\Services;

use Illuminate\Support\Facades\Storage;
use RuntimeException;

class FileEncryptionService
{
    protected string $cipher = 'aes-256-cbc';

    public function encrypt(string $sourcePath, string $destinationPath, string $disk = 'uploads'): void
    {
        $content = Storage::disk($disk)->get($sourcePath);

        if ($content === null) {
            throw new RuntimeException("File not found: {$sourcePath}");
        }

        $encrypted = $this->encryptContent($content);

        Storage::disk($disk)->put($destinationPath, $encrypted);
    }

    public function decrypt(string $filePath, string $disk = 'uploads'): string
    {
        $encrypted = Storage::disk($disk)->get($filePath);

        if ($encrypted === null) {
            throw new RuntimeException("Encrypted file not found: {$filePath}");
        }

        return $this->decryptContent($encrypted);
    }

    public function encryptContent(string $content): string
    {
        $key = $this->getEncryptionKey();
        $iv = random_bytes(openssl_cipher_iv_length($this->cipher));

        $encrypted = openssl_encrypt($content, $this->cipher, $key, OPENSSL_RAW_DATA, $iv);

        if ($encrypted === false) {
            throw new RuntimeException('File encryption failed.');
        }

        // Prepend IV to encrypted data for later decryption
        return base64_encode($iv . $encrypted);
    }

    public function decryptContent(string $encryptedContent): string
    {
        $key = $this->getEncryptionKey();
        $data = base64_decode($encryptedContent, true);

        if ($data === false) {
            throw new RuntimeException('File decryption failed: invalid encoded data.');
        }

        $ivLength = openssl_cipher_iv_length($this->cipher);

        if (strlen($data) < $ivLength) {
            throw new RuntimeException('File decryption failed: data is corrupted.');
        }

        $iv = substr($data, 0, $ivLength);
        $encrypted = substr($data, $ivLength);

        try {
            $decrypted = openssl_decrypt($encrypted, $this->cipher, $key, OPENSSL_RAW_DATA, $iv);
        } catch (\Throwable) {
            throw new RuntimeException('File decryption failed: OpenSSL error.');
        }

        if ($decrypted === false) {
            throw new RuntimeException('File decryption failed.');
        }

        return $decrypted;
    }

    protected function getEncryptionKey(): string
    {
        $key = config('app.key');

        if (str_starts_with($key, 'base64:')) {
            $key = base64_decode(substr($key, 7));
        }

        return $key;
    }
}
