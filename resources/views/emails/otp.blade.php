<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="x-apple-disable-message-reformatting">
    <title>{{ $subjectLine ?? __('OTP') }}</title>
    <!--[if mso]>
    <noscript>
        <xml>
            <o:OfficeDocumentSettings>
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
    </noscript>
    <![endif]-->
</head>
<body style="margin:0;padding:0;background-color:#f1f5f9;-webkit-font-smoothing:antialiased;">
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">
        {{ __('Your verification code is :code', ['code' => $otp]) }}
    </div>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#f1f5f9;">
        <tr>
            <td align="center" style="padding:40px 16px;">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:520px;margin:0 auto;">
                    <tr>
                        <td align="center" style="padding-bottom:24px;">
                            <span style="display:inline-block;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;font-size:15px;font-weight:700;letter-spacing:-0.02em;color:#4f46e1;">
                                {{ config('app.name') }}
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color:#ffffff;border-radius:16px;box-shadow:0 4px 6px -1px rgba(15,23,42,0.08),0 2px 4px -2px rgba(15,23,42,0.06);border:1px solid #e2e8f0;overflow:hidden;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                <tr>
                                    <td style="height:4px;background:linear-gradient(90deg,#6366f1,#8b5cf6,#a855f7);"></td>
                                </tr>
                                <tr>
                                    <td style="padding:32px 28px 28px;">
                                        <p style="margin:0 0 8px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;font-size:20px;font-weight:700;line-height:1.3;color:#0f172a;letter-spacing:-0.02em;">
                                            {{ __('Hello :name,', ['name' => $user->name]) }}
                                        </p>
                                        <p style="margin:0 0 24px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;font-size:15px;line-height:1.6;color:#64748b;">
                                            @if ($purpose === 'password_reset')
                                                {{ __('Use the code below to continue resetting your password. This helps keep your account secure.') }}
                                            @elseif ($purpose === 'resend')
                                                {{ __('Here is your new verification code. Enter it in the app to confirm your email.') }}
                                            @else
                                                {{ __('You’re almost done — enter this code in the app to verify your email and activate your account.') }}
                                            @endif
                                        </p>
                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                            <tr>
                                                <td align="center" style="padding:20px 16px;background-color:#f8fafc;border-radius:12px;border:1px dashed #cbd5e1;">
                                                    <p style="margin:0 0 6px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;font-size:11px;font-weight:600;letter-spacing:0.12em;text-transform:uppercase;color:#94a3b8;">
                                                        {{ __('Your code') }}
                                                    </p>
                                                    <p style="margin:0;font-family:ui-monospace,SFMono-Regular,'SF Mono',Menlo,Consolas,monospace;font-size:32px;font-weight:700;letter-spacing:0.28em;color:#0f172a;line-height:1.2;">
                                                        {{ $otp }}
                                                    </p>
                                                </td>
                                            </tr>
                                        </table>
                                        <p style="margin:20px 0 0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;font-size:13px;line-height:1.5;color:#94a3b8;">
                                            {{ __('This code expires in :minutes minutes.', ['minutes' => $expiresMinutes]) }}
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:0 28px 28px;">
                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#fffbeb;border-radius:10px;border:1px solid #fde68a;">
                                            <tr>
                                                <td style="padding:14px 16px;">
                                                    <p style="margin:0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;font-size:13px;line-height:1.5;color:#92400e;">
                                                        <strong style="color:#b45309;">{{ __('Security tip') }}</strong>
                                                        — {{ __('If you didn’t request this email, you can safely ignore it. Never share this code with anyone.') }}
                                                    </p>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding:28px 8px 0;">
                            <p style="margin:0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;font-size:12px;line-height:1.5;color:#94a3b8;">
                                © {{ date('Y') }} {{ config('app.name') }}. {{ __('All rights reserved.') }}
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
