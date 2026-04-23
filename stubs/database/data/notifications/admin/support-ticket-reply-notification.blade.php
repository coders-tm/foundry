<table width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation">
    <tr>
        <td style="font-size: 15px; line-height: 24px; color: #334155;">
            <!-- Section Label -->
            <p
                style="margin: 0 0 4px; font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.08em;">
                💬 Ticket Update</p>

            <p style="margin: 0 0 20px; font-size: 16px; line-height: 26px; color: #334155;">
                Hi,
            </p>

            <p style="margin: 0 0 28px; font-size: 15px; line-height: 24px; color: #334155;">
                A new update has been made to the following ticket.
            </p>

            <!-- Ticket Details Card -->
            <table width="100%" cellpadding="0" cellspacing="0" role="presentation"
                style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 28px;">
                <tr>
                    <td style="padding: 12px 16px; border-bottom: 1px solid #e2e8f0;">
                        <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
                            <tr>
                                <td
                                    style="padding: 0; font-size: 12px; font-weight: 600; color: #64748b; width: 30%; vertical-align: middle;">
                                    Ticket</td>
                                <td
                                    style="padding: 0; font-size: 13px; font-weight: 700; color: #0f172a; vertical-align: middle; text-align: right;">
                                    #{{ $support_ticket->ticket_number }}</td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 12px 16px; border-bottom: 1px solid #e2e8f0;">
                        <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
                            <tr>
                                <td
                                    style="padding: 0; font-size: 12px; font-weight: 600; color: #64748b; width: 30%; vertical-align: middle;">
                                    Subject</td>
                                <td
                                    style="padding: 0; font-size: 13px; color: #334155; vertical-align: middle; text-align: right;">
                                    {{ $support_ticket->subject }}</td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>

            <!-- Section Header -->
            <p
                style="margin: 32px 0 12px; font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.08em;">
                Reply Message</p>

            <!-- Reply Content Panel -->
            <table width="100%" cellpadding="0" cellspacing="0" role="presentation"
                style="background-color: #f0f9ff; border: 1px solid #bae6fd; border-radius: 8px; margin-bottom: 24px;">
                <tr>
                    <td style="padding: 16px 20px;">
                        <p style="margin: 0; font-size: 14px; line-height: 22px; color: #0c4a6e;">
                            {!! $reply->message ?? '' !!}</p>
                    </td>
                </tr>
            </table>

            <!-- Attachments -->
            @if (count($reply->attachments ?? []))
                <p
                    style="margin: 24px 0 12px; font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.08em;">
                    Attachments</p>
                @foreach ($reply->attachments as $file)
                    <p style="margin: 6px 0; font-size: 13px;"><a href="{{ $file->url }}"
                            style="color: #003D99; text-decoration: none;">📎 {{ $file->name }}</a></p>
                @endforeach
            @endif

            <!-- CTA Button -->
            <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin: 36px 0;">
                <tr>
                    <td align="center">
                        <a href="{{ $support_ticket->admin_url }}" target="_blank" rel="noopener"
                            style="display: inline-block; padding: 14px 36px; background-color: #003D99; color: #ffffff !important; text-decoration: none; border-radius: 8px; font-weight: 700; font-size: 15px; line-height: 20px; letter-spacing: -0.01em;">
                            View Ticket
                        </a>
                    </td>
                </tr>
            </table>

            <!-- URL Fallback Block -->
            <table width="100%" cellpadding="0" cellspacing="0" role="presentation"
                style="margin-top: 24px; border-top: 1px solid #e2e8f0;">
                <tr>
                    <td style="padding: 20px 0 0;">
                        <p style="margin: 0; font-size: 12px; line-height: 18px; color: #94a3b8;">
                            If you're having trouble clicking the button, copy and paste this link:
                            <span
                                style="display: block; margin-top: 6px; word-break: break-all; color: #003D99;">{{ $support_ticket->admin_url }}</span>
                        </p>
                    </td>
                </tr>
            </table>

            <!-- Info Panel -->
            <table width="100%" cellpadding="0" cellspacing="0" role="presentation"
                style="background-color: #f0f9ff; border: 1px solid #bae6fd; border-radius: 8px; margin: 24px 0;">
                <tr>
                    <td style="padding: 12px 16px;">
                        <p style="margin: 0; font-size: 12px; line-height: 18px; color: #0c4a6e;">ℹ️ Please do not
                            respond to this email; any response should be made using the admin portal.</p>
                    </td>
                </tr>
            </table>

            <p style="margin: 28px 0 0; font-size: 15px; line-height: 24px; color: #334155;">
                Regards,<br>
                <strong style="color: #0f172a;">{{ $app->name }}</strong>
            </p>
        </td>
    </tr>
</table>
