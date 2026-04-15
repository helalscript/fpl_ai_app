<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class OtpMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  'registration'|'password_reset'|'resend'  $purpose
     */
    public function __construct(
        public User $user,
        public string $otp,
        public string $purpose,
        public int $expiresMinutes = 10,
    ) {}

    public function envelope(): Envelope
    {
        $subject = match ($this->purpose) {
            'password_reset' => __('Your password reset OTP'),
            'resend' => __('Your verification OTP'),
            default => __('Verify your email'),
        };

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        $subject = match ($this->purpose) {
            'password_reset' => __('Your password reset OTP'),
            'resend' => __('Your verification OTP'),
            default => __('Verify your email'),
        };

        Log::info('OTP Mail sent to: '.$this->user->email, ['otp' => $this->otp]);

        return new Content(
            view: 'emails.otp',
            with: [
                'user' => $this->user,
                'otp' => $this->otp,
                'purpose' => $this->purpose,
                'expiresMinutes' => $this->expiresMinutes,
                'subjectLine' => $subject,
            ],
        );
    }
}
