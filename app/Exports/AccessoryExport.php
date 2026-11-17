<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class AccessoryExport implements FromCollection, WithHeadings, WithTitle, WithStyles, ShouldAutoSize
{
    public function __construct(private Collection $accessories) {}

    public function collection()
    {
        return $this->accessories->map(fn ($a) => [
            'Tanggal'      => $a->accessory_date->translatedFormat('d M Y'),
            'Aksesoris'    => $a->name,
            'Seri Terkait' => $a->product ? ($a->product->series ?: $a->product->name) : '-',
            'KVA'          => $a->product && $a->product->kva ? $a->product->kva : '-',
            'No. Urut'     => $a->serial_number ?: '-',
            'Jumlah'       => $a->qty,
            'Satuan'       => $a->unit ?: '-',
            'Keterangan'   => $a->keterangan ?: '-',
            'Diinput'      => $a->operator_name ?? optional($a->user)->name ?? '-',
        ]);
    }

    public function headings(): array
    {
        return [
            'Tanggal', 'Aksesoris', 'Seri Terkait', 'KVA', 'No. Urut', 'Jumlah',
            'Satuan', 'Keterangan', 'Diinput',
        ];
    }

    public function title(): string
    {
        return 'Aksesoris Keluar';
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '1e40af']],
            ],
        ];
    }
}
