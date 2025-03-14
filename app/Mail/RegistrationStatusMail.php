<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RegistrationStatusMail extends Mailable
{
    use Queueable, SerializesModels;

    public $status;
    public $firstName;
    public $lastName;

    /**
     * Create a new message instance.
     */
    public function __construct($status, $firstName, $lastName)
    {
        $this->status = $status;
        $this->firstName = $firstName;
        $this->lastName = $lastName;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject("Registration Request {$this->status}")
                    ->view('emails.registration_status')
                    ->with([
                        'status' => $this->status,
                        'firstName' => $this->firstName,
                        'lastName' => $this->lastName,
                    ]);
    }
}
