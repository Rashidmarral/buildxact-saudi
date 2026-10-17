@extends('layouts.app')

@section('title', __('Customer Report'))

@section('content')
@include('user.reports.partials.party-report', [
    'type' => 'customer',
    'routeName' => 'app.reports.customers',
    'titleLabel' => __('Customer Report'),
    'subtitleLabel' => __('Every customer ever billed, with this period\'s activity and their live outstanding balance.'),
])
@endsection
