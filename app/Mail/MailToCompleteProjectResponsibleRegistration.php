<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class MailToCompleteProjectResponsibleRegistration extends Mailable
{
    use Queueable, SerializesModels;

    private $de, $template , $promoter, $responsible, $invitation;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($from, $template , $promoter, $responsible, $invitation)
    {
        $this->de = $from;
        $this->template = $template;
        $this->promoter = $promoter;
        $this->responsible = $responsible;
        $this->invitation = $invitation;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->from($this->de)->view($this->template)->with(['promoter'=>$this->promoter, 'responsible'=>$this->responsible, 'invitation'=>$this->invitation]);
    }
}
