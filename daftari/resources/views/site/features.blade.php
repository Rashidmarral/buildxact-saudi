@extends('layouts.site')

@section('title', __('Features') . ' · Daftari')

@section('content')
@php($sections = \App\Models\CmsSection::forPage('features'))
@foreach ($sections as $section)
    @include('site.partials.cms-section', ['section' => $section])
@endforeach
@endsection
