{{--
    The plain-text half of the reset code email.

    Worth keeping in step with the HTML rather than letting it rot: a message
    sent as multipart/alternative scores better with spam filters than one that
    is HTML alone, which matters more here than usual — a reset code that lands
    in spam is a person locked out.
--}}
Reset your {{ $appName }} password

Enter this code in the app to choose a new password:

{{ $code }}

This code expires in {{ $minutes }} minutes and can only be used once.

Didn't ask to reset your password? You can ignore this email — nothing has
changed, and your password stays as it is.

--
Sent by {{ $appName }} because a password reset was requested for this address.
