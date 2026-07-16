@extends('layouts.app')
@section('content')
<div class="page-breadcrumb border-bottom">
    <div class="row">
        <div class="col-lg-3 col-md-4 col-xs-12 align-self-center">
            <h5 class="font-medium text-uppercase mb-0">KYC Review</h5>
        </div>
        <div class="col-lg-9 col-md-8 col-xs-12 align-self-center">
            <nav aria-label="breadcrumb" class="mt-2 float-md-right float-left">
                <ol class="breadcrumb mb-0 justify-content-end p-0">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Home</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.kyc.index') }}">KYC Reviews</a></li>
                    <li class="breadcrumb-item active">Review</li>
                </ol>
            </nav>
        </div>
    </div>
</div>
<div class="page-content container-fluid">
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-4">
                        <div class="d-flex align-items-center">
                            <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center mr-3" style="width:56px;height:56px;font-size:20px;font-weight:600;flex-shrink:0;">
                                {{ strtoupper(substr($submission->user->first_name,0,1)) }}{{ strtoupper(substr($submission->user->last_name,0,1)) }}
                            </div>
                            <div>
                                <h5 class="mb-0">{{ $submission->user->first_name }} {{ $submission->user->last_name }}</h5>
                                <small class="text-muted">{{ $submission->user->email }} &bull; {{ $submission->user->phone }}</small>
                            </div>
                        </div>
                        @php $sc=['pending'=>'warning','approved'=>'success','rejected'=>'danger','resubmission_required'=>'info']; @endphp
                        <span class="badge badge-{{ $sc[$submission->status] ?? 'secondary' }} p-2">{{ ucwords(str_replace('_',' ',$submission->status)) }}</span>
                    </div>
                    <h6 class="text-uppercase text-muted mb-3">Document Information</h6>
                    <div class="row mb-3"><div class="col-sm-4 text-muted">Document Type</div><div class="col-sm-8">{{ ucfirst(str_replace('_',' ',$submission->metadata['document_type'] ?? 'N/A')) }}</div></div>
                    <div class="row mb-3"><div class="col-sm-4 text-muted">Document Number</div><div class="col-sm-8">{{ $submission->metadata['document_number'] ?? 'N/A' }}</div></div>
                    <div class="row mb-3"><div class="col-sm-4 text-muted">Issuing Country</div><div class="col-sm-8">{{ $submission->metadata['issuing_country'] ?? 'N/A' }}</div></div>
                    <div class="row mb-3"><div class="col-sm-4 text-muted">Expiry Date</div><div class="col-sm-8">{{ $submission->metadata['expiry_date'] ?? 'N/A' }}</div></div>
                    <div class="row mb-3"><div class="col-sm-4 text-muted">Submitted</div><div class="col-sm-8">{{ optional($submission->submitted_at)->format('M j, Y g:i A') ?? $submission->created_at->format('M j, Y') }}</div></div>
                    @if($submission->documents && $submission->documents->count() > 0)
                    <h6 class="text-uppercase text-muted mt-4 mb-3">Documents</h6>
                    @foreach($submission->documents as $document)
                    <div class="mb-4">
                        <p class="small font-weight-bold mb-2">{{ $document->getDocumentLabel() }}</p>
                        @if(in_array($document->mime_type, ['application/pdf']))
                        <div class="embed-responsive embed-responsive-4by3 border rounded">
                            <iframe class="embed-responsive-item" src="{{ route('admin.kyc.document', $document) }}" style="height: 600px;"></iframe>
                        </div>
                        @else
                        <a href="{{ route('admin.kyc.document', $document) }}" target="_blank">
                            <img src="{{ route('admin.kyc.document', $document) }}" class="img-fluid rounded border" style="max-height: 600px;" alt="KYC Document">
                        </a>
                        @endif
                    </div>
                    @endforeach
                    @endif
                </div>
            </div>
        </div>
        <div class="col-md-4">
            @if($submission->isReviewable())
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title text-uppercase mb-3">Review Decision</h5>
                    @if(session('success'))<div class="alert alert-success p-2">{{ session('success') }}</div>@endif
                    <form method="POST" action="{{ route('admin.kyc.update', $submission) }}">
                        @csrf
                        @method('PUT')
                        <div class="form-group">
                            <label class="form-label font-weight-bold">Decision <span class="text-danger">*</span></label>
                            <div class="mt-1">
                                <div class="form-check mb-1">
                                    <input class="form-check-input" type="radio" name="decision" value="approve" id="d_approve" required>
                                    <label class="form-check-label text-success font-weight-bold" for="d_approve"><i class="mdi mdi-check-circle mr-1"></i>Approve</label>
                                </div>
                                <div class="form-check mb-1">
                                    <input class="form-check-input" type="radio" name="decision" value="resubmit" id="d_resubmit">
                                    <label class="form-check-label text-warning font-weight-bold" for="d_resubmit"><i class="mdi mdi-refresh mr-1"></i>Request Resubmission</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="decision" value="reject" id="d_reject">
                                    <label class="form-check-label text-danger font-weight-bold" for="d_reject"><i class="mdi mdi-close-circle mr-1"></i>Reject</label>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Notes <span class="text-muted small">(required for reject/resubmit)</span></label>
                            <textarea name="notes" class="form-control" rows="3" placeholder="Add review notes or rejection reason..."></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary btn-block">Submit Decision</button>
                        <a href="{{ route('admin.kyc.index') }}" class="btn btn-outline-secondary btn-block mt-2"><i class="mdi mdi-arrow-left mr-1"></i>Back</a>
                    </form>
                </div>
            </div>
            @else
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title text-uppercase mb-3">Review History</h5>
                    <div class="alert alert-{{ $sc[$submission->status] ?? 'secondary' }}">
                        <strong>{{ ucfirst($submission->status) }}</strong> on {{ $submission->updated_at->format('M j, Y') }}
                    </div>
                    @if($submission->notes)
                        <p class="text-muted">{{ $submission->notes }}</p>
                    @endif
                    <a href="{{ route('admin.kyc.index') }}" class="btn btn-outline-secondary btn-block mt-2"><i class="mdi mdi-arrow-left"></i> Back to KYC Reviews</a>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
