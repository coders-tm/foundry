<table width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation">
    <tr>
        <td style="font-size: 15px; line-height: 24px; color: #334155;">
            <!-- Section Label -->
            <p
                style="margin: 0 0 4px; font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.08em;">
                📋 Invoice Ready</p>

            <p style="margin: 0 0 20px; font-size: 16px; line-height: 26px; color: #334155;">
                Hi <strong style="color: #0f172a;">{{ $order->customer->first_name ?? 'there' }}</strong>,
            </p>

            <p style="margin: 0 0 28px; font-size: 15px; line-height: 24px; color: #334155;">Your invoice is ready for
                download. Here are the key details:</p>

            <!-- Section Header -->
            <p
                style="margin: 32px 0 12px; font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.08em;">
                Invoice Details</p>

            <!-- Data Card -->
            <table width="100%" cellpadding="0" cellspacing="0" role="presentation"
                style="width: 100%; border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 24px; background-color: #f8fafc; overflow: hidden;">
                <tr>
                    <td
                        style="padding: 13px 18px; border-bottom: 1px solid #e2e8f0; font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.06em; width: 38%;">
                        Invoice Number</td>
                    <td
                        style="padding: 13px 18px; border-bottom: 1px solid #e2e8f0; font-size: 14px; color: #1e293b; text-align: right; font-weight: 500;">
                        {{ $order->number }}</td>
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
                @if ($order->has_due && $order->due_amount != $order->total)
                    <tr>
                        <td
                            style="padding: 13px 18px; border-bottom: 1px solid #e2e8f0; font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.06em; width: 38%;">
                            Amount Paid</td>
                        <td
                            style="padding: 13px 18px; border-bottom: 1px solid #e2e8f0; font-size: 14px; color: #16a34a; text-align: right; font-weight: 600;">
                            {{ $order->paid_total }}</td>
                    </tr>
                    <tr>
                        <td
                            style="padding: 13px 18px; border-bottom: 1px solid #e2e8f0; font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.06em; width: 38%;">
                            Balance Due</td>
                        <td
                            style="padding: 13px 18px; border-bottom: 1px solid #e2e8f0; font-size: 14px; color: #dc2626; text-align: right; font-weight: 600;">
                            {{ $order->due_amount }}</td>
                    </tr>
                @endif
                <tr>
                    <td
                        style="padding: 13px 18px; font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.06em; width: 38%;">
                        Total Amount</td>
                    <td
                        style="padding: 13px 18px; font-size: 14px; font-weight: 700; color: #0f172a; text-align: right;">
                        {{ $order->total }}</td>
                </tr>
            </table>

            @if (count($order->payments ?? []) > 1)
                <!-- Section Header -->
                <p
                    style="margin: 32px 0 12px; font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.08em;">
                    Payment History</p>
                <table width="100%" cellpadding="0" cellspacing="0" role="presentation"
                    style="width: 100%; border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 24px; overflow: hidden;">
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
                </table>
            @endif

            <!-- Section Header -->
            <p
                style="margin: 32px 0 12px; font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.08em;">
                Invoice Summary</p>

            <table width="100%" cellpadding="0" cellspacing="0" role="presentation"
                style="width: 100%; border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 24px; overflow: hidden;">
                <tr style="background-color: #f1f5f9;">
                    <th align="left"
                        style="padding: 12px 16px; font-size: 11px; font-weight: 600; color: #64748b; text-transform: uppercase;">
                        Item</th>
                    <th align="center" width="60"
                        style="padding: 12px 16px; font-size: 11px; font-weight: 600; color: #64748b; text-transform: uppercase;">
                        Qty</th>
                    <th align="right" width="100"
                        style="padding: 12px 16px; font-size: 11px; font-weight: 600; color: #64748b; text-transform: uppercase;">
                        Total</th>
                </tr>
                @foreach ($order->items as $item)
                    <tr>
                        <td style="padding: 16px 16px; border-bottom: 1px solid #f1f5f9; vertical-align: top;">
                            <div style="font-weight: 600; color: #0f172a; font-size: 14px; line-height: 20px;">
                                {{ $item->title }}</div>
                            @if ($item->variant_title)
                                <div style="font-size: 12px; color: #64748b; margin-top: 4px;">
                                    {{ $item->variant_title }}</div>
                            @endif
                        </td>
                        <td align="center"
                            style="padding: 16px 16px; border-bottom: 1px solid #f1f5f9; font-size: 14px; color: #334155;">
                            {{ $item->quantity }}</td>
                        <td align="right"
                            style="padding: 16px 16px; border-bottom: 1px solid #f1f5f9; font-size: 14px; font-weight: 700; color: #0f172a;">
                            {{ $item->total }}</td>
                    </tr>
                @endforeach
                <tr style="background-color: #f8fafc;">
                    <td colspan="2" align="right"
                        style="padding: 12px 16px; font-size: 13px; color: #64748b; font-weight: 600; text-transform: uppercase;">
                        Subtotal</td>
                    <td align="right" style="padding: 12px 16px; font-size: 14px; color: #1e293b;">
                        {{ $order->sub_total }}</td>
                </tr>
                @if (!empty($order->discount_total))
                    <tr style="background-color: #f8fafc;">
                        <td colspan="2" align="right"
                            style="padding: 12px 16px; font-size: 13px; color: #64748b; font-weight: 600; text-transform: uppercase;">
                            Discount</td>
                        <td align="right"
                            style="padding: 12px 16px; font-size: 14px; color: #166534; font-weight: 600;">
                            -{{ $order->discount_total }}</td>
                    </tr>
                @endif
                @if (!empty($order->tax_total))
                    <tr style="background-color: #f8fafc;">
                        <td colspan="2" align="right"
                            style="padding: 12px 16px; font-size: 13px; color: #64748b; font-weight: 600; text-transform: uppercase;">
                            Tax</td>
                        <td align="right" style="padding: 12px 16px; font-size: 14px; color: #1e293b;">
                            {{ $order->tax_total }}</td>
                    </tr>
                @endif
                <tr style="background-color: #f8fafc;">
                    <td colspan="2" align="right"
                        style="padding: 20px 16px; font-size: 15px; color: #0f172a; font-weight: 700; text-transform: uppercase;">
                        Total</td>
                    <td align="right" style="padding: 20px 16px; font-size: 20px; font-weight: 800; color: #003D99;">
                        {{ $order->total }}</td>
                </tr>
            </table>

            <!-- CTA Button -->
            <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin: 36px 0;">
                <tr>
                    <td align="center">
                        <a href="{{ $order->payment_url }}" target="_blank" rel="noopener"
                            style="display: inline-block; padding: 14px 36px; background-color: #003D99; color: #ffffff !important; text-decoration: none; border-radius: 8px; font-weight: 700; font-size: 15px; line-height: 20px; letter-spacing: -0.01em;">
                            View Invoice Details
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
                                style="display: block; margin-top: 6px; word-break: break-all; color: #003D99;">{{ $order->payment_url }}</span>
                        </p>
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
