<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifikasi Login</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f4; margin: 0; padding: 0; }
        .wrapper { max-width: 600px; margin: 30px auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .header { background-color: #1e40af; padding: 24px 32px; }
        .header h1 { color: #ffffff; margin: 0; font-size: 20px; font-weight: 600; }
        .header p { color: #bfdbfe; margin: 4px 0 0; font-size: 13px; }
        .body { padding: 32px; }
        .greeting { font-size: 16px; color: #1f2937; margin-bottom: 16px; }
        .alert-box { background-color: #eff6ff; border-left: 4px solid #3b82f6; border-radius: 4px; padding: 16px 20px; margin-bottom: 24px; }
        .alert-box p { margin: 0; color: #1e40af; font-size: 14px; }
        .info-table { width: 100%; border-collapse: collapse; font-size: 14px; }
        .info-table tr { border-bottom: 1px solid #e5e7eb; }
        .info-table tr:last-child { border-bottom: none; }
        .info-table td { padding: 10px 0; color: #374151; vertical-align: top; }
        .info-table td:first-child { color: #6b7280; width: 140px; }
        .badge { display: inline-block; background-color: #dbeafe; color: #1e40af; padding: 2px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; text-transform: uppercase; }
        .warning { margin-top: 24px; padding: 14px 18px; background-color: #fefce8; border-left: 4px solid #eab308; border-radius: 4px; font-size: 13px; color: #713f12; }
        .footer { background-color: #f9fafb; padding: 20px 32px; text-align: center; font-size: 12px; color: #9ca3af; border-top: 1px solid #e5e7eb; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="header">
            <h1>Asata Production System</h1>
            <p>Notifikasi Keamanan Akun</p>
        </div>
        <div class="body">
            <p class="greeting">Halo, <strong>{{ $user->name }}</strong>!</p>

            <div class="alert-box">
                <p>Akun Anda baru saja digunakan untuk login ke <strong>Asata Production System</strong>.</p>
            </div>

            <table class="info-table">
                <tr>
                    <td>Waktu Login</td>
                    <td><strong>{{ $loginTime }}</strong></td>
                </tr>
                <tr>
                    <td>Role</td>
                    <td><span class="badge">{{ ucfirst($user->role) }}</span></td>
                </tr>
                <tr>
                    <td>IP Address</td>
                    <td><strong>{{ $ip }}</strong></td>
                </tr>
                <tr>
                    <td>Browser / Device</td>
                    <td style="word-break: break-all;">{{ $userAgent }}</td>
                </tr>
            </table>

            <div class="warning">
                Jika Anda tidak merasa melakukan login ini, segera hubungi administrator dan ganti password Anda.
            </div>
        </div>
        <div class="footer">
            Email ini dikirim otomatis oleh sistem. Jangan balas email ini.<br>
            &copy; {{ date('Y') }} Asata Production System
        </div>
    </div>
</body>
</html>
