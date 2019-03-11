<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Config;

class MailApplicationReceived extends Mailable
{
    use Queueable, SerializesModels;

    private $de, $template, $application, $responsable;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($from, $template, $application)
    {
        $this->de = $from;
        $this->template = $template;
        $this->application = $application;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->from($this->de)->view($this->template)->with(['application'=>$this->application, 'logo'=>['path'=>asset('images/logo_home.png')]/*Config::get('beautymail.view.logo')*/]);
    }
}
