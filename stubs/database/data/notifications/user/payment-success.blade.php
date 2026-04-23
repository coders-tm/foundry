<table width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation">
    <tr>
        <td style="font-size: 15px; line-height: 24px; color: #334155;">
            <!-- Section Label -->
            <p
                style="margin: 0 0 4px; font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.08em;">
                ✅ Payment Confirmed</p>

            <p style="margin: 0 0 20px; font-size: 16px; line-height: 26px; color: #334155;">
                Hi <strong style="color: #0f172a;">{{ $order->customer->first_name ?? 'there' }}</strong>,
            </p>

            <p style="margin: 0 0 28px; font-size: 15px; line-height: 24px; color: #334155;">Great news! Your payment for
                order <strong style="color: #0f172a;">{{ $order->number }}</strong> has been successfully processed.</p>

            <!-- Section Header -->
            <p
                style="margin: 32px 0 12px; font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.08em;">
                Payment Details</p>

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
                            Payment Date</td>
                        <td
                            style="padding: 13px 18px; border-bottom: 1px solid #e2e8f0; font-size: 14px; color: #1e293b; text-align: right; font-weight: 500;">
                            {{ now()->format('M d, Y') }}</td>
                    </tr>
                    @if (!empty($order->payments))
                        <tr>
                            <td
                                style="padding: 13px 18px; border-bottom: 1px solid #e2e8f0; font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.06em; width: 38%;">
                                Payment Method</td>
                            <td
                                style="padding: 13px 18px; border-bottom: 1px solid #e2e8f0; font-size: 14px; color: #1e293b; text-align: right;">
                                {{ $order->payments[0]->payment_method->name ?? 'N/A' }}</td>
                        </tr>
                        @if ($order->payments[0]->transaction_id)
                            <tr>
                                <td
                                    style="padding: 13px 18px; border-bottom: 1px solid #e2e8f0; font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.06em; width: 38%;">
                                    Transaction ID</td>
                                <td
                                    style="padding: 13px 18px; border-bottom: 1px solid #e2e8f0; font-size: 14px; color: #1e293b; text-align: right;">
                                    {{ $order->payments[0]->transaction_id }}</td>
                            </tr>
                        @endif
                    @endif
                    <tr>
                        <td
                            style="padding: 13px 18px; border-bottom: 1px solid #e2e8f0; font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.06em; width: 38%;">
                            Amount Paid</td>
                        <td
                            style="padding: 13px 18px; border-bottom: 1px solid #e2e8f0; font-size: 14px; font-weight: 700; color: #0f172a; text-align: right;">
                            {{ $order->total }}</td>
                    </tr>
                    <tr>
                        <td
                            style="padding: 13px 18px; font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.06em; width: 38%;">
                            Status</td>
                        <td
                            style="padding: 13px 18px; font-size: 14px; color: #16a34a; text-align: right; font-weight: 600;">
                            {{ $order->payment_status }}</td>
                    </tr>
                </tbody>
            </table>

            @if (count($order->payments ?? []) > 1)
                <!-- Section Header -->
                <p
                    style="margin: 32px 0 12px; font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.08em;">
                    Payment History</p>
                <table width="100%" cellpadding="0" cellspacing="0" role="presentation"
                    style="width: 100%; border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 24px; overflow: hidden;">
                    <thead>
                        <tr style="background-color: #f1f5f9;">
                            <th align="left"
                                style="padding: 12px 16px; font-size: 11px; font-weight: 600; color: #64748b; text-transform: uppercase;">
                                Date</th>
                            <th align="left"
                                style="padding: 12px 16px; font-size: 11px; font-weight: 600; color: #64748b; text-transform: uppercase;">
                                Method</th>
                            <th align="right"
                                style="padding: 12px 16px; font-size: 11px; font-weight: 600; color: #64748b; text-transform: uppercase;">
                                Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($order->payments as $payment)
                            <tr>
                                <td
                                    style="padding: 12px 16px; border-bottom: 1px solid #f1f5f9; font-size: 13px; color: #334155;">
                                    {{ $payment->date }}</td>
                                <td
                                    style="padding: 12px 16px; border-bottom: 1px solid #f1f5f9; font-size: 13px; color: #334155;">
                                    {{ $payment->payment_method->name ?? 'N/A' }}</td>
                                <td align="right"
                                    style="padding: 12px 16px; border-bottom: 1px solid #f1f5f9; font-size: 13px; font-weight: 600; color: #0f172a;">
                                    {{ $payment->amount }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            <!-- Success Panel -->
            <table width="100%" cellpadding="0" cellspacing="0" role="presentation"
                style="background-color: #f0fdf4; border: 1px solid #dcfce7; border-radius: 8px; margin-bottom: 24px;">
                <tr>
                    <td style="padding: 16px 20px;">
                        <p style="margin: 0; font-size: 14px; line-height: 22px; color: #166534;">✅ Payment confirmed!
                            Your order is being processed and will be shipped soon.</p>
                    </td>
                </tr>
            </table>

            <!-- CTA Button -->
            <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin: 36px 0;">
                <tr>
                    <td align="center">
                        <a href="{{ app_url($order->url) }}" target="_blank" rel="noopener"
                            style="display: inline-block; padding: 14px 36px; background-color: #003D99; color: #ffffff !important; text-decoration: none; border-radius: 8px; font-weight: 700; font-size: 15px; line-height: 20px; letter-spacing: -0.01em;">
                            View Order Details
                        </a>
                    </td>
                </tr>
            </table>

            <!-- Info Panel -->
            <table width="100%" cellpadding="0" cellspacing="0" role="presentation"
                style="background-color: #f0f9ff; border: 1px solid #bae6fd; border-radius: 8px; margin-bottom: 24px;">
                <tr>
                    <td style="padding: 16px 20px;">
                        <p style="margin: 0; font-size: 14px; line-height: 22px; color: #0c4a6e;">If you have any
                            questions about your order, please contact us at <a href="mailto:{{ $support->email }}"
                                style="color: #003D99; text-decoration: none;">{{ $support->email }}</a>.</p>
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
                                style="display: block; margin-top: 6px; word-break: break-all; color: #003D99;">{{ app_url($order->url) }}</span>
                        </p>
                    </td>
                </tr>
            </table>

            <p style="margin: 28px 0 0; font-size: 15px; line-height: 24px; color: #334155;">
                Thank you for your business!<br>
                <strong style="color: #0f172a;">{{ $app->name }} Team</strong>
            </p>
        </td>
    </tr>
</table>
