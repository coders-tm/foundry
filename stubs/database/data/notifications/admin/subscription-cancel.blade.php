<table width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation">
    <tr>
        <td style="font-size: 15px; line-height: 24px; color: #334155;">
            <!-- Section Label -->
            <p
                style="margin: 0 0 4px; font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.08em;">
                ⏹️ Subscription Cancelled</p>

            <p style="margin: 0 0 20px; font-size: 16px; line-height: 26px; color: #334155;">
                Hello,
            </p>

            <p style="margin: 0 0 28px; font-size: 15px; line-height: 24px; color: #334155;">A member's subscription has
                been <strong>cancelled</strong>. Summary below:</p>

            <!-- Section Header -->
            <p
                style="margin: 32px 0 12px; font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.08em;">
                Subscription Summary</p>

            <!-- Data Card -->
            <table width="100%" cellpadding="0" cellspacing="0" role="presentation"
                style="width: 100%; border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 24px; background-color: #f8fafc; overflow: hidden;">
                <tbody>
                    <tr>
                        <td
                            style="padding: 13px 18px; border-bottom: 1px solid #e2e8f0; font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.06em; width: 38%;">
                            Name</td>
                        <td
                            style="padding: 13px 18px; border-bottom: 1px solid #e2e8f0; font-size: 14px; color: #1e293b; text-align: right; font-weight: 500;">
                            {{ $user->name }}</td>
                    </tr>
                    <tr>
                        <td
                            style="padding: 13px 18px; border-bottom: 1px solid #e2e8f0; font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.06em; width: 38%;">
                            Email</td>
                        <td
                            style="padding: 13px 18px; border-bottom: 1px solid #e2e8f0; font-size: 14px; color: #1e293b; text-align: right; font-weight: 500;">
                            {{ $user->email }}</td>
                    </tr>
                    @if (!empty($user->phone_number))
                        <tr>
                            <td
                                style="padding: 13px 18px; border-bottom: 1px solid #e2e8f0; font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.06em; width: 38%;">
                                Phone</td>
                            <td
                                style="padding: 13px 18px; border-bottom: 1px solid #e2e8f0; font-size: 14px; color: #1e293b; text-align: right; font-weight: 500;">
                                {{ $user->phone_number }}</td>
                        </tr>
                    @endif
                    <tr>
                        <td
                            style="padding: 13px 18px; border-bottom: 1px solid #e2e8f0; font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.06em; width: 38%;">
                            Plan</td>
                        <td
                            style="padding: 13px 18px; border-bottom: 1px solid #e2e8f0; font-size: 14px; color: #1e293b; text-align: right; font-weight: 500;">
                            {{ $plan->label ?? 'N/A' }}</td>
                    </tr>
                    <tr>
                        <td
                            style="padding: 13px 18px; border-bottom: 1px solid #e2e8f0; font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.06em; width: 38%;">
                            Price</td>
                        <td
                            style="padding: 13px 18px; border-bottom: 1px solid #e2e8f0; font-size: 14px; color: #1e293b; text-align: right; font-weight: 500;">
                            {{ $plan->price ?? '' }}</td>
                    </tr>
                    <tr>
                        <td
                            style="padding: 13px 18px; font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.06em; width: 38%;">
                            Ends At</td>
                        <td
                            style="padding: 13px 18px; font-size: 14px; font-weight: 700; color: #dc2626; text-align: right;">
                            {{ $expires_at ?: $ends_at }}</td>
                    </tr>
                </tbody>
            </table>

            <!-- Warning Panel -->
            <table width="100%" cellpadding="0" cellspacing="0" role="presentation"
                style="background-color: #fef9c3; border: 1px solid #fde68a; border-radius: 8px; margin: 24px 0;">
                <tr>
                    <td style="padding: 16px 20px;">
                        <p style="margin: 0; font-size: 14px; line-height: 22px; color: #a16207;">⚠️ Please update
                            internal records. If the member returns, ensure a smooth reactivation experience.</p>
                    </td>
                </tr>
            </table>

            <!-- Info Note -->
            <table width="100%" cellpadding="0" cellspacing="0" role="presentation"
                style="background-color: #f0f9ff; border: 1px solid #bae6fd; border-radius: 8px; margin: 24px 0;">
                <tr>
                    <td style="padding: 12px 16px;">
                        <p style="margin: 0; font-size: 12px; line-height: 18px; color: #0c4a6e;">ℹ️ Cancellation date
                            reflects the recorded subscription end. If this was accidental, initiate a manual
                            reactivation.</p>
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
