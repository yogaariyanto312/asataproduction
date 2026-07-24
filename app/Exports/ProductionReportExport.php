<?php

namespace App\Exports;

use App\Models\ProductionLog;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Carbon\Carbon;

class ProductionReportExport implements FromCollection, WithHeadings, WithTitle, WithStyles, ShouldAutoSize
{
    public function __construct(
        private int $month,
        private int $year,
        private ?string $deptFilter = null
    ) {}

    public function collection()
    {
        return ProductionLog::select(
                'product_id',
                DB::raw('SUM(shift1_qty) as total_shift1'),
                DB::raw('SUM(shift2_qty) as total_shift2'),
                DB::raw('SUM(shift3_qty) as total_shift3'),
                DB::raw('SUM(total_qty) as grand_total')
            )
            ->with('product.category')
            ->whereMonth('production_date', $this->month)
            ->whereYear('production_date', $this->year)
            ->when($this->deptFilter, fn($q) => $q->where('production_logs.department', $this->deptFilter))
            ->groupBy('product_id')
            ->orderByDesc('grand_total')
            ->get()
            ->map(fn($item) => [
                'Kategori'    => $item->product->category->name ?? '-',
                'Produk'      => $item->product->name ?? '-',
                'Seri'        => $item->product->series ?? '-',
                'Satuan'      => $item->product->unit ?? 'unit',
                'UP'          => $item->total_shift1,
                'BT'          => $item->total_shift2,
                'Grand Total' => $item->grand_total,
            ]);
    }

    public function headings(): array
    {
        return ['Kategori', 'Produk', 'Seri', 'Satuan', 'UP', 'BT', 'Grand Total'];
    }

    public function title(): string
    {
        return Carbon::create(null, $this->month)->translatedFormat('F') . ' ' . $this->year;
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '1e40af']], 'font' => ['color' => ['rgb' => 'FFFFFF'], 'bold' => true]],
        ];
    }
}
