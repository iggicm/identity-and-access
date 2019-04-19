<?php
/**
 * Created by PhpStorm.
 * User: nkalla
 * Date: 06/01/19
 * Time: 17:59
 */

namespace App\Domain\event;


use Illuminate\Database\Eloquent\Model;

class UserAddedToRole extends Model
{
    protected $table = 'useraddedtoroles';
    protected $fillable = ['useraddedtoroleid', 'roleid', 'userid', 'added_by'];

    public function __construct($useraddedtoroleid = null, $roleid = null, $userid = null, $added_by = null, array $attributes = [])
    {
        parent::__construct($attributes);
        $this->useraddedtoroleid = $useraddedtoroleid;
        $this->roleid = $roleid;
        $this->userid = $userid;
        $this->added_by = $added_by;
    }

}
