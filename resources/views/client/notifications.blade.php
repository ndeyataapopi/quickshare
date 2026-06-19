@extends('layouts.app')
@section('title', 'Notifications')
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
    @if(session('success'))<div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="close" data-dismiss="alert"><span>&times;</span></button></div>@endif
    <div class="row"><div class="col-12"><div class="card"><div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="card-title text-uppercase mb-0">Notifications
                @if($unreadCount > 0)<span class="badge badge-danger ml-2">{{ $unreadCount }} unread</span>@endif
            </h5>
            @if($unreadCount > 0)
            <form method="POST" action="{{ route('client.notifications.read-all') }}">@csrf
                <button class="btn btn-sm btn-outline-primary"><i class="mdi mdi-check-all"></i> Mark All Read</button>
            </form>
            @endif
        </div>
        @if($notifications->isEmpty())
            <div class="text-center py-5"><i class="mdi mdi-bell-outline text-muted" style="font-size:64px;"></i>
                <h5 class="mt-3 text-muted">No Notifications</h5><p class="text-muted">You're all caught up!</p></div>
        @else
        <div class="list-group list-group-flush">
            @foreach($notifications as $n)
            @php
                $unread  = is_null($n->read_at);
                $message = $n->data['message'] ?? 'You have a new notification.';
                $type    = $n->data['type'] ?? '';
                $icons   = ['kyc_approved'=>['mdi-check-circle','text-success'],'kyc_rejected'=>['mdi-close-circle','text-danger'],'loan_approved'=>['mdi-cash-check','text-success'],'loan_rejected'=>['mdi-cash-remove','text-danger'],'loan_funded'=>['mdi-bank-transfer','text-primary'],'loan_disbursed'=>['mdi-bank-transfer-out','text-primary'],'repayment_received'=>['mdi-cash-usd','text-success'],'repayment_reminder'=>['mdi-alarm','text-warning'],'repayment_overdue'=>['mdi-alert-circle','text-danger']];
                [$ico, $cls] = $icons[$type] ?? ['mdi-bell','text-muted'];
            @endphp
            <div class="list-group-item py-3 {{ $unread ? 'border-left border-primary bg-light' : '' }}">
                <div class="d-flex align-items-start">
                    <i class="mdi {{ $ico }} {{ $cls }} mr-3 mt-1" style="font-size:22px;"></i>
                    <div class="flex-grow-1">
                        <p class="mb-1 {{ $unread ? 'font-weight-bold' : '' }}">{{ $message }}</p>
                        <small class="text-muted">{{ $n->created_at->diffForHumans() }}</small>
                    </div>
                    <div class="ml-3 text-right flex-shrink-0">
                        @if($unread)<span class="badge badge-primary d-block mb-1">New</span>
                        <form method="POST" action="{{ route('client.notifications.read', $n->id) }}">@csrf
                            <button class="btn btn-xs btn-outline-secondary" style="font-size:11px;">Mark Read</button>
                        </form>@endif
                    </div>
                </div>
            </div>
            @endforeach
        </div>
        <div class="mt-3">{{ $notifications->links() }}</div>
        @endif
    </div></div></div></div>
</div>
@endsection
