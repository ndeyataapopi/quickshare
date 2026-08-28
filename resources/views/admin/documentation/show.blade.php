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
                    <li class="breadcrumb-item"><a href="{{ route('admin.documentation.index') }}">Documentation</a></li>
                    <li class="breadcrumb-item active" aria-current="page">{{ $title }}</li>
                </ol>
            </nav>
        </div>
    </div>
</div>

<div class="page-content container-fluid">
    <div class="row">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title text-uppercase">Pages</h5>
                    <div class="form-group">
                        <input type="text" id="docs-sidebar-search" class="form-control form-control-sm" placeholder="Filter pages..." autocomplete="off">
                    </div>
                    <div class="list-group" id="docs-sidebar-list" style="max-height: 70vh; overflow-y: auto;">
                        @foreach($pages as $page)
                            <a href="{{ route('admin.documentation.show', ['path' => $page['path']]) }}" class="list-group-item list-group-item-action docs-sidebar-item {{ request()->route('path') === $page['path'] ? 'active' : '' }}" data-title="{{ e(strtolower($page['title'])) }}">
                                <small>{{ $page['title'] }}</small>
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-9">
            <div class="card">
                <div class="card-body documentation-content">
                    {!! $html !!}
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const input = document.getElementById('docs-sidebar-search');
    const items = document.querySelectorAll('.docs-sidebar-item');

    input.addEventListener('input', function () {
        const term = this.value.toLowerCase();

        items.forEach(function (item) {
            const title = item.getAttribute('data-title') || '';
            item.style.display = title.includes(term) ? '' : 'none';
        });
    });
});
</script>

<style>
.documentation-content h1 { font-size: 1.8rem; margin-bottom: 1rem; }
.documentation-content h2 { font-size: 1.4rem; margin-top: 1.5rem; margin-bottom: 0.75rem; }
.documentation-content h3 { font-size: 1.2rem; margin-top: 1.25rem; margin-bottom: 0.5rem; }
.documentation-content table { width: 100%; margin-bottom: 1rem; }
.documentation-content table th,
.documentation-content table td { border: 1px solid #dee2e6; padding: 0.5rem; }
.documentation-content pre { background: #f8f9fa; padding: 1rem; border-radius: 0.25rem; }
.documentation-content blockquote { border-left: 4px solid #007bff; padding-left: 1rem; color: #495057; }
</style>
@endsection
