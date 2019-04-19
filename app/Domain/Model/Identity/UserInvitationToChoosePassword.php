<?php
/**
 * Created by PhpStorm.
 * User: nkalla
 * Date: 04/01/19
 * Time: 18:41
 */

namespace App\Domain\Model\Identity;


use Illuminate\Database\Eloquent\Model;

class UserInvitationToChoosePassword extends Model
{

    protected $table = 'user_invitation_to_choose_passwords';
    protected $fillable = ['userid', 'firstname', 'lastname', 'email', 'phone', 'url'];

    public function __construct($userid = null,  $firstname = null, $lastname = null, $email = null, $phone = null, $url = null, array $attributes = [])
    {
        parent::__construct($attributes);
        $this->userid=$userid;
        $this->firstname=$firstname;
        $this->lastname=$lastname;
        $this->email=$email;
        $this->phone=$phone;
        $this->url=$url;
    }
}
