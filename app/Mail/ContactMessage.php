<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ContactMessage extends Mailable
{
    use Queueable, SerializesModels;

    public array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function envelope(): \Illuminate\Mail\Envelope
    {
        return new \Illuminate\Mail\Envelope(
            subject: 'QuickShare Contact Form: ' . $this->data['subject'],
            replyTo: [[$this->data['email'], $this->data['name']]],
        );
    }

    public function content(): \Illuminate\Mail\Content
    {
        return new \Illuminate\Mail\Content(
            markdown: 'emails.contact',
        );
    }
}
