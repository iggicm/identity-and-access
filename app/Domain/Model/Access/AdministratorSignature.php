<?php
/**
 * Created by PhpStorm.
 * User: nkalla
 * Date: 23/02/19
 * Time: 21:18
 */

namespace App\Domain\Model\Access;


class AdministratorSignature
{
    public $administator_id, $signe_at,  $administrator_name;

    /**
     * AdministratorSignature constructor.
     * @param $administator_id
     * @param $signe_at
     * @param $administrator_name
     */
    public function __construct($administator_id, $signe_at, $administrator_name)
    {
        $this->administator_id = $administator_id;
        $this->signe_at = $signe_at;
        $this->administrator_name = $administrator_name;
    }
}
