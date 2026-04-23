<table width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation">
    <tr>
        <td style="font-size: 15px; line-height: 24px; color: #334155;">
            <!-- Section Label -->
            <p
                style="margin: 0 0 4px; font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.08em;">
                💰 Refund Processed</p>

            <p style="margin: 0 0 20px; font-size: 16px; line-height: 26px; color: #334155;">
                Hi <strong style="color: #0f172a;">Admin</strong>,
            </p>

            <p style="margin: 0 0 28px; font-size: 15px; line-height: 24px; color: #334155;">A refund has been processed
                for order <strong>{{ $order->number }}</strong>.</p>

            <!-- Section Header -->
            <p
                style="margin: 32px 0 12px; font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.08em;">
                Order Information</p>

            <!-- Data Card -->
            <table width="100%" cellpadding="0" cellspacing="0" role="presentation"
                style="width: 100%; border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 24px; background-color: #f8fafc; overflow: hidden;">
                <tbody>
                    <tr>
                        <td
                            style="padding: 13px 18px; border-bottom: 1px solid #e2e8f0; font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.06em; width: 38%;">
                            Order Number</td>
                        <td
                            style="padding: 13px 18px; border-bottom: 1px solid #e2e8f0; font-size: 14px; color: #1e293b; text-align: right; font-weight: 500;">
                            {{ $order->number }}</td>
                    </tr>
                    <tr>
                        <td
                            style="padding: 13px 18px; border-bottom: 1px solid #e2e8f0; font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.06em; width: 38%;">
                            Order Date</td>
                        <td
                            style="padding: 13px 18px; border-bottom: 1px solid #e2e8f0; font-size: 14px; color: #1e293b; text-align: right; font-weight: 500;">
                            {{ $order->date }}</td>
                    </tr>
                    <tr>
                        <td
                            style="padding: 13px 18px; border-bottom: 1px solid #e2e8f0; font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.06em; width: 38%;">
                            Customer Name</td>
                        <td
                            style="padding: 13px 18px; border-bottom: 1px solid #e2e8f0; font-size: 14px; color: #1e293b; text-align: right; font-weight: 500;">
                            {{ $order->customer->name ?? 'N/A' }}</td>
                    </tr>
                    <tr>
                        <td
                            style="padding: 13px 18px; font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.06em; width: 38%;">
                            Order Total</td>
                        <td
                            style="padding: 13px 18px; font-size: 14px; font-weight: 700; color: #0f172a; text-align: right;">
                            {{ $order->total }}</td>
                    </tr>
                </tbody>
            </table>

            <!-- Refund Details Section -->
            <p
                style="margin: 32px 0 12px; font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.08em;">
                Refund Details</p>

            <!-- Refund Data Card -->
            <table width="100%" cellpadding="0" cellspacing="0" role="presentation"
                style="width: 100%; border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 24px; background-color: #f8fafc; overflow: hidden;">
                <tbody>
                    <tr>
                        <td
                            style="padding: 13px 18px; border-bottom: 1px solid #e2e8f0; font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.06em; width: 38%;">
                            Refund Amount</td>
                        <td
                            style="padding: 13px 18px; border-bottom: 1px solid #e2e8f0; font-size: 14px; font-weight: 700; color: #dc2626; text-align: right;">
                            {{ $refund->amount ?? '' }}</td>
                    </tr>
                    <tr>
                        <td
                            style="padding: 13px 18px; border-bottom: 1px solid #e2e8f0; font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.06em; width: 38%;">
                            Status</td>
                        <td
                            style="padding: 13px 18px; border-bottom: 1px solid #e2e8f0; font-size: 14px; color: #1e293b; text-align: right; font-weight: 500;">
                            {{ $refund->status ?? '' }}</td>
                    </tr>
                    <tr>
                        <td
                            style="padding: 13px 18px; font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.06em; width: 38%;">
                            Payment Method</td>
                        <td
                            style="padding: 13px 18px; font-size: 14px; color: #1e293b; text-align: right; font-weight: 500;">
                            {{ $refund->payment_method->name ?? 'N/A' }}</td>
                    </tr>
                </tbody>
            </table>

            @if (!empty($refund->reason))
                <!-- Refund Reason Panel -->
                <p
                    style="margin: 24px 0 8px; font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.08em;">
                    Reason</p>
                <table width="100%" cellpadding="0" cellspacing="0" role="presentation"
                    style="background-color: #f0f9ff; border: 1px solid #bae6fd; border-radius: 8px; margin-bottom: 24px;">
                    <tr>
                        <td style="padding: 16px 20px;">
                            <p style="margin: 0; font-size: 14px; line-height: 22px; color: #0c4a6e;">
                                {!! nl2br(e($refund->reason)) !!}</p>
                        </td>
                    </tr>
                </table>
            @endif

            <!-- CTA Button -->
            <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin: 36px 0;">
                <tr>
                    <td align="center">
                        <a href="{{ admin_url($order->url) }}" target="_blank" rel="noopener"
                            style="display: inline-block; padding: 14px 36px; background-color: #003D99; color: #ffffff !important; text-decoration: none; border-radius: 8px; font-weight: 700; font-size: 15px; line-height: 20px; letter-spacing: -0.01em;">
                            View Order Details
                        </a>
                    </td>
                </tr>
            </table>

            <!-- Info Note -->
            <p style="margin: 24px 0 0; font-size: 14px; line-height: 22px; color: #334155;">The customer has been
                notified of this refund. Please monitor the refund processing in your payment gateway.</p>

            <!-- URL Fallback Block -->
            <table width="100%" cellpadding="0" cellspacing="0" role="presentation"
                style="margin-top: 24px; border-top: 1px solid #e2e8f0;">
                <tr>
                    <td style="padding: 20px 0 0;">
                        <p style="margin: 0; font-size: 12px; line-height: 18px; color: #94a3b8;">
                            If you're having trouble clicking the button, copy and paste this link:
                            <span
                                style="display: block; margin-top: 6px; word-break: break-all; color: #003D99;">{{ admin_url($order->url) }}</span>
                        </p>
                    </td>
                </tr>
            </table>

            <p style="margin: 28px 0 0; font-size: 15px; line-height: 24px; color: #334155;">
                Regards,<br>
                <strong style="color: #0f172a;">{{ $app->name }} System</strong>
            </p>
        </td>
    </tr>
</table>
