<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <meta http-equiv="X-UA-Compatible" content="ie=edge" />
    <title>{{ $id }}</title>
    @include('includes.pdf-style')
</head>

<body>
    <main>
        <table style="margin-bottom: 20px" cellspacing="0">
            <tr>
                <td style="font-size: 23px; text-transform: uppercase; width: 70%">
                    <img width="100px" src="{{ $logo ?? config('app.logo', asset('images/logo.png')) }}"
                        alt="{{ $app_name ?? config('app.name') }}">
                </td>
                <td class="text-right" style="width: 30%">
                    <div>ORDER {{ $id }}</div>
                    <div>{{ $created_at }}</div>
                </td>
            </tr>
        </table>
        <table cellspacing="0" style="margin-bottom: 20px">
            <tr>
                <td style="width: 150px;">
                    <div class="text-bold" style="margin-bottom: 10px">BILLING ADDRESS</div>
                    @if ($billing_address)
                        <div>{{ $billing_address }}</div>
                    @else
                        <div class="text-grey">No billing info provided</div>
                    @endif
                </td>
                <td></td>
            </tr>
        </table>
        <table style="margin: 10px 0;" cellspacing="0" class="table">
            <tbody>
                <tr>
                    <td colspan="2" class="text-bold">ITEMS</td>
                    <td class="text-bold text-center">PRICE</td>
                    <td class="text-bold text-center">QTY</td>
                    <td class="text-bold text-right">TOTAL</td>
                </tr>
                @foreach ($line_items as $item)
                    <tr>
                        <td style="width: 40px">
                            @php
                                $thumbnail = $item->thumbnail ?? optional($item->product)->thumbnail;
                            @endphp
                            <img class="thumbnail" src="{{ $thumbnail->url ?? asset('images/placeholder.jpg') }}">
                        </td>
                        <td>
                            <p>
                                {{ $item->title }}
                                @if ($item->variant_title != 'Default')
                                    <br>
                                    {{ $item->variant_title }}
                                @endif
                                @if (isset($item->metadata['description']))
                                    <br>
                                    {{ $item->metadata['description'] }}
                                @endif
                            </p>
                        </td>
                        <td class="text-center">
                            {{ format_amount($item->price) }}
                        </td>
                        <td class="text-center">
                            {{ $item->quantity }}
                        </td>
                        <td class="text-right">
                            {{ format_amount($item->total) }}
                        </td>
                    </tr>
                @endforeach
                <tr>
                    <td colspan="3"></td>
                    <td colspan="1" class="border-top text-right">Subtotal:</td>
                    <td colspan="1" class="border-top text-right">{{ $sub_total }}</td>
                </tr>
                <tr>
                    <td colspan="3"></td>
                    <td colspan="1" class="text-right">Discount:</td>
                    <td colspan="1" class="text-right">-{{ $discount_total }}</td>
                </tr>
                <tr>
                    <td colspan="3"></td>
                    <td colspan="1" class="text-right">Tax:</td>
                    <td colspan="1" class="text-right">+{{ $tax_total }}</td>
                </tr>
                <tr>
                    <td colspan="3"></td>
                    <td colspan="1" class="border-top text-right">Grand Total:</td>
                    <td colspan="1" class="border-top text-right">{{ $grand_total }}</td>
                </tr>
                <tr>
                    <td colspan="3"></td>
                    <td colspan="1" class="text-right ">Cash Paid:</td>
                    <td colspan="1" class="text-right ">{{ $paid_total }}</td>
                </tr>
                <tr>
                    <td colspan="3"></td>
                    <td colspan="1" class="text-right">Due Amount:</td>
                    <td colspan="1" class="text-right">{{ $due_amount }}</td>
                </tr>
            </tbody>
        </table>
        @if (!empty($payments))
            <div style="margin-top: 30px; padding: 15px; background-color: #f9f9f9; border: 1px solid #e0e0e0;">
                <div
                    style="font-size: 14px; font-weight: bold; margin-bottom: 15px; padding-bottom: 8px; border-bottom: 2px solid #333;">
                    PAYMENT INFORMATION
                </div>
                @foreach ($payments as $payment)
                    <div
                        style="margin-bottom: {{ $loop->last ? '0' : '15px' }}; padding-bottom: {{ $loop->last ? '0' : '15px' }}; border-bottom: {{ $loop->last ? 'none' : '1px dashed #ccc' }};">
                        <table cellspacing="0" style="width: 100%;">
                            <tr>
                                <td style="width: 30%; padding: 3px 0; font-size: 11px; color: #666;">Payment Method:
                                </td>
                                <td style="padding: 3px 0; font-size: 12px; font-weight: bold;">
                                    {{ $payment['payment_method']['name'] ?? 'N/A' }}
                                </td>
                            </tr>
                            @if ($payment['transaction_id'])
                                <tr>
                                    <td style="padding: 3px 0; font-size: 11px; color: #666;">Transaction ID:</td>
                                    <td style="padding: 3px 0; font-size: 11px; font-family: monospace; color: #333;">
                                        {{ $payment['transaction_id'] }}
                                    </td>
                                </tr>
                            @endif
                            <tr>
                                <td style="padding: 3px 0; font-size: 11px; color: #666;">Amount Paid:</td>
                                <td style="padding: 3px 0; font-size: 13px; font-weight: bold; color: #2e7d32;">
                                    @if (!empty($payment['gateway_amount']))
                                        {{ $payment['gateway_amount'] }}
                                    @else
                                        {{ $payment['amount'] }}
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <td style="padding: 3px 0; font-size: 11px; color: #666;">Payment Date:</td>
                                <td style="padding: 3px 0; font-size: 11px; color: #333;">
                                    {{ $payment['date'] }}
                                </td>
                            </tr>
                            @if ($payment['status'])
                                <tr>
                                    <td style="padding: 3px 0; font-size: 11px; color: #666;">Status:</td>
                                    <td style="padding: 3px 0;">
                                        <span
                                            style="display: inline-block; padding: 2px 8px; font-size: 10px; font-weight: bold; text-transform: uppercase; background-color: #4caf50; color: white; border-radius: 3px;">
                                            {{ $payment['status'] }}
                                        </span>
                                    </td>
                                </tr>
                            @endif
                        </table>
                    </div>
                @endforeach
            </div>
        @endif
        <div style=" margin-bottom: 40px"></div>
        <div class="text-center" style="margin: 20px 0">Thank you for shopping with us!</div>
        <div class="text-center text-bold">{{ config('app.name') }}</div>
    </main>
</body>

</html>
