<?php
/**
 * Created by PhpStorm.
 * User: nkalla
 * Date: 06/01/19
 * Time: 17:59
 */

namespace App\Domain\event;


use Illuminate\Database\Eloquent\Model;

class GroupAddedToRole extends Model
{
    protected $table = 'groupaddedtoroles';
    protected $fillable = ['groupaddedtoroleid', 'roleid', 'groupid', 'added_by'];

    public function __construct($groupaddedtoroleid = null, $roleid = null, $groupid = null, $added_by = null, array $attributes = [])
    {
        parent::__construct($attributes);
        $this->groupaddedtoroleid = $groupaddedtoroleid;
        $this->roleid = $roleid;
        $this->groupid = $groupid;
        $this->added_by = $added_by;
    }

}
