<?php
 
namespace App\Mail;
 
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
 
class LetterSender extends Mailable
{
    use Queueable, SerializesModels;

    public $path;
 
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($path)
    {
        $this->path = $path;
    }
 
    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view('emails.send_letter')->attachFromStorage($this->path);
    }
}