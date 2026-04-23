<table width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation">
    <tr>
        <td style="font-size: 15px; line-height: 24px; color: #334155;">
            <!-- Section Label -->
            <p
                style="margin: 0 0 4px; font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.08em;">
                🎉 Welcome Aboard</p>

            <p style="margin: 0 0 20px; font-size: 16px; line-height: 26px; color: #334155;">
                Hi <strong style="color: #0f172a;">{{ $user->first_name }}</strong>,
            </p>

            <p style="margin: 0 0 28px; font-size: 15px; line-height: 24px; color: #334155;">Welcome to
                {{ $app->name }}! We're thrilled to have you as a valued member of our community.</p>

            @if ($subscription)
                <!-- Section Header -->
                <p
                    style="margin: 32px 0 12px; font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.08em;">
                    Your Subscription Details</p>

                <!-- Data Card -->
                <table width="100%" cellpadding="0" cellspacing="0" role="presentation"
                    style="width: 100%; border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 24px; background-color: #f8fafc; overflow: hidden;">
                    <tbody>
                        <tr>
                            <td
                                style="padding: 13px 18px; border-bottom: 1px solid #e2e8f0; font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.06em; width: 38%;">
                                Plan</td>
                            <td
                                style="padding: 13px 18px; border-bottom: 1px solid #e2e8f0; font-size: 14px; color: #1e293b; text-align: right; font-weight: 500;">
                                {{ $subscription->plan->label }}</td>
                        </tr>
                        <tr>
                            <td
                                style="padding: 13px 18px; border-bottom: 1px solid #e2e8f0; font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.06em; width: 38%;">
                                Price</td>
                            <td
                                style="padding: 13px 18px; border-bottom: 1px solid #e2e8f0; font-size: 14px; color: #1e293b; text-align: right; font-weight: 500;">
                                {{ $subscription->plan->price }}</td>
                        </tr>
                        <tr>
                            <td
                                style="padding: 13px 18px; border-bottom: 1px solid #e2e8f0; font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.06em; width: 38%;">
                                Billing Cycle</td>
                            <td
                                style="padding: 13px 18px; border-bottom: 1px solid #e2e8f0; font-size: 14px; color: #1e293b; text-align: right; font-weight: 500;">
                                {{ $subscription->billing_cycle }}</td>
                        </tr>
                        @if (!empty($subscription->next_billing_date))
                            <tr>
                                <td
                                    style="padding: 13px 18px; font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.06em; width: 38%;">
                                    Next Billing</td>
                                <td
                                    style="padding: 13px 18px; font-size: 14px; color: #1e293b; text-align: right; font-weight: 500;">
                                    {{ $subscription->next_billing_date }}</td>
                            </tr>
                        @endif
                    </tbody>
                </table>

                <p style="margin: 24px 0; font-size: 14px; line-height: 22px; color: #334155;">With this plan, you'll
                    have access to exciting features, exclusive content, and premium benefits. We're confident you'll
                    find great value throughout your subscription.</p>
            @else
                <p style="margin: 24px 0; font-size: 14px; line-height: 22px; color: #334155;">Your account is now
                    active and ready to use. Explore our features and discover what we have to offer!</p>
            @endif

            <!-- Success Panel -->
            <table width="100%" cellpadding="0" cellspacing="0" role="presentation"
                style="background-color: #f0fdf4; border: 1px solid #dcfce7; border-radius: 8px; margin-bottom: 24px;">
                <tr>
                    <td style="padding: 16px 20px;">
                        <p style="margin: 0; font-size: 14px; line-height: 22px; color: #166534;">🚀 Welcome aboard! We
                            can't wait to see what you'll achieve with us.</p>
                    </td>
                </tr>
            </table>

            <!-- Info Panel -->
            <table width="100%" cellpadding="0" cellspacing="0" role="presentation"
                style="background-color: #f0f9ff; border: 1px solid #bae6fd; border-radius: 8px; margin-bottom: 24px;">
                <tr>
                    <td style="padding: 16px 20px;">
                        <p style="margin: 0; font-size: 14px; line-height: 22px; color: #0c4a6e;">If you have any
                            questions or need assistance, our support team is here to help at <a
                                href="mailto:{{ $app->email }}"
                                style="color: #003D99; text-decoration: none;">{{ $app->email }}</a>.</p>
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
