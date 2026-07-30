@extends('layouts.app')
@section('content')
<div class="page-breadcrumb border-bottom">
    <div class="row">
        <div class="col-lg-3 col-md-4 col-xs-12 align-self-center">
            <h5 class="font-medium text-uppercase mb-0"><i class="mdi mdi-account-check mr-2"></i>KYC Verification</h5>
        </div>
        <div class="col-lg-9 col-md-8 col-xs-12 align-self-center">
            <nav aria-label="breadcrumb" class="mt-2 float-md-right float-left">
                <ol class="breadcrumb mb-0 justify-content-end p-0">
                    <li class="breadcrumb-item"><a href="{{ route('client.dashboard') }}">Home</a></li>
                    <li class="breadcrumb-item active">KYC Verification</li>
                </ol>
            </nav>
        </div>
    </div>
</div>
<div class="page-content container-fluid">
    @php $submission = auth()->user()->kycSubmission; @endphp

    @if(session('success'))
        <div class="alert alert-success"><i class="mdi mdi-check-circle mr-2"></i> {{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger"><i class="mdi mdi-close-circle mr-2"></i> {{ session('error') }}</div>
    @endif

    @if($submission && $submission->status === 'pending')
        <div class="alert alert-warning"><i class="mdi mdi-clock-outline mr-2"></i> Your KYC documents are under review. This typically takes 1–2 business days.</div>
    @elseif($submission && $submission->status === 'approved')
        <div class="alert alert-success"><i class="mdi mdi-check-circle mr-2"></i> Your KYC has been approved. You are fully verified and can apply for loans.</div>
    @elseif($submission && $submission->status === 'resubmission_required')
        <div class="alert alert-warning"><i class="mdi mdi-alert mr-2"></i> Resubmission required. Reason: {{ $submission->rejection_reason ?? 'Not specified' }}</div>
    @endif

    @if(!$submission || in_array($submission->status, ['rejected', 'resubmission_required']))
    <div class="row justify-content-center">
        <div class="col-md-9">
            @if ($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                </div>
            @endif
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title text-uppercase mb-1">Submit KYC Documents</h5>
                    <p class="text-muted mb-4">All documents are encrypted and stored securely. Max file size: 10MB. Accepted: PDF, JPG, PNG.</p>
                    
                    <form action="{{ route('client.kyc.store') }}" method="POST" enctype="multipart/form-data" id="kycForm">
                        @csrf
                        <h6 class="text-uppercase font-weight-bold mt-3 mb-3 text-primary"><i class="mdi mdi-card-text-outline mr-2"></i>Identity Document</h6>
                        <div class="form-group row">
                            <label class="col-sm-4 col-form-label">Document Type <span class="text-danger">*</span></label>
                            <div class="col-sm-8">
                                <select name="document_type" class="form-control @error('document_type') is-invalid @enderror" required>
                                    <option value="">Select document type</option>
                                    <option value="national_id" {{ old('document_type') === 'national_id' ? 'selected' : '' }}>National ID</option>
                                    <option value="passport" {{ old('document_type') === 'passport' ? 'selected' : '' }}>Passport</option>
                                    <option value="drivers_license" {{ old('document_type') === 'drivers_license' ? 'selected' : '' }}>Driver's License</option>
                                </select>
                                @error('document_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <div class="form-group row">
                            <label class="col-sm-4 col-form-label">Document Number <span class="text-danger">*</span></label>
                            <div class="col-sm-8">
                                <input type="text" name="document_number"
                                    class="form-control @error('document_number') is-invalid @enderror"
                                    value="{{ old('document_number') }}" placeholder="e.g. 01010100100" required>
                                @error('document_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <div class="form-group row">
                            <label class="col-sm-4 col-form-label">Issuing Country <span class="text-danger">*</span></label>
                            <div class="col-sm-8">
                                <input type="text" name="issuing_country" maxlength="2"
                                    class="form-control @error('issuing_country') is-invalid @enderror"
                                    value="{{ old('issuing_country', 'NA') }}" placeholder="2-letter code e.g. NA" required>
                                @error('issuing_country')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <div class="form-group row">
                            <label class="col-sm-4 col-form-label">Document Expiry Date <span class="text-danger">*</span></label>
                            <div class="col-sm-8">
                                <input type="date" name="expiry_date"
                                    class="form-control @error('expiry_date') is-invalid @enderror"
                                    value="{{ old('expiry_date') }}" min="{{ now()->addDay()->format('Y-m-d') }}" required>
                                @error('expiry_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <h6 class="text-uppercase font-weight-bold mt-4 mb-3 text-primary"><i class="mdi mdi-file-upload mr-2"></i>Upload Documents</h6>
                        
                        <div class="form-group row">
                            <label class="col-sm-4 col-form-label">Identity Document <span class="text-danger">*</span></label>
                            <div class="col-sm-8">
                                <div class="custom-file-upload">
                                    <input type="file" name="national_id" id="national_id" class="file-input" accept=".pdf,.jpg,.jpeg,.png" required>
                                    <label for="national_id" class="file-label">
                                        <i class="mdi mdi-cloud-upload mr-2"></i>
                                        <span class="file-text">Choose file or drag here</span>
                                        <span class="file-info">PDF, JPG, PNG (Max 10MB)</span>
                                    </label>
                                </div>
                                @error('national_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        
                        <div class="form-group row">
                            <label class="col-sm-4 col-form-label">Payslip</label>
                            <div class="col-sm-8">
                                <div class="custom-file-upload">
                                    <input type="file" name="payslip" id="payslip" class="file-input" accept=".pdf,.jpg,.jpeg,.png">
                                    <label for="payslip" class="file-label">
                                        <i class="mdi mdi-cloud-upload mr-2"></i>
                                        <span class="file-text">Choose file or drag here</span>
                                        <span class="file-info">PDF, JPG, PNG (Max 10MB)</span>
                                    </label>
                                </div>
                                @error('payslip')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        
                        <div class="form-group row">
                            <label class="col-sm-4 col-form-label">Bank Statement (3 months)</label>
                            <div class="col-sm-8">
                                <div class="custom-file-upload">
                                    <input type="file" name="bank_statement" id="bank_statement" class="file-input" accept=".pdf,.jpg,.jpeg,.png">
                                    <label for="bank_statement" class="file-label">
                                        <i class="mdi mdi-cloud-upload mr-2"></i>
                                        <span class="file-text">Choose file or drag here</span>
                                        <span class="file-info">PDF, JPG, PNG (Max 10MB)</span>
                                    </label>
                                </div>
                                @error('bank_statement')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        
                        <div class="form-group row">
                            <label class="col-sm-4 col-form-label">Selfie Photo <span class="text-danger">*</span></label>
                            <div class="col-sm-8">
                                <div class="custom-file-upload">
                                    <input type="file" name="selfie" id="selfie" class="file-input" accept=".jpg,.jpeg,.png" required>
                                    <label for="selfie" class="file-label">
                                        <i class="mdi mdi-camera mr-2"></i>
                                        <span class="file-text">Take photo or choose file</span>
                                        <span class="file-info">JPG, PNG (Max 10MB)</span>
                                    </label>
                                </div>
                                @error('selfie')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <div class="form-group row mt-4">
                            <div class="col-sm-8 offset-sm-4">
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" name="terms" id="terms" value="1"
                                        class="custom-control-input @error('terms') is-invalid @enderror" required>
                                    <label class="custom-control-label" for="terms">
                                        I confirm that all submitted documents are genuine and belong to me.
                                        I consent to QuickShare processing my personal data for KYC purposes.
                                    </label>
                                    @error('terms')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group row mt-4">
                            <div class="col-sm-8 offset-sm-4">
                                <button type="submit" class="btn btn-primary btn-lg" id="submitBtn">
                                    <i class="mdi mdi-upload mr-2"></i> Submit KYC Documents
                                </button>
                                <a href="{{ route('client.dashboard') }}" class="btn btn-secondary btn-lg ml-2">Cancel</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>

<style>
.custom-file-upload {
    position: relative;
    display: inline-block;
    width: 100%;
}

.file-input {
    position: absolute;
    opacity: 0;
    width: 100%;
    height: 100%;
    cursor: pointer;
}

.file-label {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 2rem;
    border: 2px dashed #dee2e6;
    border-radius: 8px;
    background-color: #f8f9fa;
    cursor: pointer;
    transition: all 0.3s ease;
    text-align: center;
}

.file-label:hover {
    border-color: #007bff;
    background-color: #e3f2fd;
}

.file-input:focus + .file-label {
    border-color: #007bff;
    box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
}

.file-input:valid + .file-label {
    border-color: #28a745;
    background-color: #d4edda;
}

.file-text {
    font-weight: 600;
    color: #495057;
    margin-bottom: 0.5rem;
}

.file-info {
    font-size: 0.875rem;
    color: #6c757d;
}

.file-preview {
    margin-top: 1rem;
    padding: 0.5rem;
    background-color: #e9ecef;
    border-radius: 4px;
    font-size: 0.875rem;
}

.file-preview i {
    color: #28a745;
    margin-right: 0.5rem;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const MAX_WIDTH = 1600;
    const JPEG_QUALITY = 0.82;
    const IMAGE_TYPES = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];

    // Compress an image file in the browser using Canvas API.
    // Returns a Promise that resolves to a File (compressed or original on failure).
    function compressImage(file) {
        return new Promise((resolve) => {
            if (!IMAGE_TYPES.includes(file.type)) {
                resolve(file);
                return;
            }

            const img = new Image();
            const url = URL.createObjectURL(file);

            img.onload = function() {
                URL.revokeObjectURL(url);

                let width = img.naturalWidth;
                let height = img.naturalHeight;

                if (width > MAX_WIDTH) {
                    height = Math.round((height / width) * MAX_WIDTH);
                    width = MAX_WIDTH;
                }

                const canvas = document.createElement('canvas');
                canvas.width = width;
                canvas.height = height;
                const ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0, width, height);

                const isPng = file.type === 'image/png';
                const outputType = isPng ? 'image/png' : 'image/jpeg';
                const quality = isPng ? undefined : JPEG_QUALITY;

                canvas.toBlob(function(blob) {
                    if (!blob) {
                        resolve(file);
                        return;
                    }

                    const ext = isPng ? '.png' : '.jpg';
                    const baseName = file.name.replace(/\.[^/.]+$/, '');
                    const compressedFile = new File(
                        [blob],
                        baseName + ext,
                        { type: outputType, lastModified: Date.now() }
                    );
                    resolve(compressedFile);
                }, outputType, quality);
            };

            img.onerror = function() {
                URL.revokeObjectURL(url);
                resolve(file);
            };

            img.src = url;
        });
    }

    // Check if a file is an image (should be compressed) or PDF (should be left as-is)
    function isImage(file) {
        return IMAGE_TYPES.includes(file.type);
    }

    // Show a status message on the file label
    function showFileStatus(label, message) {
        let status = label.parentElement.querySelector('.file-status');
        if (!status) {
            status = document.createElement('div');
            status.className = 'file-status';
            status.style.cssText = 'margin-top:0.5rem;font-size:0.8rem;color:#007bff;';
            label.parentElement.appendChild(status);
        }
        status.textContent = message;
    }

    function clearFileStatus(label) {
        const status = label.parentElement.querySelector('.file-status');
        if (status) status.remove();
    }

    const fileInputs = document.querySelectorAll('.file-input');
    const compressedFiles = {};

    fileInputs.forEach(input => {
        const originalChangeHandler = function(e) {
            const file = e.target.files[0];
            const label = input.nextElementSibling;
            const fileText = label.querySelector('.file-text');
            const fileInfo = label.querySelector('.file-info');

            if (!file) return;

            // Validate file size (10MB)
            const maxSize = 10 * 1024 * 1024;
            if (file.size > maxSize) {
                alert('File size must be less than 10MB');
                input.value = '';
                return;
            }

            // Validate file type
            const allowedTypes = input.getAttribute('accept').split(',');
            if (!allowedTypes.includes('.' + file.name.split('.').pop().toLowerCase())) {
                alert('Invalid file type. Please upload an accepted file format.');
                input.value = '';
                return;
            }

            if (isImage(file)) {
                // Compress image in the browser
                showFileStatus(label, 'Preparing image...');

                compressImage(file).then(function(compressed) {
                    compressedFiles[input.name] = compressed;

                    // Update UI with compressed file info
                    fileText.textContent = compressed.name;
                    fileInfo.textContent = `${(compressed.size / 1024 / 1024).toFixed(2)} MB` +
                        (compressed.size < file.size ? ` (compressed from ${(file.size / 1024 / 1024).toFixed(2)} MB)` : '');

                    label.style.borderColor = '#28a745';
                    label.style.backgroundColor = '#d4edda';

                    clearFileStatus(label);

                    let preview = label.parentElement.querySelector('.file-preview');
                    if (!preview) {
                        preview = document.createElement('div');
                        preview.className = 'file-preview';
                        label.parentElement.appendChild(preview);
                    }
                    preview.innerHTML = `<i class="mdi mdi-check-circle"></i> ${compressed.name} ready to upload`;
                }).catch(function() {
                    // Fallback: use original file
                    compressedFiles[input.name] = file;
                    fileText.textContent = file.name;
                    fileInfo.textContent = `${(file.size / 1024 / 1024).toFixed(2)} MB`;
                    label.style.borderColor = '#28a745';
                    label.style.backgroundColor = '#d4edda';
                    clearFileStatus(label);

                    let preview = label.parentElement.querySelector('.file-preview');
                    if (!preview) {
                        preview = document.createElement('div');
                        preview.className = 'file-preview';
                        label.parentElement.appendChild(preview);
                    }
                    preview.innerHTML = `<i class="mdi mdi-check-circle"></i> ${file.name} ready to upload`;
                });
            } else {
                // PDF or other non-image: use as-is
                compressedFiles[input.name] = file;
                fileText.textContent = file.name;
                fileInfo.textContent = `${(file.size / 1024 / 1024).toFixed(2)} MB`;
                label.style.borderColor = '#28a745';
                label.style.backgroundColor = '#d4edda';

                let preview = label.parentElement.querySelector('.file-preview');
                if (!preview) {
                    preview = document.createElement('div');
                    preview.className = 'file-preview';
                    label.parentElement.appendChild(preview);
                }
                preview.innerHTML = `<i class="mdi mdi-check-circle"></i> ${file.name} ready to upload`;
            }
        };

        input.addEventListener('change', originalChangeHandler);

        // Drag and drop
        const label = input.nextElementSibling;

        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            label.addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        ['dragenter', 'dragover'].forEach(eventName => {
            label.addEventListener(eventName, () => {
                label.style.borderColor = '#007bff';
                label.style.backgroundColor = '#e3f2fd';
            });
        });

        ['dragleave', 'drop'].forEach(eventName => {
            label.addEventListener(eventName, () => {
                if (!input.files[0]) {
                    label.style.borderColor = '#dee2e6';
                    label.style.backgroundColor = '#f8f9fa';
                }
            });
        });

        label.addEventListener('drop', function(e) {
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                input.files = files;
                const event = new Event('change', { bubbles: true });
                input.dispatchEvent(event);
            }
        });
    });

    // Form submission: replace file inputs with compressed files and show loading state
    const form = document.getElementById('kycForm');
    const submitBtn = document.getElementById('submitBtn');

    form.addEventListener('submit', function(e) {
        // Replace original files with compressed versions before submit
        Object.keys(compressedFiles).forEach(function(inputName) {
            const input = form.querySelector(`input[name="${inputName}"]`);
            if (input && compressedFiles[inputName]) {
                const dt = new DataTransfer();
                dt.items.add(compressedFiles[inputName]);
                input.files = dt.files;
            }
        });

        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="mdi mdi-loading mdi-spin mr-2"></i> Uploading...';
    });
});
</script>
@endsection
