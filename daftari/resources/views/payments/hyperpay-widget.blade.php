@extends('layouts.public')

@section('title', __('Complete payment'))

@section('content')
<div class="max-w-md mx-auto bg-white rounded-xl border border-slate-100 p-6">
    <h1 class="text-lg font-semibold text-slate-900 mb-4">{{ __('Complete payment') }}</h1>
    <script src="{{ $widgetScriptUrl }}"></script>
    <form action="" class="paymentWidgets" data-brands="VISA MASTER MADA APPLEPAY STC_PAY"></form>
</div>
@endsection
