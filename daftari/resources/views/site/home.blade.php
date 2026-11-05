@extends('layouts.site')

@section('title', __('Daftari — Saudi VAT Invoicing & Accounting'))

@section('content')
@php($sections = \App\Models\CmsSection::forPage('home'))
@foreach ($sections as $section)
    @include('site.partials.cms-section', ['section' => $section])
@endforeach
@endsection
