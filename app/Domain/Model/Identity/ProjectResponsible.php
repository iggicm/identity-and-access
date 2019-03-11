<?php
/**
 * Created by PhpStorm.
 * User: nkalla
 * Date: 08/01/19
 * Time: 16:09
 */

namespace App\Domain\Model\Identity;


use App\User;

class ProjectResponsible extends User
{
    protected $table = 'users';

    public function __construct($userid = null, $projectid = null, $firstname = null, $lastname = null, $birthdate = null,
                                $placeofbirth = null, $gender = null, $photo = null, $documenttype = null, $documentid = null,
                                $country = null, $region = null, $division = null, $town = null, $countryname = null, $regionname = null,
                                $divisionname = null, $townname = null, $email = null, $phone = null, $password = null,
                                $created_by = null, array $attributes = array())
    {
        parent::__construct($userid, $projectid, $firstname, $lastname, $birthdate, $placeofbirth, $gender, $photo, $documenttype,
            $documentid, $country, $region, $division, $town, $countryname, $regionname, $divisionname, $townname, $email, $phone,
            0, $password, $created_by, $attributes);
    }

}
