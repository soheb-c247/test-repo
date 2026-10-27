@props(['title' => null])
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#0b2a4a">
<title>{{ $title ? $title.' · ' : '' }}{{ config('lab.name') }}</title>
<link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
<link rel="icon" href="{{ asset('favicon.png') }}" type="image/png" sizes="64x64">
<link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
@vite(['resources/css/app.css', 'resources/js/app.js'])
