<?php


namespace App\Domain\Model\Access;
use App\Domain\Model\Identity\Group;
use App\User;
use Illuminate\Database\Eloquent\Model;


class Role extends Model
{
    public const PROMOTER = "PROMOTER";
    public const RESPONSIBLE = "RESPONSIBLE";

    protected $table = 'roles';
    protected $fillable = ['roleid', 'name', 'description', 'photo', 'groupsplayingrole', 'usersplayingrole', 'scopes', 'created_by'];

    public function __construct($roleid = null, $name = null, $description = null, $photo = null,
                                $groupsplayingrole='[]', $usersplayingrole='[]', $scopes = '[]', $created_by = null, $attributes = array()){

        parent::__construct($attributes);
        $this->roleid = $roleid;
        $this->name = $name;
        $this->description = $description;
        $this->photo = $photo;
        $this->groupsplayingrole = $groupsplayingrole;
        $this->usersplayingrole = $usersplayingrole;
        $this->scopes = $scopes;
        $this->created_by = $created_by;

    }

    public function isUserInRole($userid){
        $users = User::where('userid', '=', $userid)->get();
        if (!(count($users) === 1)){
            return false;
        }
        $members = json_decode($this->usersplayingrole);
        foreach ($members as $member){
            if ($member == $userid){
                return true;
            }
        }
        $membersGroups = json_decode($this->groupsplayingrole);
        foreach ($membersGroups as $membersGroup){
            $groups = Group::where('groupid', '=', $membersGroup)->get();
            if(!(count($groups) === 1)){
                return false;
            }
            $membres = json_decode($groups[0]->members);
            foreach ($membres as $membre){
                if ($membre == $userid){
                    return true;
                }
            }
        }
        return false;
    }

    public function isGroupInRole($groupid){
        $groups = Group::where('groupid', '=', $groupid)->get();
        if (!(count($groups) === 1)){
            return false;
        }
        $groupids = json_decode($this->groupsplayingrole);
        foreach ($groupids as $item){
            if ($item == $groupid){
                return true;
            }
        }
        return false;
    }

    public function useridsPlayingRole(){
        $users = json_decode($this->usersplayingrole);
        $groups = json_decode($this->groupsplayingrole);
        foreach ($groups as $group){
            $theGroup = Group::where('groupid', '=', $group)->first();
            if (!($theGroup === null)){
                $members = json_decode($theGroup->members);
                foreach ($members as $member){
                    array_push($users, $member);
                }
            }
        }
        return $users;
    }

    public function groupidsPlayingRole(){
        $groups = json_decode($this->groupsplayingrole);
        return $groups;
    }

    public function usersPlayingRole(){
        $users = [];

        $usersids = json_decode($this->usersplayingrole);
        foreach ($usersids as $usersid){
            $auser = User::where('userid', '=', $usersid)->first();
            if ($auser){
                array_push($users, $auser);
            }
        }

        $groups = json_decode($this->groupsplayingrole);
        foreach ($groups as $group){
            $theGroup = Group::where('groupid', '=', $group)->first();
            if (!($theGroup === null)){
                $members = json_decode($theGroup->members);
                foreach ($members as $member){
                    $auser = User::where('userid', '=', $member)->first();
                    if ($auser){
                        array_push($users, $auser);
                    }
                }
            }
        }
        return $users;
    }

    public function groupsPlayingRole(){
        $retVal = [];
        $groups = json_decode($this->groupsplayingrole);
        foreach ($groups as $group){
            $theGroup = Group::where('groupid', '=', $group)->first();
            if (!($theGroup === null)){
                array_push($retVal, $theGroup);
            }
        }
        return $retVal;
    }
}
