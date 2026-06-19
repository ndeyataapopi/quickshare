@extends('layouts.app')
@section('content')
<div class="page-breadcrumb border-bottom">
    <div class="row">
        <div class="col-lg-3 col-md-4 col-xs-12 align-self-center">
            <h5 class="font-medium text-uppercase mb-0">{{ ucfirst($type ?? 'Report') }} Report</h5>
        </div>
        <div class="col-lg-9 col-md-8 col-xs-12 align-self-center">
            <nav aria-label="breadcrumb" class="mt-2 float-md-right float-left">
                <ol class="breadcrumb mb-0 justify-content-end p-0">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Home</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.reports.index') }}">Reports</a></li>
                    <li class="breadcrumb-item active">{{ ucfirst($type ?? 'Report') }}</li>
                </ol>
            </nav>
        </div>
    </div>
</div>
<div class="page-content container-fluid">
    <div class="row mb-4">
        @foreach($stats ?? [] as $label => $value)
        <div class="col-md-3 mb-3">
            <div class="card"><div class="card-body">
                <h6 class="text-uppercase text-muted">{{ ucfirst(str_replace('_',' ',$label)) }}</h6>
                <h3>{{ is_numeric($value) ? number_format($value) : $value }}</h3>
            </div></div>
        </div>
        @endforeach
    </div>
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="card-title text-uppercase mb-0">{{ ucfirst($type ?? 'Report') }} Data</h5>
                        <a href="{{ route('admin.reports.index') }}" class="btn btn-sm btn-outline-secondary"><i class="mdi mdi-arrow-left"></i> Back</a>
                    </div>
                    @if(isset($data) && $data->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-hover table-striped">
                            <thead class="thead-light">
                                <tr>
                                    @foreach(array_keys($data->first()->toArray()) as $col)
                                        <th>{{ ucfirst(str_replace('_',' ',$col)) }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($data as $row)
                                <tr>
                                    @foreach($row->toArray() as $val)
                                        <td>{{ is_array($val) ? json_encode($val) : $val }}</td>
                                    @endforeach
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">{{ $data->links() }}</div>
                    @else
                    <div class="text-center py-5">
                        <i class="mdi mdi-chart-line text-muted" style="font-size:64px;"></i>
                        <h5 class="mt-3 text-muted">No Report Data</h5>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
