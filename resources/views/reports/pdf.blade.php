<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Produksi {{ $monthName }} {{ $year }}</title>
    <style>
        @page { margin: 28px 32px 44px 32px; }

        * { box-sizing: border-box; }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10px;
            color: #1e293b;
            margin: 0;
        }

        /* ===== Header ===== */
        .header {
            border-bottom: 3px solid #1e40af;
            padding-bottom: 10px;
            margin-bottom: 14px;
        }
        .header table { width: 100%; border-collapse: collapse; }
        .header td { vertical-align: middle; border: none; padding: 0; }
        .header h1 {
            font-size: 17px;
            font-weight: bold;
            color: #1e40af;
            margin: 0;
            letter-spacing: 0.5px;
        }
        .header .sub { color: #64748b; font-size: 9.5px; margin-top: 3px; }
        .header .period-badge {
            display: inline-block;
            background: #1e40af;
            color: #fff;
            font-size: 11px;
            font-weight: bold;
            padding: 6px 14px;
            border-radius: 4px;
        }

        /* ===== Summary cards ===== */
        .summary { width: 100%; border-collapse: separate; border-spacing: 8px 0; margin-bottom: 14px; }
        .summary td {
            width: 25%;
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 9px 12px;
            text-align: center;
        }
        .summary .label { font-size: 8.5px; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }
        .summary .value { font-size: 16px; font-weight: bold; color: #1e40af; margin-top: 2px; }
        .summary .value.muted { color: #334155; }

        /* ===== Main table ===== */
        table.data { width: 100%; border-collapse: collapse; }
        table.data thead th {
            background: #1e40af;
            color: #fff;
            padding: 8px 9px;
            text-align: left;
            font-size: 9.5px;
            font-weight: bold;
            border-right: 1px solid #2c50c4;
        }
        table.data thead th:last-child { border-right: none; }
        table.data thead th.center { text-align: center; }

        table.data tbody td {
            padding: 7px 9px;
            border-bottom: 1px solid #e8edf3;
            font-size: 9.5px;
            vertical-align: middle;
        }
        table.data tbody tr:nth-child(even) td { background: #f8fafc; }
        table.data tbody td.center { text-align: center; }
        table.data .no { color: #94a3b8; font-weight: bold; }
        table.data .produk { font-weight: bold; color: #0f172a; }
        table.data .seri { font-family: 'DejaVu Sans Mono', monospace; font-size: 9px; color: #475569; }
        table.data .kategori {
            font-size: 8.5px;
            color: #475569;
        }
        table.data .grand { color: #1e40af; font-weight: bold; font-size: 11px; }
        table.data .urut {
            font-family: 'DejaVu Sans Mono', monospace;
            font-size: 8.5px;
            color: #64748b;
            white-space: pre-line;
            line-height: 1.5;
        }

        /* ===== Footer total row ===== */
        table.data tfoot td {
            background: #1e293b;
            color: #fff;
            font-weight: bold;
            padding: 9px;
            font-size: 10px;
            border: none;
        }
        table.data tfoot td.center { text-align: center; }
        table.data tfoot .grand { color: #93c5fd; font-size: 12px; }

        /* ===== Page footer ===== */
        .page-footer {
            position: fixed;
            bottom: -28px;
            left: 0;
            right: 0;
            font-size: 8px;
            color: #94a3b8;
        }
        .page-footer table { width: 100%; border-collapse: collapse; }
        .page-footer td { border: none; padding: 0; }
        .page-footer .right { text-align: right; }
    </style>
</head>
<body>
    {{-- Header --}}
    <div class="header">
        <table>
            <tr>
                <td>
                    <h1>LAPORAN PRODUKSI BULANAN</h1>
                    <div class="sub">Asata Production System &nbsp;&bull;&nbsp; Dicetak {{ now()->format('d/m/Y H:i') }}</div>
                </td>
                <td style="text-align: right;">
                    <span class="period-badge">{{ strtoupper($monthName) }} {{ $year }}</span>
                </td>
            </tr>
        </table>
    </div>

    {{-- Summary --}}
    @php
        $totalProduk = $report->count();
        $totalUp     = $report->sum('total_shift1');
        $totalBt     = $report->sum('total_shift2');
        $grandAll    = $report->sum('grand_total');
    @endphp
    <table class="summary">
        <tr>
            <td>
                <div class="label">Jenis Produk</div>
                <div class="value muted">{{ number_format($totalProduk) }}</div>
            </td>
            <td>
                <div class="label">Total UP</div>
                <div class="value muted">{{ number_format($totalUp) }}</div>
            </td>
            <td>
                <div class="label">Total BT</div>
                <div class="value muted">{{ number_format($totalBt) }}</div>
            </td>
            <td>
                <div class="label">Grand Total</div>
                <div class="value">{{ number_format($grandAll) }}</div>
            </td>
        </tr>
    </table>

    {{-- Data table --}}
    <table class="data">
        <thead>
            <tr>
                <th class="center" style="width: 4%;">No</th>
                <th style="width: 17%;">Produk</th>
                <th style="width: 17%;">Seri</th>
                <th style="width: 15%;">Kategori</th>
                <th class="center" style="width: 7%;">UP</th>
                <th class="center" style="width: 7%;">BT</th>
                <th class="center" style="width: 11%;">Grand Total</th>
                <th style="width: 22%;">No. Urut Terakhir</th>
            </tr>
        </thead>
        <tbody>
            @forelse($report as $i => $row)
            <tr>
                <td class="center no">{{ $i + 1 }}</td>
                <td class="produk">{{ $row->product->name ?? '-' }}</td>
                <td class="seri">{{ $row->product->series_with_kva ?: '-' }}</td>
                <td class="kategori">{{ $row->product->category->name ?? '-' }}</td>
                <td class="center">{{ number_format($row->total_shift1) }}</td>
                <td class="center">{{ number_format($row->total_shift2) }}</td>
                <td class="center grand">{{ number_format($row->grand_total) }}</td>
                <td class="urut">{{ $row->last_notes ?: '-' }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="8" class="center" style="padding: 20px; color: #94a3b8;">
                    Tidak ada data produksi pada periode ini.
                </td>
            </tr>
            @endforelse
        </tbody>
        @if($report->isNotEmpty())
        <tfoot>
            <tr>
                <td colspan="4">TOTAL KESELURUHAN</td>
                <td class="center">{{ number_format($totalUp) }}</td>
                <td class="center">{{ number_format($totalBt) }}</td>
                <td class="center grand">{{ number_format($grandAll) }}</td>
                <td></td>
            </tr>
        </tfoot>
        @endif
    </table>

    {{-- Page footer --}}
    <div class="page-footer">
        <table>
            <tr>
                <td>Asata Production System &copy; {{ date('Y') }}</td>
                <td class="right">Laporan Produksi {{ $monthName }} {{ $year }}</td>
            </tr>
        </table>
    </div>
</body>
</html>
