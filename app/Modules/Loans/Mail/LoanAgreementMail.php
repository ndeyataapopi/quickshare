<?php

namespace App\Modules\Loans\Mail;

use App\Modules\Loans\Models\Loan;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LoanAgreementMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Loan $loan,
    ) {
        $this->onQueue('notifications');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Loan Agreement {$this->loan->reference}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.loans.agreement',
        );
    }

    public function attachments(): array
    {
        return [
            Attachment::fromStorageDisk(
                (string) config('loan.agreement.disk'),
                $this->loan->agreement_path,
            )->as("loan-agreement-{$this->loan->reference}.pdf")
                ->withMime('application/pdf'),
        ];
    }
}
