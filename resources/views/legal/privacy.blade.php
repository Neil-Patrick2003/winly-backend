@extends('legal.layout')

@section('title', 'Privacy Policy')

@section('content')
    <p>
        {{ $appName }} is a community for sharing self-care and small wins. This policy explains what
        we collect when you use the {{ $appName }} app and website, why, who it is shared with, and
        the choices you have.
        @if ($operator !== $appName)
            {{ $appName }} is operated by {{ $operator }}.
        @endif
    </p>

    <h2>What we collect</h2>
    <div class="card">
        <ul>
            <li><strong>Account details:</strong> your name, username, email address and password. Your password is stored only as a secure hash; we never see it.</li>
            <li><strong>Profile:</strong> the profile photo, cover photo and bio you choose to add, and whether your account is private.</li>
            <li><strong>What you post:</strong> your wins (meditation, learning and movement entries) with their captions, photos and videos, your stories, comments, likes and saved posts.</li>
            <li><strong>Your connections:</strong> who you follow, who follows you, the circles you join or create, and circle invitations.</li>
            <li><strong>Activity:</strong> your streaks and progress, notifications, who has viewed or reacted to your stories, and when you were last active.</li>
            <li><strong>Device information:</strong> your device's name, which we use to label your sign-ins, and a push notification token if you allow notifications.</li>
        </ul>
    </div>
    <p>
        We do not collect your location or your contacts, we do not use advertising trackers, and
        we do not sell your personal information.
    </p>

    <h2>How we use it</h2>
    <ul>
        <li>To run your account and sign you in.</li>
        <li>To show your posts, stories and profile to the people you choose to share them with.</li>
        <li>To keep track of your streaks and progress.</li>
        <li>To send you notifications and, when you ask for one, a password reset code by email.</li>
        <li>To keep {{ $appName }} safe, including reviewing content that has been reported to us.</li>
    </ul>

    <h2>Who can see what you share</h2>
    <p>
        When you post a win, you choose who it is for: everyone, all of your circles, or only the
        circles you pick. Your stories are shown to the people who follow you until they expire after 24 hours. Your name,
        username, profile photo, cover photo and bio are visible to other members.
    </p>

    <h2>Services we rely on</h2>
    <p>We share only what each service needs to do its job for us:</p>
    <ul>
        <li><strong>Amazon Web Services (S3)</strong> stores the photos and videos you upload.</li>
        <li><strong>Brevo</strong> sends our emails, such as password reset codes.</li>
        <li><strong>Expo, Apple and Google</strong> deliver push notifications to your device.</li>
        <li><strong>Our hosting provider</strong> runs the servers that hold your account and posts.</li>
    </ul>
    <p>
        If you connect an AI assistant through <em>AI integration</em> in your settings, that
        assistant can read your {{ $appName }} data using the token you created. This happens only
        if you set it up, and you can revoke the token at any time.
    </p>
    <p>
        We may also disclose information if the law requires it, or to protect the safety of our
        members.
    </p>

    <h2>How long we keep it</h2>
    <p>
        We keep your information for as long as you have an account. Stories disappear from the app
        after 24 hours.
    </p>

    <h2>Deleting your account</h2>
    <p>
        You can delete your account at any time in the app under
        <strong>Profile → Settings → Delete account</strong>. This permanently removes your account
        and everything in it: your profile, photos, posts, stories, comments, likes, follows and
        circle memberships. Circles you created stay open for their other members. Copies may remain
        in our server backups for up to 30 days before they are overwritten.
        @if ($contactEmail)
            If you can no longer sign in, email <a href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a> from the address on your account and we will delete it for you.
        @endif
        <a href="{{ route('legal.delete-account') }}">More about deleting your account</a>.
    </p>

    <h2>Your rights</h2>
    <p>
        Under the Data Privacy Act of 2012 and similar laws, you have the right to access the
        personal information we hold about you, to correct it, to object to how it is used, and to
        have it deleted. You can edit most of it yourself in the app. For anything else, contact us
        below. If you are in the Philippines, you may also complain to the National Privacy
        Commission.
    </p>

    <h2>Security</h2>
    <p>
        Your information travels over encrypted connections and passwords are stored as hashes. No
        system is perfectly secure, but we work to protect what you trust us with.
    </p>

    <h2>Children</h2>
    <p>
        {{ $appName }} is not for anyone under {{ $minimumAge }}. If we learn that an account belongs
        to someone younger, we will delete it.
    </p>

    <h2>Changes to this policy</h2>
    <p>
        If we change this policy, we will update the date at the top of this page. If the change is
        significant, we will let you know in the app.
    </p>

    <h2>Contact us</h2>
    <p>
        @if ($contactEmail)
            Questions about your privacy? Email <a href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a>.
        @else
            Questions about your privacy? Contact {{ $operator }}.
        @endif
    </p>
@endsection
