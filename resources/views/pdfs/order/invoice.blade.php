<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice</title>
</head>
<body style="font-family: Arial, sans-serif;">

    @if (!empty($orders))
        @foreach ($orders as $index => $order)
            <div>

                <!-- Header: Store Details -->
                <table style="width: 100%; margin-bottom: 1rem;">
                    <tr>
                        <!-- QR Code & Store Name -->
                        <td>
                            <table>
                                <tr>
                                    <!-- QR Code -->
                                    @if (!empty($store['qr_code_file_path']))
                                        @php
                                            $imageData = file_get_contents($store['qr_code_file_path']);
                                            $imageType = pathinfo($store['qr_code_file_path'], PATHINFO_EXTENSION);
                                            $base64Image = 'data:image/' . $imageType . ';base64,' . base64_encode($imageData);
                                        @endphp
                                        <td style="padding-right: 12px;">
                                            <img src="{{ $base64Image }}" alt="Store QR Code" style="width: 64px; height: 64px; border-radius: 8px;">
                                        </td>
                                    @endif

                                    <!-- Logo -->
                                    @if (!empty($store['logo']))
                                        @php
                                            $imageData = file_get_contents($store['logo']['file_path']);
                                            $imageType = pathinfo($store['logo']['file_path'], PATHINFO_EXTENSION);
                                            $base64Image = 'data:image/' . $imageType . ';base64,' . base64_encode($imageData);
                                        @endphp
                                        <td style="padding-right: 12px;">
                                            <img src="{{ $base64Image }}" alt="Store Logo" style="width: 64px; height: 64px; border-radius: 8px;">
                                        </td>
                                    @endif

                                    <!-- Store Name & Link -->
                                    <td style="vertical-align: middle;">
                                        <p style="font-size: 20px; font-weight: bold; margin: 0;">{{ $store['name'] ?? 'Store' }}</p>
                                        <p style="font-size: 14px; color: #4b5563; margin: 0;">{{ $store['web_link'] ?? '#' }}</p>
                                    </td>
                                </tr>
                            </table>
                        </td>

                        <!-- Invoice Title -->
                        <td style="width: 30%; text-align: right; font-size: 32px; font-weight: bold; text-transform: uppercase; vertical-align: middle;">
                            Invoice
                        </td>
                    </tr>
                </table>

                <!-- Store Contact & Customer Details -->
                <table style="width: 100%; margin-bottom: 1rem;">
                    <tr>
                        <!-- Customer -->
                        <td style="width: 50%; vertical-align: top;">
                            <p style="font-weight: bold; margin: 4px;">Customer:</p>
                            @if (!empty($order['customer_name']))
                                <p style="color: #4b5563; margin: 4px;">{{ $order['customer_name'] }}</p>
                            @endif
                            @if (!empty($order['customer_mobile_number']) && isset($order['customer_mobile_number']['international']))
                                <p style="color: #4b5563; margin: 4px;">{{ $order['customer_mobile_number']['international'] }}</p>
                            @endif
                            @if (!empty($order['customer_email']))
                                <p style="color: #4b5563; margin: 4px;">{{ $order['customer_email'] }}</p>
                            @endif
                        </td>

                        <!-- Store Contact -->
                        <td style="width: 50%; vertical-align: top;">
                            <p style="font-weight: bold; margin: 4px;">Store:</p>
                            @if (!empty($store['email']))
                                <p style="color: #4b5563; margin: 4px;">Email: {{ $store['email'] }}</p>
                            @endif
                            @if (!empty($store['whatsapp_mobile_number']['international']))
                                <p style="color: #4b5563; margin: 4px;">WhatsApp: {{ $store['whatsapp_mobile_number']['international'] }}</p>
                            @endif
                            @if (!empty($store['ussd_mobile_number']['international']))
                                <p style="color: #4b5563; margin: 4px;">USSD: {{ $store['ussd_mobile_number']['international'] }}</p>
                            @endif
                        </td>
                    </tr>
                </table>

                <!-- Invoice Details -->
                <table style="width: 100%; margin-bottom: 1rem; border-collapse: collapse;">
                    <tr>
                        <td style="padding: 0; margin: 0;">
                            <span style="margin: 0; font-weight: bold;">Order No:</span>
                            <span style="margin: 0;">#{{ $order['number'] ?? 'N/A' }}</span>
                        </td>
                        <td style="padding: 0; margin: 0;">
                            <span style="margin: 0; font-weight: bold;">Status:</span>
                            <span style="margin: 0;">{{ ucwords($order['status']) }}</span>
                        </td>
                        <td style="padding: 0; margin: 0;">
                            <span style="margin: 0; font-weight: bold;">Payment Status:</span>
                            <span style="margin: 0;">{{ ucwords($order['payment_status']) }}</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding-top: 8px;">
                            <span style="margin: 0; font-weight: bold;">Date:</span>
                            <span style="margin: 0;">{{ !empty($order['created_at']) ? date('d M Y', strtotime($order['created_at'])) : 'N/A' }}</span>
                        </td>
                        <td colspan="2" style="padding-top: 8px;">
                            @if (!empty($order['delivery_method_name']))
                                <span style="margin: 0; font-weight: bold;">Delivery Method:</span>
                                <span style="margin: 0;">{{ ucwords($order['delivery_method_name']) }}</span>
                            @endif
                        </td>
                    </tr>
                </table>

                <!-- Order Products -->
                @if (!empty($order['order_products']))
                    <table style="width: 100%; border-collapse: collapse; font-size: 14px; margin-bottom: 0; border: 1px solid #d1d5db;">
                        <thead>
                            <tr style="font-size: 16px;background: #f3f4f6;">
                                <th style="border: 1px solid #d1d5db; padding: 8px; text-align: left;">Item</th>
                                <th style="border: 1px solid #d1d5db; padding: 8px; text-align: left;">Quantity</th>
                                <th style="border: 1px solid #d1d5db; padding: 8px; text-align: left;">Unit Price</th>
                                <th style="border: 1px solid #d1d5db; padding: 8px; text-align: left;">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($order['order_products'] as $product)
                                <tr>
                                    <td style="border: 1px solid #d1d5db; padding: 8px;">{{ $product['name'] ?? 'N/A' }}</td>
                                    <td style="border: 1px solid #d1d5db; padding: 8px;">{{ $product['quantity'] ?? 0 }}</td>
                                    <td style="border: 1px solid #d1d5db; padding: 8px;">{{ $product['unit_price']['amountWithCurrency'] ?? 'N/A' }}</td>
                                    <td style="border: 1px solid #d1d5db; padding: 8px;">{{ $product['grand_total']['amountWithCurrency'] ?? 'N/A' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif

                <!-- Order Totals -->
                <table style="width: 100%; margin-bottom: 2rem;">
                    <tr>
                        <td style="width: 50%;"></td>
                        <td style="width: 50%;">

                            <table style="width: 100%; margin-top: 16px;">

                                <!-- Subtotal -->
                                <tr>
                                    <td style="text-align: right; padding-bottom: 8px; border-bottom: 1px dashed #d1d5db;">
                                        <span style="color: #374151;">Subtotal:</span>
                                    </td>
                                    <td style="text-align: right; padding-bottom: 8px; border-bottom: 1px dashed #d1d5db;">
                                        <span style="color: #374151;">{{ $order['subtotal']['amountWithCurrency'] ?? 'N/A' }}</span>
                                    </td>
                                </tr>

                                <!-- Discounts (if any) -->
                                @if (!empty($order['discount_total']['amount']) && $order['discount_total']['amount'] > 0)
                                    <tr>
                                        <td style="text-align: right; padding-bottom: 8px; color: #6b7280; border-bottom: 1px dashed #d1d5db;">
                                            <span>Discount:</span>
                                        </td>
                                        <td style="text-align: right; padding-bottom: 8px; color: #6b7280; border-bottom: 1px dashed #d1d5db;">
                                            <span>{{ $order['discount_total']['amountWithCurrency'] ?? 'N/A' }}</span>
                                        </td>
                                    </tr>
                                @endif

                                <!-- Tax -->
                                <tr>
                                    <td style="text-align: right; padding-bottom: 8px; border-bottom: 1px dashed #d1d5db;">
                                        <span style="color: #374151;">Tax ({{ $order['vat_rate'] ?? '0%' }}):</span>
                                    </td>
                                    <td style="text-align: right; padding-bottom: 8px; border-bottom: 1px dashed #d1d5db;">
                                        <span>{{ $order['vat_amount']['amountWithCurrency'] ?? 'N/A' }}</span>
                                    </td>
                                </tr>

                                <!-- Additional Fees (if any) -->
                                @if (!empty($order['fee_total']['amount']) && $order['fee_total']['amount'] > 0)
                                    <tr>
                                        <td style="text-align: right; padding-bottom: 8px; border-bottom: 1px dashed #d1d5db;">
                                            <span>Additional Fees:</span>
                                        </td>
                                        <td style="text-align: right; padding-bottom: 8px; border-bottom: 1px dashed #d1d5db;">
                                            <span>{{ $order['fee_total']['amountWithCurrency'] ?? 'N/A' }}</span>
                                        </td>
                                    </tr>
                                @endif

                                <!-- Grand Total -->
                                <tr>
                                    <td style="text-align: right; padding-top: 12px; padding-bottom: 12px; font-size: 16px; font-weight: bold; border-bottom: 1px dashed #d1d5db;">
                                        <span>Grand Total:</span>
                                    </td>
                                    <td style="text-align: right; padding-top: 12px; padding-bottom: 12px; font-size: 16px; font-weight: bold; border-bottom: 1px dashed #d1d5db;">
                                        <span>{{ $order['grand_total']['amountWithCurrency'] ?? 'N/A' }}</span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>

                <p style="margin-top: 24px; text-align: center; color: #6b7280;">Thank you!</p>

            </div>

            <!-- Page Break for PDF -->
            @if ($index < count($orders) - 1)
                <div style="page-break-after: always;"></div>
            @endif
        @endforeach
    @endif

</body>
</html>
