@extends('layouts.site')

@section('title', __('About') . ' · Daftari')

@section('content')
@php($sections = \App\Models\CmsSection::forPage('about'))
@foreach ($sections as $section)
    @include('site.partials.cms-section', ['section' => $section])
@endforeach
@endsection
