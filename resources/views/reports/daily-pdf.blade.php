<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Harian {{ $date }}</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1e293b; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #1e40af; padding-bottom: 12px; }
        .header h1 { font-size: 16px; font-weight: bold; color: #1e40af; margin: 0; }
        .header p { color: #64748b; margin: 4px 0 0; font-size: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background: #1e40af; color: white; padding: 8px 10px; text-align: left; font-size: 10px; }
        th.center, td.center { text-align: center; }
        td { padding: 7px 10px; border-bottom: 1px solid #e2e8f0; }
        tr:nth-child(even) td { background: #f8fafc; }
        .tfoot td { background: #e2e8f0; font-weight: bold; border-top: 2px solid #94a3b8; }
        .grand { color: #1e40af; font-weight: 900; }
        .footer { margin-top: 20px; font-size: 9px; color: #94a3b8; text-align: right; }
        .summary { display: flex; gap: 20px; margin-bottom: 15px; }
        .summary-box { border: 1px solid #e2e8f0; padding: 8px 16px; border-radius: 6px; text-align: center; }
        .summary-box .val { font-size: 18px; font-weight: bold; color: #1e40af; }
        .summary-box .lbl { font-size: 9px; color: #94a3b8; }
    </style>
</head>
<body>
    <div class="header">
        <h1>LAPORAN PRODUKSI HARIAN</h1>
        <p>Tanggal: {{ \Carbon\Carbon::parse($date)->translatedFormat('l, d F Y') }} &nbsp;|&nbsp; Dicetak: {{ now()->format('d/m/Y H:i') }}</p>
    </div>

    <table style="width:auto;margin-bottom:16px">
        <tr>
            <th>UP</th><th>BT</th><th>Grand Total</th>
        </tr>
        <tr>
            <td class="center">{{ number_format($totalShift1) }}</td>
            <td class="center">{{ number_format($totalShift2) }}</td>
            <td class="center grand">{{ number_format($grandTotal) }}</td>
        </tr>
    </table>

    <table>
        <thead>
            <tr>
                <th>No</th><th>Produk</th><th>Seri</th><th>Kategori</th>
                <th class="center">UP</th><th class="center">BT</th>
                <th class="center">Total</th><th>Operator</th>
            </tr>
        </thead>
        <tbody>
            @foreach($logs as $i => $log)
            @php $isChannel = ($log->product->type ?? 'regular') === 'channel'; @endphp
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>
                    <strong>{{ $log->product->name ?? '-' }}</strong>
                    @if($isChannel)<br><em style="font-size:9px;color:#6d28d9;">Channel UP + BT</em>@endif
                    @if($log->notes)
                    <br><em style="font-size:9px;color:#64748b;">{{ $log->notes }}</em>
                    @endif
                </td>
                <td>{{ $log->product->series_with_kva ?: '-' }}</td>
                <td>{{ $log->product->category->name ?? '-' }}</td>
                <td class="center">{{ number_format($log->shift1_qty) }}</td>
                <td class="center">{{ number_format($log->shift2_qty) }}</td>
                <td class="center grand">{{ number_format($log->total_qty) }}</td>
                <td>{{ $log->user->name ?? '-' }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4" class="tfoot">TOTAL</td>
                <td class="tfoot center">{{ number_format($totalShift1) }}</td>
                <td class="tfoot center">{{ number_format($totalShift2) }}</td>
                <td class="tfoot center grand">{{ number_format($grandTotal) }}</td>
                <td class="tfoot"></td>
            </tr>
        </tfoot>
    </table>

    <div class="footer">Asata Production System &copy; {{ date('Y') }}</div>
</body>
</html>
