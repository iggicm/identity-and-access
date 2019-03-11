<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class MailApplicationAccepted extends Mailable
{
    use Queueable, SerializesModels;

    private $de, $template, $application, $responsable, $projet, $role, $applicationroleid, $url;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($from, $template, $application, $responsable, $applicationroleid, $url)
    {
        $this->de = $from;
        $this->template = $template;
        $this->application = $application;
        $this->responsable = $responsable;
        $this->applicationroleid = $applicationroleid;
        $this->url = $url;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->from($this->de)->view($this->template)->with(['application'=>$this->application,
            'responsable'=>$this->responsable, 'applicationroleid'=>$this->applicationroleid,'logo'=>['path'=>asset('images/logo_home.png')],
            'url'=>$this->url/*Config::get('beautymail.view.logo')*/]);
    }
}
