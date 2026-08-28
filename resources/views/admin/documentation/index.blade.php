@extends('layouts.app')

@section('content')
<div class="page-breadcrumb border-bottom">
    <div class="row">
        <div class="col-lg-3 col-md-4 col-xs-12 align-self-center">
            <h5 class="font-medium text-uppercase mb-0">Platform Documentation</h5>
        </div>
        <div class="col-lg-9 col-md-8 col-xs-12 align-self-center">
            <nav aria-label="breadcrumb" class="mt-2 float-md-right float-left">
                <ol class="breadcrumb mb-0 justify-content-end p-0">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Home</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Documentation</li>
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
                    <h5 class="card-title text-uppercase">Documentation Pages</h5>
                    <p class="text-muted">Search the documentation, then select a page to view it.</p>

                    <div class="form-group">
                        <input type="text" id="documentation-search" class="form-control" placeholder="Search documentation..." autocomplete="off">
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover" id="documentation-table">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Path</th>
                                    <th class="text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($pages as $page)
                                <tr class="documentation-row" data-search="{{ e(strtolower($page['title'] . ' ' . $page['search'])) }}">
                                    <td>{{ $page['title'] }}</td>
                                    <td><small class="text-muted">{{ $page['path'] }}</small></td>
                                    <td class="text-right">
                                        <a href="{{ route('admin.documentation.show', ['path' => $page['path']]) }}" class="btn btn-sm btn-primary">View</a>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="3" class="text-center text-muted">No documentation pages found.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title text-uppercase">Version History</h5>
                    <p class="text-muted">View the documentation change log and platform evolution.</p>
                    <a href="{{ route('admin.documentation.show', ['path' => 'VERSIONS.md']) }}" class="btn btn-outline-primary btn-sm btn-block">View VERSIONS.md</a>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-body">
                    <h5 class="card-title text-uppercase">Architecture Decisions</h5>
                    <p class="text-muted">Important architectural decisions are recorded as ADRs.</p>
                    <a href="{{ route('admin.documentation.show', ['path' => 'adrs/001-manual-payments.md']) }}" class="btn btn-outline-secondary btn-sm btn-block mb-2">ADR-001 — Manual Payments</a>
                    <a href="{{ route('admin.documentation.show', ['path' => 'adrs/002-provider-agnostic-payments.md']) }}" class="btn btn-outline-secondary btn-sm btn-block">ADR-002 — Provider-Agnostic Payments</a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const input = document.getElementById('documentation-search');
    const rows = document.querySelectorAll('.documentation-row');

    input.addEventListener('input', function () {
        const term = this.value.toLowerCase();

        rows.forEach(function (row) {
            const search = row.getAttribute('data-search') || '';
            row.style.display = search.includes(term) ? '' : 'none';
        });
    });
});
</script>
@endsection
