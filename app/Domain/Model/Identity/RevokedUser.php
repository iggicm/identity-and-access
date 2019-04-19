<?php

namespace App\Domain\Model\Identity;

use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Hash;
use Laravel\Passport\HasApiTokens;
use Webpatser\Uuid\Uuid;

class RevokedUser extends Authenticatable
{
    use HasApiTokens, Notifiable;
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['revokeduserid', 'firstname', 'lastname', 'gender', 'photo', 'country','cityofresidency', 'interestcenter',
        'email', 'phone', 'enablement', 'revoked_by', 'password',  'commenttotheregistration', 'mustresetpassword', 'latitude',
        'longitude', 'isconnected', 'access_token'];


    protected $table = 'revokedusers';

    /*protected $fillable = [
        'name', 'email', 'password',
    ];*/

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];


    public function __construct($revokeduserid = null,  $firstname = null, $lastname = null, $gender = null, $photo = null,
                                $country = null, $cityofresidency = null, $interestcenter = null, $email = null, $phone = null,
                                $enablement = null, $revoked_by = null, $password = null, $commenttotheregistration = null, $mustresetpassword = null,
                                $latitude = null, $longitude = null, $isconnected= null, $access_token = null, $attributes = array())
    {
        parent::__construct($attributes);

        $this->revokeduserid = $revokeduserid;
        $this->firstname = $firstname;
        $this->lastname = $lastname;
        $this->gender = $gender;
        $this->photo = $photo;
        $this->country = $country;
        $this->cityofresidency = $cityofresidency;
        $this->interestcenter = $interestcenter;
        $this->email = $email;
        $this->phone = $phone;
        $this->enablement = $enablement;
        $this->revoked_by = $revoked_by;
        $this->password = $password . '123456';
        $this->commenttotheregistration = $commenttotheregistration;
        $this->mustresetpassword = $mustresetpassword;
        $this->latitude = $latitude;
        $this->longitude = $longitude;
        $this->isconnected = $isconnected;
        $this->access_token = $access_token;

    }

    public function getPhoneNumberCountry(){
        //\Propaganistas\LaravelPhone\PhoneNumber::make('+237 691179154')->isOfCountry('CM')
        $countries = \Propaganistas\LaravelIntl\Facades\Country::all();
        foreach ($countries as $country){
            if (\Propaganistas\LaravelPhone\PhoneNumber::make($this->phone)->isOfCountry($country)){
                return $country;
            }
        }
        return null;
    }

    public function getcountryCode(){
        $country = json_decode($this->country);
        return $country->code;
    }

    public function getcountryName(){
        $country = json_decode($this->country);
        return $country->name;
    }
}

