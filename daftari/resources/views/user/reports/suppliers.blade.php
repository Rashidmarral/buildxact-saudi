@extends('layouts.app')

@section('title', __('Supplier Report'))

@section('content')
@include('user.reports.partials.party-report', [
    'type' => 'supplier',
    'routeName' => 'app.reports.suppliers',
    'titleLabel' => __('Supplier Report'),
    'subtitleLabel' => __('Every supplier ever billed, with this period\'s activity and their live outstanding balance.'),
])
@endsection
