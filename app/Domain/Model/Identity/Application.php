<?php
/**
 * Created by PhpStorm.
 * User: nkalla
 * Date: 04/01/19
 * Time: 19:06
 */

namespace App\Domain\Model\Identity;


use Illuminate\Database\Eloquent\Model;

class Application extends Model
{
    public const DEFAULT_APPLICATION_STATE = 'APPLICATION_RECEIVED';
    public const APPLICATION_DENIED = 'APPLICATION_DENIED';
    public const APPLICATION_ACCEPTED = 'APPLICATION_ACCEPTED';

    protected $table = 'applications';
    protected $fillable = ['applicationid','firstname', 'lastname', 'gender', 'photo', 'country','cityofresidency', 'interestcenter',
        'email', 'phone', 'commenttotheregistration',  'state', 'latitude', 'longitude'];


    public function __construct($applicationid = null,  $firstname = null, $lastname = null, $gender = null, $photo = null,
                                $country = null, $cityofresidency = null, $interestcenter = null, $email = null, $phone = null,
                                $commenttotheregistration = null, $state = null, $latitude = null, $longitude = null, $attributes = array())
    {
        parent::__construct($attributes);

        $this->applicationid = $applicationid;
        $this->firstname = $firstname;
        $this->lastname = $lastname;
        $this->gender = $gender;
        $this->photo = $photo;
        $this->country = $country;
        $this->cityofresidency = $cityofresidency;
        $this->interestcenter = $interestcenter;
        $this->email = $email;
        $this->phone = $phone;
        $this->commenttotheregistration = $commenttotheregistration;
        $this->state = $state;
        $this->latitude = $latitude;
        $this->longitude = $longitude;
    }

}
