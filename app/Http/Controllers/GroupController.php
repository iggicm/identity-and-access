<?php

namespace App\Http\Controllers;

use App\Domain\Model\Identity\Group;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Webpatser\Uuid\Uuid;

class GroupController extends Controller
{


    public function createNewGroup(Request $request){

        $validator = Validator::make(

            $request->all(),
            [
                'name' => 'required|string|min:1|max:100',
                'description' => 'required|string|min:1|max:1000',
            ]
        );

        if ($validator->fails()) {
            return response(array('success' => 0, 'faillure' => 1, 'raison' => $validator->errors()->first()), 200);
        }

        $members = $request->get('members');
        if ($members == null){
            $members = '[]';
        }
        $user = Auth::user();
        $created_by = $user->userid;

        $groupToRegister = new Group(
            Uuid::generate()->string,
            $request->get('name'),
            $request->get('description'),
            $members,
            $created_by
        );


        $groupToRegister->save();

        return response(array('success' => 1, 'faillure' => 0, 'response' => 'Group creation successfull'), 200);
    }

    public function update(Request $request, $groupid)
    {
        $validator = Validator::make(

            $request->all(),
            [
                'action' => 'required|string|min:1',
                //'members'=> 'required|string|min:1',
            ]
        );

        if ($validator->fails()) {
            return response(array('success' => 0, 'faillure' => 1, 'raison' => $validator->errors()->first()), 200);
        }

        $action = $request->get('action');

        $groupArray = Group::where('groupid', '=', $groupid)->get();
        if (!(count($groupArray) === 1)) {
            return response(array('success' => 0, 'faillure' => 1, 'raison' => 'Group inexistant'), 200);
        }

        $group  = $groupArray[0];

        if ($action == env('ADD_MEMBERS_TO_GROUP')) {

            $validator = Validator::make(

                $request->all(),
                [
                    'action' => 'required|string|min:1',
                    'members'=> 'required|string|min:1',
                ]
            );

            if ($validator->fails()) {
                return response(array('success' => 0, 'faillure' => 1, 'raison' => $validator->errors()->first()), 200);
            }

            $members = json_decode($request->get('members'));

            $retVal = $group->addMembersToGroup($members);

            if ($retVal[0] === true){
                return response(array('success' => 1, 'faillure' => 0, 'response' => 'Membres  ajoutes avec succes', 'inserted' => $retVal[1], 'notinserted' => $retVal[2]));
            }
            return response(array('success' => 0, 'faillure' => 1, 'raison' => $retVal[3]), 200);
        }
        elseif ($action == env('REMOVE_MEMBERS_TO_GROUP')) {

            $validator = Validator::make(

                $request->all(),
                [
                    'action' => 'required|string|min:1',
                    'members'=> 'required|string|min:1',
                ]
            );

            if ($validator->fails()) {
                return response(array('success' => 0, 'faillure' => 1, 'raison' => $validator->errors()->first()), 200);
            }

            $members = json_decode($request->get('members'));

            $retVal = $group->removeMembersToGroup($members);

            if ($retVal[0] === true){
                return response(array('success' => 1, 'faillure' => 0, 'response' => 'Membres  ajoutes avec succes', 'removed' => $retVal[1], 'noremoved' => $retVal[2]));
            }
            return response(array('success' => 0, 'faillure' => 1, 'raison' => $retVal[3]), 200);
        }
        return response(array('success' => 0, 'faillure' => 1, 'raison' => 'Aucune action specifiee'), 200);
    }

    public function retrieveAllGroup(Request $request){
        return response(array('success' => 1, 'faillure' => 0, 'response' => Group::all()));
    }

    public function notGroupMembers(Request $request, $groupid){

        $groups = Group::where('groupid', '=', $groupid)->get();

        if(!(count($groups) === 1)){
            return response(array('success' => 0, 'faillure' => 1, 'raison' => "Group inexistant"), 200);
        }

        $group = $groups[0];
        $currentGroupMembers = json_decode($group->members);
        return response(array('success' => 1, 'faillure' => 0, 'response' => User::whereNotIn('userid',$currentGroupMembers)->get()), 200);
        //return User::whereNotIn('userid',$currentGroupMembers)->get();
    }

}
