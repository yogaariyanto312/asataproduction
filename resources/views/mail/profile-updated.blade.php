<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Perubahan Data Akun</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f4; margin: 0; padding: 0; }
        .wrapper { max-width: 600px; margin: 30px auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .header { background-color: #1e40af; padding: 24px 32px; }
        .header h1 { color: #ffffff; margin: 0; font-size: 20px; font-weight: 600; }
        .header p { color: #bfdbfe; margin: 4px 0 0; font-size: 13px; }
        .body { padding: 32px; }
        .greeting { font-size: 16px; color: #1f2937; margin-bottom: 16px; }
        .message { font-size: 14px; color: #374151; margin-bottom: 20px; line-height: 1.6; }
        .change-list { margin: 20px 0; border-radius: 8px; overflow: hidden; border: 1px solid #e5e7eb; }
        .change-item { display: flex; align-items: flex-start; padding: 12px 18px; border-bottom: 1px solid #f3f4f6; }
        .change-item:last-child { border-bottom: none; }
        .change-icon { width: 20px; margin-right: 12px; margin-top: 2px; flex-shrink: 0; }
        .change-label { font-size: 12px; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 2px; }
        .change-value { font-size: 14px; color: #111827; }
        .change-value.password { color: #6b7280; font-style: italic; }
        .meta { margin-top: 20px; padding: 12px 18px; background-color: #f9fafb; border-radius: 6px; font-size: 13px; color: #6b7280; }
        .warning { margin-top: 24px; padding: 14px 18px; background-color: #fef2f2; border-left: 4px solid #ef4444; border-radius: 4px; font-size: 13px; color: #7f1d1d; line-height: 1.6; }
        .footer { background-color: #f9fafb; padding: 20px 32px; text-align: center; font-size: 12px; color: #9ca3af; border-top: 1px solid #e5e7eb; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="header">
            <h1>Asata Production System</h1>
            <p>Notifikasi Perubahan Data Akun</p>
        </div>
        <div class="body">
            <p class="greeting">Halo, <strong>{{ $user->name }}</strong>!</p>

            <p class="message">
                Data akun Anda baru saja diperbarui. Berikut perubahan yang dilakukan:
            </p>

            <div class="change-list">
                @if(in_array('Nama', $changes))
                <div class="change-item">
                    <svg class="change-icon" fill="none" stroke="#1d4ed8" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                    <div>
                        <div class="change-label">Nama</div>
                        <div class="change-value">{{ $user->name }}</div>
                    </div>
                </div>
                @endif

                @if(in_array('Email', $changes))
                <div class="change-item">
                    <svg class="change-icon" fill="none" stroke="#1d4ed8" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                    <div>
                        <div class="change-label">Email</div>
                        <div class="change-value">{{ $user->email ?? '-' }}</div>
                    </div>
                </div>
                @endif

                @if(in_array('Password', $changes))
                <div class="change-item">
                    <svg class="change-icon" fill="none" stroke="#1d4ed8" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                    <div>
                        <div class="change-label">Password</div>
                        <div class="change-value password">Password berhasil diperbarui</div>
                    </div>
                </div>
                @endif
            </div>

            <div class="meta">
                Diperbarui pada: <strong>{{ $updatedAt }}</strong>
            </div>

            <div class="warning">
                <strong>Bukan Anda yang melakukan perubahan ini?</strong><br>
                Segera hubungi administrator sistem untuk mengamankan akun Anda.
            </div>
        </div>
        <div class="footer">
            Email ini dikirim otomatis oleh sistem. Jangan balas email ini.<br>
            &copy; {{ date('Y') }} Asata Production System
        </div>
    </div>
</body>
</html>
