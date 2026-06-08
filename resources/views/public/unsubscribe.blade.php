@extends('layouts.public')

@push('head')
    <meta name="robots" content="noindex, follow">
@endpush

@section('content')
    <div class="min-h-[60vh] flex items-center justify-center px-4 py-20 bg-slate-50">
        @livewire('unsubscribe', ['status' => $status])
    </div>
@endsection
