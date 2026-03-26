@extends('fleet-idp::layouts.minimal')

@section('title', $title)

@section('content')
    <h1 style="font-size: 1.25rem; margin: 0 0 0.75rem;">{{ $title }}</h1>
    <p style="margin: 0 0 1.25rem; color: #444;">{{ $lead }}</p>
    <form method="post" action="{{ route($routeName) }}" style="margin: 0;">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}" autocomplete="off">
        <button type="submit" class="btn">{{ $buttonLabel }}</button>
    </form>
@endsection
