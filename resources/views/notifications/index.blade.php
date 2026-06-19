@extends('layouts.app')
@section('content')
<div class="page-breadcrumb border-bottom">
    <div class="row">
        <div class="col-lg-3 col-md-4 col-xs-12 align-self-center">
            <h5 class="font-medium text-uppercase mb-0">Notifications</h5>
        </div>
        <div class="col-lg-9 col-md-8 col-xs-12 align-self-center">
            <nav aria-label="breadcrumb" class="mt-2 float-md-right float-left">
                <ol class="breadcrumb mb-0 justify-content-end p-0">
                    <li class="breadcrumb-item"><a href="{{ route('client.dashboard') }}">Home</a></li>
                    <li class="breadcrumb-item active">Notifications</li>
                </ol>
            </nav>
        </div>
    </div>
</div>
<div class="page-content container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="card-title text-uppercase mb-0">Your Notifications</h5>
                    </div>
                    @if(isset($notifications) && $notifications->count() > 0)
                    <div class="list-group">
                        @foreach($notifications as $notification)
                        <div class="list-group-item {{ is_null($notification->read_at) ? 'list-group-item-light' : '' }}">
                            <div class="d-flex w-100 justify-content-between align-items-start">
                                <div class="d-flex align-items-start">
                                    <i class="mdi mdi-bell{{ is_null($notification->read_at) ? '' : '-outline' }} text-{{ is_null($notification->read_at) ? 'primary' : 'muted' }} mr-3 mt-1" style="font-size:20px;"></i>
                                    <div>
                                        <p class="mb-0 {{ is_null($notification->read_at) ? 'font-weight-bold' : '' }}">
                                            {{ $notification->data['message'] ?? 'New notification' }}
                                        </p>
                                        <small class="text-muted">{{ $notification->created_at->diffForHumans() }}</small>
                                    </div>
                                </div>
                                @if(is_null($notification->read_at))
                                    <span class="badge badge-primary">New</span>
                                @endif
                            </div>
                        </div>
                        @endforeach
                    </div>
                    <div class="mt-3">{{ $notifications->links() }}</div>
                    @else
                    <div class="text-center py-5">
                        <i class="mdi mdi-bell-outline text-muted" style="font-size:64px;"></i>
                        <h5 class="mt-3 text-muted">No Notifications</h5>
                        <p class="text-muted">You have no notifications at this time.</p>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
