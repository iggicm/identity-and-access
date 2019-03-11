<?php

namespace App\Domain\Model\Identity;

use App\User;
use Illuminate\Database\Eloquent\Model;

class Group extends Model
{
    public const PROMOTER = "PROMOTER";
    public const RESPONSIBLE = "RESPONSIBLE";

    protected $table = 'groups';
    protected $fillable = ['groupid', 'name', 'description', 'members', 'created_by'];



    public function __construct($groupid = null, $name =null, $description = null, $members = null, $created_by = null, $attributes = array())
    {
        parent::__construct($attributes);
        $this->groupid = $groupid;
        $this->description = $description;
        $this->name = $name;
        $this->members = $members;
        $this->created_by = $created_by;

    }

    public function addMembersToGroup($members){
        $membres = json_decode($this->members);
        $inserted = [];
        $notinserted = [];
        foreach ($members as $member){
            $users = User::where('userid', '=', $member)->get();
            if (!(count($users) === 1)){
                $inseree = [];
                $noninseree = [];
                return [false, $inseree, $noninseree, 'le membre reference par "' . $member . '" n\'existe pas dans le systeme'];
            }
            $found = false;
            foreach ($membres as $membre){
                if ($membre == $member){
                    $found = true;
                    array_push($notinserted, $member);
                    break;
                }
            }
            if ($found === false){
                array_push($membres, $member);
                array_push($inserted, $member);
            }
        }
        try{
            $this->members = json_encode($membres, JSON_UNESCAPED_SLASHES);
            $this->save();
        }catch (\Exception $e){
            return [false, $inserted, $notinserted, $e->getMessage()];
        }

        return [true, $inserted, $notinserted];
    }


    public function removeMembersToGroup($members){
        $membres = json_decode($this->members);
        $removed = [];
        $notremoved = [];
        foreach ($members as $member){
            $users = User::where('userid', '=', $member)->get();
            if (!(count($users) === 1)){
                $inseree = [];
                $noninseree = [];
                return [false, $inseree, $noninseree, 'le membre reference par "' . $member . '" n\'existe pas dans le systeme'];
            }
            $found = false;
            for ($i = 0; $i<count($membres); $i++){
                if ($membres[$i] == $member){
                    $found = true;
                    array_splice($membres, $i, 1);
                    array_push($removed, $member);
                    break;
                }
            }
            if ($found === false){
                array_push($notremoved, $member);
                //array_push($inserted, $member);
            }
        }
        try{
            $this->members = json_encode($membres, JSON_UNESCAPED_SLASHES);
            $this->save();
        }catch (\Exception $e){
            return [false, $removed, $notremoved, $e->getMessage()];
        }

        return [true, $removed, $notremoved];
    }

}
