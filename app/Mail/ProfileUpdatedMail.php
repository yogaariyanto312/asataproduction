<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ProfileUpdatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public array $changes,
        public string $updatedAt,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: '[Asata System] Perubahan Data Akun');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.profile-updated');
    }
}
