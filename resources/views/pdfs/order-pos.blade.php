<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <meta http-equiv="X-UA-Compatible" content="ie=edge" />
    <title>{{ $id }}</title>
    @include('includes.pdf-style')
    <style>
        body {
            font-size: 13px;
            font-family: Helvetica, sans-serif;
            margin: 0px;
        }

        @page {
            margin: 0px;
        }
    </style>
</head>

<body>
    <main>
        <table style="margin-bottom: 15px" cellspacing="0">
            <tr>
                <td class="text-center text-bold" style="font-size:24px; text-transform: uppercase;">
                    <img width="100px" src="{{ $logo ?? config('app.logo', asset('images/logo.png')) }}"
                        alt="{{ config('app.name') }}">
                </td>
            </tr>
            </tr>
            <tr>
            <tr>
                <td class="text-center" style="font-size: 15px; padding-top: 15px;">
                    --------------------- RETAIL INVOICE ---------------------
                </td>
            </tr>
        </table>
        <table style="margin-bottom: 20px" cellspacing="0">
            <tr>
                <td>Invoice No: {{ $id }}</td>
                <td>Customer: {{ $customer_name }}</td>
            </tr>
            <tr>
                <td>Date: <span class="text-uc">{{ $created_at }}</span></td>
                <td>Phone: {{ $phone_number ?? '0000000000' }}</td>
            </tr>
        </table>
        <table style="margin: 10px 0" cellspacing="0" class="table">
            <tbody>
                <tr>
                    <td class="dashed-bt text-bold">SL</td>
                    <td class="dashed-bt text-bold">ITEM</td>
                    <td class="dashed-bt text-bold text-center">PRICE</td>
                    <td class="dashed-bt text-bold text-center">QTY</td>
                    <td class="dashed-bt text-bold text-right">TOTAL</td>
                </tr>
                @foreach ($line_items as $index => $item)
                    <tr>
                        <td>
                            {{ $index + 1 }}
                        </td>
                        <td>
                            <div>
                                {{ $item->title }}
                                @if ($item->variant_title != 'Default')
                                    <br>
                                    {{ $item->variant_title }}
                                @endif
                            </div>
                        </td>
                        <td class="text-center">
                            {{ format_amount($item->price, $currency) }}
                        </td>
                        <td class="text-center">
                            {{ $item->quantity }}
                        </td>
                        <td class="text-right">
                            {{ format_amount($item->total, $currency) }}
                        </td>
                    </tr>
                @endforeach
                <tr>
                    <td colspan="2" class="dashed-top"></td>
                    <td colspan="2" class="dashed-top text-right">Subtotal:</td>
                    <td colspan="1" class="dashed-top text-right">{{ $sub_total }}</td>
                </tr>
                <tr>
                    <td colspan="2" class="text-right"></td>
                    <td colspan="2" class="text-right">Discount:</td>
                    <td colspan="1" class="text-right">-{{ $discount_total }}</td>
                </tr>
                <tr>
                    <td colspan="2" class="text-right"></td>
                    <td colspan="2" class="text-right">Tax:</td>
                    <td colspan="1" class="text-right">+{{ $tax_total }}</td>
                </tr>
                <tr>
                    <td colspan="2" class="text-right"></td>
                    <td colspan="2" class="text-right">Grand Total:</td>
                    <td colspan="1" class="text-right">{{ $grand_total }}</td>
                </tr>
                <tr>
                    <td colspan="2" class="text-right"></td>
                    <td colspan="2" class="text-right dashed-top">Cash Paid:</td>
                    <td colspan="1" class="text-right dashed-top">{{ $paid_total }}</td>
                </tr>
                <tr>
                    <td colspan="2" class="text-right"></td>
                    <td colspan="2" class="text-right">Due Amount:</td>
                    <td colspan="1" class="text-right">{{ $due_amount }}</td>
                </tr>
                <tr>
                    <td colspan="2" class="text-right"></td>
                    <td colspan="3" class="dashed-top"></td>
                </tr>
            </tbody>
        </table>
        @if (!empty($payments))
            <table style="margin: 15px 0 10px 0" cellspacing="0">
                <tr>
                    <td class="text-center" style="font-size: 15px; padding: 8px 0;">
                        -------------------- PAYMENT INFO --------------------
                    </td>
                </tr>
            </table>
            <table style="margin: 0" cellspacing="0" class="table">
                @foreach ($payments as $index => $payment)
                    <tr>
                        <td colspan="2" class="text-bold" style="padding: 5px 0 2px 0; font-size: 12px;">
                            {{ $payment['payment_method']['name'] ?? 'N/A' }}
                        </td>
                    </tr>
                    @if ($payment['transaction_id'])
                        <tr>
                            <td style="padding: 0 0 2px 0; font-size: 11px; width: 35%;">Transaction ID:</td>
                            <td style="padding: 0 0 2px 0; font-size: 11px;">{{ $payment['transaction_id'] }}</td>
                        </tr>
                    @endif
                    <tr>
                        <td style="padding: 0 0 2px 0; font-size: 11px;">Amount:</td>
                        <td class="text-bold" style="padding: 0 0 2px 0; font-size: 11px;">{{ $payment['amount'] }}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 0 0 5px 0; font-size: 11px;">Date:</td>
                        <td style="padding: 0 0 5px 0; font-size: 11px;">{{ $payment['date'] }}</td>
                    </tr>
                    @if (!$loop->last)
                        <tr>
                            <td colspan="2" style="border-bottom: 1px dashed #ccc; padding: 3px 0;"></td>
                        </tr>
                    @endif
                @endforeach
            </table>
            <table style="margin: 5px 0 0 0" cellspacing="0">
                <tr>
                    <td class="text-center" style="font-size: 15px; padding: 5px 0;">
                        ------------------------------------------------------
                    </td>
                </tr>
            </table>
        @endif
        <div class="text-center" style="margin: 20px 0">Thank you for shopping with us!</div>
    </main>
</body>

</html>
