{{--
    The password reset code email.

    Written as tables with inline styles rather than as a modern layout,
    because a good half of the clients that will render it stopped at about
    2003 — Outlook still lays out through Word. Flexbox, grid, and anything
    depending on an external stylesheet all fall apart there; nested tables
    with `style` attributes do not.

    No images either. Most clients block remote images by default, so a logo
    would leave a broken-image box exactly where the brand should be. The
    wordmark below is text, which always renders.
--}}
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="color-scheme" content="light" />
    <meta name="supported-color-schemes" content="light" />
    <title>{{ $appName }} password reset code</title>
</head>
<body style="margin:0; padding:0; width:100%; background-color:#F4F7F2; -webkit-font-smoothing:antialiased;">

    {{-- Shown in the inbox list beside the subject, and nowhere else. Without
         it clients pull the first words of the body in, which here would be
         the wordmark repeated back. --}}
    <div style="display:none; font-size:1px; color:#F4F7F2; line-height:1px; max-height:0; max-width:0; opacity:0; overflow:hidden;">
        Your code is {{ $code }} — it expires in {{ $minutes }} minutes.
    </div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#F4F7F2;">
        <tr>
            <td align="center" style="padding:32px 16px;">

                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:480px; margin:0 auto;">

                    {{-- Wordmark --}}
                    <tr>
                        <td align="center" style="padding:0 0 24px 0;">
                            <span style="font-family:Georgia,'Times New Roman',serif; font-size:26px; font-weight:bold; letter-spacing:0.5px; color:#0F3D2E;">
                                {{ $appName }}
                            </span>
                        </td>
                    </tr>

                    {{-- Card --}}
                    <tr>
                        <td style="background-color:#FFFFFF; border:1px solid #E1E9DE; border-radius:16px; padding:40px 32px;">

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td align="center" style="padding:0 0 8px 0;">
                                        <h1 style="margin:0; font-family:Georgia,'Times New Roman',serif; font-size:22px; line-height:30px; font-weight:bold; color:#0F3D2E;">
                                            Reset your password
                                        </h1>
                                    </td>
                                </tr>
                                <tr>
                                    <td align="center" style="padding:0 0 28px 0;">
                                        <p style="margin:0; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Helvetica,Arial,sans-serif; font-size:15px; line-height:22px; color:#4F7263;">
                                            Enter this code in the app to choose a new password.
                                        </p>
                                    </td>
                                </tr>

                                {{-- The code. Letter-spaced and oversized because it is the one
                                     thing in here anybody actually needs, and because it is
                                     usually read off the screen and typed into a phone rather
                                     than copied. --}}
                                <tr>
                                    <td align="center" style="padding:0 0 28px 0;">
                                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 auto;">
                                            <tr>
                                                <td align="center" style="background-color:#F4F7F2; border:1px solid #E1E9DE; border-radius:12px; padding:20px 28px;">
                                                    <span style="font-family:'SF Mono',SFMono-Regular,Menlo,Consolas,'Courier New',monospace; font-size:34px; line-height:40px; font-weight:bold; letter-spacing:10px; color:#0F3D2E; margin-left:10px;">{{ $code }}</span>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>

                                <tr>
                                    <td align="center" style="padding:0 0 4px 0;">
                                        <p style="margin:0; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Helvetica,Arial,sans-serif; font-size:14px; line-height:20px; color:#4F7263;">
                                            This code expires in <strong style="color:#0F3D2E;">{{ $minutes }} minutes</strong> and can only be used once.
                                        </p>
                                    </td>
                                </tr>
                            </table>

                        </td>
                    </tr>

                    {{-- The note that matters to anyone who did not ask for this. Outside
                         the card and quieter, so it does not compete with the code, but not
                         so quiet it reads as boilerplate. --}}
                    <tr>
                        <td style="padding:24px 8px 0 8px;">
                            <p style="margin:0; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Helvetica,Arial,sans-serif; font-size:13px; line-height:19px; color:#4F7263;">
                                Didn&rsquo;t ask to reset your password? You can ignore this email — nothing has changed, and your password stays as it is.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td align="center" style="padding:28px 8px 0 8px;">
                            <p style="margin:0; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Helvetica,Arial,sans-serif; font-size:12px; line-height:18px; color:#8AA396;">
                                Sent by {{ $appName }} because a password reset was requested for this address.
                            </p>
                        </td>
                    </tr>

                </table>

            </td>
        </tr>
    </table>

</body>
</html>
