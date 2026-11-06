@extends('legal.layout')

@section('title', 'Delete your account')

@section('content')
    <p>
        This page explains how to delete your {{ $appName }} account and what happens to your
        information when you do.
        @if ($operator !== $appName)
            {{ $appName }} is operated by {{ $operator }}.
        @endif
    </p>

    <h2>In the app</h2>
    <div class="card">
        <ol>
            <li>Open {{ $appName }} and sign in.</li>
            <li>Go to <strong>Profile → Settings → Delete account</strong>.</li>
            <li>Enter your password, tap <strong>Delete my account</strong> and confirm.</li>
        </ol>
    </div>
    <p>Your account is deleted straight away. This cannot be undone.</p>

    @if ($contactEmail)
        <h2>Without the app</h2>
        <p>
            If you no longer have the app or cannot sign in, email
            <a href="mailto:{{ $contactEmail }}?subject={{ rawurlencode('Delete my '.$appName.' account') }}">{{ $contactEmail }}</a>
            from the email address on your account and ask us to delete it. Include your username if
            you know it. We will confirm and delete the account within 7 days.
        </p>
    @endif

    <h2>What is deleted</h2>
    <div class="card">
        <ul>
            <li>Your account details: name, username, email address and password.</li>
            <li>Your profile, profile photo and cover photo.</li>
            <li>Your wins, with their captions, photos and videos, and your stories.</li>
            <li>Your comments, likes, saved posts, follows and circle memberships.</li>
            <li>Your streaks, notifications, sign-ins and push notification tokens.</li>
        </ul>
    </div>

    <h2>What is kept</h2>
    <p>
        Circles you created stay open for their other members, without you as the owner. Copies of
        your information may remain in our server backups for up to 30 days before they are
        overwritten. We do not keep anything else.
    </p>

    <p>
        See the <a href="{{ route('legal.privacy') }}">Privacy Policy</a> for more on how we handle
        your information.
    </p>
@endsection
