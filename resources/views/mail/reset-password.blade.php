<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f4; margin: 0; padding: 0; }
        .wrapper { max-width: 600px; margin: 30px auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .header { background-color: #1e40af; padding: 24px 32px; }
        .header h1 { color: #ffffff; margin: 0; font-size: 20px; font-weight: 600; }
        .header p { color: #bfdbfe; margin: 4px 0 0; font-size: 13px; }
        .body { padding: 32px; }
        .greeting { font-size: 16px; color: #1f2937; margin-bottom: 16px; }
        .message { font-size: 14px; color: #374151; margin-bottom: 24px; line-height: 1.6; }
        .btn-wrap { text-align: center; margin: 28px 0; }
        .btn { display: inline-block; background-color: #1d4ed8; color: #ffffff; text-decoration: none; padding: 14px 32px; border-radius: 8px; font-size: 15px; font-weight: 600; }
        .expire-note { font-size: 13px; color: #6b7280; text-align: center; margin-top: 8px; }
        .url-fallback { margin-top: 24px; padding: 14px 18px; background-color: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; }
        .url-fallback p { margin: 0 0 6px; font-size: 12px; color: #6b7280; }
        .url-fallback a { font-size: 11px; color: #1d4ed8; word-break: break-all; }
        .warning { margin-top: 24px; padding: 14px 18px; background-color: #fef2f2; border-left: 4px solid #ef4444; border-radius: 4px; font-size: 13px; color: #7f1d1d; }
        .footer { background-color: #f9fafb; padding: 20px 32px; text-align: center; font-size: 12px; color: #9ca3af; border-top: 1px solid #e5e7eb; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="header">
            <h1>Asata Production System</h1>
            <p>Reset Password Akun</p>
        </div>
        <div class="body">
            <p class="greeting">Halo, <strong>{{ $user->name }}</strong>!</p>

            <p class="message">
                Kami menerima permintaan untuk mereset password akun Anda.<br>
                Klik tombol di bawah untuk membuat password baru.
            </p>

            <div class="btn-wrap">
                <a href="{{ $resetUrl }}" class="btn" style="display:inline-block;background-color:#1d4ed8;color:#ffffff;text-decoration:none;padding:14px 32px;border-radius:8px;font-size:15px;font-weight:600;">Reset Password Sekarang</a>
            </div>
            <p class="expire-note">Link ini akan kadaluarsa dalam <strong>{{ $expireMinutes }} menit</strong>.</p>

            <div class="url-fallback">
                <p>Jika tombol tidak bisa diklik, salin URL berikut ke browser:</p>
                <a href="{{ $resetUrl }}">{{ $resetUrl }}</a>
            </div>

            <div class="warning">
                Jika Anda tidak merasa meminta reset password, abaikan email ini. Password Anda tidak akan berubah.
            </div>
        </div>
        <div class="footer">
            Email ini dikirim otomatis oleh sistem. Jangan balas email ini.<br>
            &copy; {{ date('Y') }} Asata Production System
        </div>
    </div>
</body>
</html>
