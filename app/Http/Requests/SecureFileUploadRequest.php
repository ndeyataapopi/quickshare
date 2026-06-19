<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

class SecureFileUploadRequest extends FormRequest
{
    protected array $allowedMimeTypes = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'application/pdf',
    ];

    protected int $maxFileSize = 5 * 1024 * 1024; // 5MB

    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:' . ($this->maxFileSize / 1024), // KB
                'mimes:jpeg,png,gif,webp,pdf',
                'mimetypes:' . implode(',', $this->allowedMimeTypes),
            ],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $file = $this->file('file');

            if (! $file instanceof UploadedFile) {
                return;
            }

            // Verify actual MIME type (not just extension)
            $this->validateMimeType($file, $validator);

            // Scan for embedded scripts in images
            $this->scanForEmbeddedScripts($file, $validator);

            // Verify file size matches header
            $this->verifyFileSize($file, $validator);
        });
    }

    protected function validateMimeType(UploadedFile $file, $validator): void
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $detectedType = finfo_file($finfo, $file->getPathname());
        finfo_close($finfo);

        if (! in_array($detectedType, $this->allowedMimeTypes)) {
            $validator->errors()->add('file', 'Invalid file type detected.');
        }
    }

    protected function scanForEmbeddedScripts(UploadedFile $file, $validator): void
    {
        $content = file_get_contents($file->getPathname());

        // Check for common script patterns
        $patterns = [
            '/<\?php/i',
            '/<script/i',
            '/javascript:/i',
            '/vbscript:/i',
            '/onload=/i',
            '/onerror=/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content)) {
                $validator->errors()->add('file', 'File contains potentially dangerous content.');
                break;
            }
        }
    }

    protected function verifyFileSize(UploadedFile $file, $validator): void
    {
        $actualSize = filesize($file->getPathname());
        $declaredSize = $file->getSize();

        // Allow 1% variance for file system overhead
        $variance = $declaredSize * 0.01;

        if (abs($actualSize - $declaredSize) > $variance) {
            $validator->errors()->add('file', 'File size mismatch detected.');
        }
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Please select a file to upload.',
            'file.max' => 'File size must not exceed ' . ($this->maxFileSize / 1024 / 1024) . 'MB.',
            'file.mimes' => 'File must be a JPEG, PNG, GIF, WebP, or PDF.',
        ];
    }
}
