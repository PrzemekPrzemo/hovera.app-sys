<?php

declare(strict_types=1);

namespace App\Mail\MasterAdmin;

use App\Models\Central\TransportReview;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Notyfikacja do master admin'ów gdy transporter flag'uje review.
 * Master admin powinien zdecydować publish vs hide z poziomu
 * `/admin/transport-reviews` filter „Tylko flagged". Patrz docs/TRANSPORT.md §12.
 */
class ReviewFlaggedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly TransportReview $review,
        public readonly string $transporterName,
        public readonly string $flaggedByEmail,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('mail/review_flagged.subject', ['transporter' => $this->transporterName]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.master-admin.review-flagged',
            with: [
                'review' => $this->review,
                'transporterName' => $this->transporterName,
                'flaggedByEmail' => $this->flaggedByEmail,
                'adminUrl' => url('/'.config('hovera.admin.path', 'admin').'/transport-reviews/'.$this->review->id),
            ],
        );
    }
}
