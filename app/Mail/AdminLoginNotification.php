<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminLoginNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $ip,
        public string $userAgent,
        public string $loginTime,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[Asata System] Login Berhasil - ' . $this->loginTime,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.admin-login',
        );
    }
}
