<table width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation">
    <tr>
        <td style="font-size: 15px; line-height: 24px; color: #334155;">
            <!-- Section Label -->
            <p
                style="margin: 0 0 4px; font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.08em;">
                🔐 Password Reset</p>

            <p style="margin: 0 0 20px; font-size: 16px; line-height: 26px; color: #334155;">
                Hi <strong style="color: #0f172a;">{{ $user->name }}</strong>,
            </p>

            <p style="margin: 0 0 28px; font-size: 15px; line-height: 24px; color: #334155;">We received a request to
                reset your password. If you made this request, please use the option below to set a new password.</p>

            @if (!empty($reset->url))
                <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin: 36px 0;">
                    <tr>
                        <td align="center">
                            <a href="{{ $reset->url }}" target="_blank" rel="noopener"
                                style="display: inline-block; padding: 14px 36px; background-color: #003D99; color: #ffffff !important; text-decoration: none; border-radius: 8px; font-weight: 700; font-size: 15px; line-height: 20px; letter-spacing: -0.01em;">
                                Reset Password
                            </a>
                        </td>
                    </tr>
                </table>

                <table width="100%" cellpadding="0" cellspacing="0" role="presentation"
                    style="margin-top: 24px; border-top: 1px solid #e2e8f0;">
                    <tr>
                        <td style="padding: 20px 0 0;">
                            <p style="margin: 0; font-size: 12px; line-height: 18px; color: #94a3b8;">
                                If you're having trouble clicking the button, copy and paste this link:
                                <span
                                    style="display: block; margin-top: 6px; word-break: break-all; color: #003D99;">{{ $reset->url }}</span>
                            </p>
                        </td>
                    </tr>
                </table>
            @else
                <!-- Section Header -->
                <p
                    style="margin: 32px 0 12px; font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.08em;">
                    Reset Token</p>

                <!-- Data Card -->
                <table width="100%" cellpadding="0" cellspacing="0" role="presentation"
                    style="width: 100%; border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 24px; background-color: #f8fafc; overflow: hidden;">
                    <tbody>
                        <tr>
                            <td
                                style="padding: 13px 18px; border-bottom: 1px solid #e2e8f0; font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.06em; width: 38%;">
                                Token</td>
                            <td
                                style="padding: 13px 18px; border-bottom: 1px solid #e2e8f0; font-size: 14px; color: #1e293b; text-align: right; font-weight: 500; font-family: 'Courier New', monospace; letter-spacing: 0.05em;">
                                {{ $reset->token }}</td>
                        </tr>
                    </tbody>
                </table>
            @endif

            @if (!empty($reset->expires))
                <!-- Expiry Info -->
                <table width="100%" cellpadding="0" cellspacing="0" role="presentation"
                    style="background-color: #fef9c3; border: 1px solid #fde68a; border-radius: 8px; margin: 24px 0;">
                    <tr>
                        <td style="padding: 12px 16px;">
                            <p style="margin: 0; font-size: 12px; line-height: 18px; color: #a16207;">⏱️ This reset
                                option will expire in <strong>{{ $reset->expires }} minutes</strong>.</p>
                        </td>
                    </tr>
                </table>
            @endif

            <!-- Safety Note Panel -->
            <table width="100%" cellpadding="0" cellspacing="0" role="presentation"
                style="background-color: #f0f9ff; border: 1px solid #bae6fd; border-radius: 8px; margin: 24px 0;">
                <tr>
                    <td style="padding: 16px 20px;">
                        <p style="margin: 0; font-size: 14px; line-height: 22px; color: #0c4a6e;">ℹ️ If you did not
                            request a password reset, you can safely ignore this email.</p>
                    </td>
                </tr>
            </table>

            <p style="margin: 28px 0 0; font-size: 15px; line-height: 24px; color: #334155;">
                Regards,<br>
                <strong style="color: #0f172a;">{{ $app->name }} Team</strong>
            </p>
        </td>
    </tr>
</table>
