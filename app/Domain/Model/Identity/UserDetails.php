<?php
/**
 * Created by PhpStorm.
 * User: nkalla
 * Date: 04/01/19
 * Time: 18:31
 */

namespace App\Domain\Model\Identity;


class UserDetails
{

    public $userid, $firstname, $lastname, $email, $phone, $enablement, $isAdmin;
    public function __construct($userid = null, $firstname = null, $lastname = null, $email = null, $phone = null, $enablement = null, $isAdmin)
    {
        $this->userid = $userid;
        $this->firstname = $firstname;
        $this->lastname = $lastname;
        $this->email = $email;
        $this->phone = $phone;
        $this->enablement = $enablement;
        $this->isAdmin = $isAdmin;
    }
}
