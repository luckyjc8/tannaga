<?php
 
namespace App\Mail;
 
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
 
class PasswordReset extends Mailable
{
    use Queueable, SerializesModels;

    public $str;
 
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($str)
    {
        $this->str = $str;
    }
 
    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view('emails.password_reset');
        //here is your blade view name
    }
}