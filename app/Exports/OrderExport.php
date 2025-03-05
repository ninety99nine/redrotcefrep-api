<?php

namespace App\Exports;

use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;


class OrderExport implements FromCollection, WithHeadings, ShouldAutoSize, WithEvents
{
    protected $query;
    protected $exportMode;

    public function __construct($query)
    {
        $this->query = $query;
        $this->exportMode = request()->input('export_mode', 'by_orders');
    }

    public function collection()
    {
        switch ($this->exportMode) {
            case 'by_orders':
                return $this->exportByOrders();
            case 'by_products':
                return $this->exportByProducts();
            case 'by_products_blanking':
                return $this->exportByProductsBlanking();
            default:
                return collect();
        }
    }

    public function headings(): array
    {
        $headings = [
            'by_orders' => [
                'Order ID', 'Status', 'Payment Status', 'Grand Total', 'Delivery Date', 'Created Date', 'Order Products',
            ],
            'by_products' => [
                'Order ID', 'Status', 'Payment Status', 'Grand Total', 'Delivery Date', 'Created Date', 'Product Name', 'Quantity', 'Unit Price', 'Subtotal',
            ],
            'by_products_blanking' => [
                'Order ID', 'Status', 'Payment Status', 'Grand Total', 'Delivery Date', 'Created Date', 'Product Name', 'Quantity', 'Unit Price', 'Subtotal',
            ],
        ];

        return $headings[$this->exportMode] ?? [];
    }

    private function exportByOrders()
    {
        return $this->query->with(['orderProducts'])->get()->map(function ($order) {
            return [
                'Order ID' => $order->id,
                'Status' => $order->status,
                'Payment Status' => $order->payment_status,
                'Grand Total' => $order->grand_total->amountWithoutCurrency,
                'Delivery Date' => $order->delivery_date,
                'Created Date' => $order->created_at->format('Y-m-d'),
                'Order Products' => $order->orderProducts->map(function ($product) {
                    return $product->name . ' (Qty: ' . $product->quantity . ')';
                })->implode(', ')
            ];
        });
    }

    private function exportByProducts()
    {
        return $this->query->with(['orderProducts'])->get()->flatMap(function ($order) {
            return $order->orderProducts->map(function ($product) use ($order) {
                return [
                    'Order ID' => $order->id,
                    'Status' => $order->status,
                    'Payment Status' => $order->payment_status,
                    'Grand Total' => $order->grand_total->amountWithoutCurrency,
                    'Delivery Date' => $order->delivery_date,
                    'Created Date' => $order->created_at->format('Y-m-d'),
                    'Product Name' => $product->name,
                    'Quantity' => $product->quantity,
                    'Unit Price' => $product->unit_price->amountWithoutCurrency,
                    'Unit Subtotal' => $product->subtotal->amountWithoutCurrency
                ];
            });
        });
    }

    private function exportByProductsBlanking()
    {
        return $this->query->with(['orderProducts'])->get()->flatMap(function ($order) {
            $firstProduct = true;
            return $order->orderProducts->map(function ($product) use ($order, &$firstProduct) {
                $data = [
                    'Order ID' => $firstProduct ? $order->id : '',
                    'Status' => $firstProduct ? $order->status : '',
                    'Payment Status' => $firstProduct ? $order->payment_status : '',
                    'Grand Total' => $firstProduct ? $order->grand_total->amountWithoutCurrency : '',
                    'Delivery Date' => $firstProduct ? $order->delivery_date : '',
                    'Created Date' => $firstProduct ? $order->created_at->format('Y-m-d') : '',
                    'Product Name' => $product->name,
                    'Quantity' => $product->quantity,
                    'Unit Price' => $product->unit_price->amountWithoutCurrency,
                    'Unit Subtotal' => $product->subtotal->amountWithoutCurrency
                ];

                $firstProduct = false;
                return $data;
            });
        });
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                // Force landscape mode for PDF
                $event->sheet->getDelegate()->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
            }
        ];
    }
}
