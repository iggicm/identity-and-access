<?php

namespace App\Domain\Model\Identity;

use Illuminate\Database\Eloquent\Model;

class PasswordResetInvitation extends Model
{

    protected $table = 'password_reset_invitations';
    protected $fillable = ['invitationid', 'firstname', 'lastname', 'email', 'used', 'url'];



    public function __construct($invitationid = null, $firstname =null, $lastname =null, $email = null, $url = null, $attributes = array())
    {
        parent::__construct($attributes);
        $this->invitationid = $invitationid;
        $this->firstname = $firstname;
        $this->lastname = $lastname;
        $this->email = $email;
        $this->used = false;
        $this->url = $url;

    }
}

//
