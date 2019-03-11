<?php
/**
 * Created by PhpStorm.
 * User: nkalla
 * Date: 04/01/19
 * Time: 18:28
 */

namespace App\Domain\Model\Identity;


class UserDescriptor
{
    public $userDetails;


    public function __construct(UserDetails $userDetails)
    {
        $this->userDetails = $userDetails;
    }

}
