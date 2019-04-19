<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class MailNotificator extends Mailable
{
    use Queueable, SerializesModels;

    private $de, $template, $invitation, $user;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($from, $template, $invitation, $user)
    {
        $this->de = $from;
        $this->template = $template;
        $this->invitation = $invitation;
        $this->user = $user;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->from($this->de)->view($this->template)->with(['invitation'=>$this->invitation, 'user'=>$this->user,  'logo'=>['path'=>asset('images/logo_home.png')]]);
    }
}
