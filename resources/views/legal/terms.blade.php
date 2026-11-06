@extends('legal.layout')

@section('title', 'Terms of Service')

@section('content')
    <p>
        These terms are an agreement between you and {{ $operator }}@if ($operator !== $appName), which runs {{ $appName }}@endif.
        By creating an account or using {{ $appName }}, you agree to them and to our
        <a href="{{ route('legal.privacy') }}">Privacy Policy</a>.
    </p>

    <h2>Who can use {{ $appName }}</h2>
    <p>
        You must be at least {{ $minimumAge }} years old. You are responsible for your account and
        for keeping your password safe. Give us accurate information and do not pretend to be
        someone else.
    </p>

    <h2>Your content</h2>
    <p>
        You own what you post. By posting it, you give us permission to store it and show it to the
        people you chose to share it with, so that {{ $appName }} can work. This permission ends when
        you delete the content or your account. Only post photos and videos you have the right to
        share.
    </p>

    <h2>Community rules</h2>
    <p>
        {{ $appName }} is meant to be a positive, supportive place. We have
        <strong>zero tolerance</strong> for objectionable content and abusive behaviour. Do not post or
        send anything that:
    </p>
    <ul>
        <li>harasses, bullies, threatens or shames another person;</li>
        <li>is hateful or discriminates against people for who they are;</li>
        <li>is sexually explicit, or sexualises minors in any way;</li>
        <li>is violent, or encourages self-harm or harm to others;</li>
        <li>is spam, a scam, or misleading;</li>
        <li>breaks the law or someone else's rights, including their privacy or copyright.</li>
    </ul>

    <h2>Reporting and enforcement</h2>
    <p>
        If you see something that breaks these rules,
        @if ($contactEmail)
            report it to us at <a href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a>.
        @else
            report it to us.
        @endif
        We review reports within 24 hours. We may remove content and suspend or delete the accounts
        of people who break these rules, without warning.
    </p>

    <h2>Not medical advice</h2>
    <p>
        {{ $appName }} is for encouragement and keeping track of healthy habits. It is not medical,
        mental health or professional advice. If you are struggling or in danger, contact a
        professional or your local emergency services.
    </p>

    <h2>Ending your account</h2>
    <p>
        You can delete your account at any time under
        <strong>Profile → Settings → Delete account</strong>. We may suspend or close accounts that
        break these terms.
    </p>

    <h2>The service</h2>
    <p>
        We work hard to keep {{ $appName }} running, but it is provided "as is". We may change or
        stop features, and we cannot promise it will always be available or free of errors. To the
        extent the law allows, we are not liable for indirect losses or for content other members
        post.
    </p>

    <h2>Changes to these terms</h2>
    <p>
        If we change these terms, we will update the date at the top of this page. If the change is
        significant, we will let you know in the app. Using {{ $appName }} after a change means you
        accept the new terms.
    </p>

    <h2>Governing law</h2>
    <p>These terms are governed by the laws of {{ $country }}.</p>

    <h2>Contact us</h2>
    <p>
        @if ($contactEmail)
            Questions about these terms? Email <a href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a>.
        @else
            Questions about these terms? Contact {{ $operator }}.
        @endif
    </p>
@endsection
