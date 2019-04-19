<?php

namespace App\Http\Controllers;

use App\Domain\Model\Access\Role;
use App\Domain\Model\Identity\Group;
use App\Jobs\ProcessRoleUpdatedEventJob;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Webpatser\Uuid\Uuid;

class RoleController extends Controller
{

    public function createNewRole(Request $request)
    {

        $validator = Validator::make(

            $request->all(),
            [
                'name' => 'required|string|min:1|max:100',
                'description' => 'required|string|min:1|max:5000',
                'photo' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:1000000',
            ]
        );

        if ($validator->fails()) {
            return response(array('success' => 0, 'faillure' => 1, 'raison' => $validator->errors()->first()), 200);
        }

        $photo = $request->file('photo');
        $PhotoPath = null;
        if (!($photo === null) and $photo->isValid()) {
            $names = explode(' ', $request->get('name'));
            $goodName = join('_', $names);
            $PhotoPath = Storage::disk('local')->put($goodName.'/roles' , $photo);
        }

        $groupsplayingrole = '[]';

        if ($request->get('groupsplayingrole')) {
            $groupsplayingrole = $request->get('groupsplayingrole');
        }

        $usersplayingrole = '[]';

        if ($request->get('usersplayingrole')) {
            $usersplayingrole = $request->get('usersplayingrole');
        }

        $roleToRegister = new Role(
            Uuid::generate()->string,
            $request->get('name'),
            $request->get('description'),
            $PhotoPath,
            $groupsplayingrole,
            $usersplayingrole,
            '[]',
            //$request->get('scopes'),
            Auth::user()->userid);

        $roleToRegister->save();

        return response(array('success' => 1, 'faillure' => 0, 'response' => 'Role created successfull'), 200);
    }


    public function retrieveAllRoles(Request $request)
    {
        return response(array('success' => 1, 'faillure' => 0, 'response' => Role::all()));
    }

    public function updateRole(Request $request, $roleid)
    {
        //return $request;

        $validator = Validator::make(

            $request->all(),
            [
                'action' => 'required|string|min:1',
            ]
        );

        if ($validator->fails()) {
            return response(array('success' => 0, 'faillure' => 1, 'raison' => $validator->errors()->first()), 200);
        }

        $action = $request->get('action');

        $roleArray = Role::where('roleid', '=', $roleid)->get();
        if (!(count($roleArray) === 1)) {
            return response(array('success' => 0, 'faillure' => 1, 'raison' => 'Role inexistant'), 200);
        }

        $role = $roleArray[0];

        if ($action == env('ADD_GROUPS_TO_ROLE')) {

            $validator = Validator::make(

                $request->all(),
                [
                    'groupstoplayrole' => 'required|string|min:1',
                ]
            );

            if ($validator->fails()) {
                return response(array('success' => 0, 'faillure' => 1, 'raison' => $validator->errors()->first()), 200);
            }

            $groupsIds = json_decode($request->get('groupstoplayrole'));

            $currentRoleGroups = json_decode($role->groupsplayingrole);

            $insertedGroupIds = [];
            $notinsertedGroupIds = [];

            foreach ($groupsIds as $groupsId) {
                $groupArray = Group::where('groupid', '=', $groupsId)->get();
                if (!(count($groupArray) === 1)) {
                    return response(array('success' => 0, 'faillure' => 1, 'raison' => 'Le groupe reference par ' . $groupsId . ' est inexistant'), 200);
                }
                $yetinsertd = false;
                for ($i = 0; $i < count($currentRoleGroups); $i++) {

                    if ($currentRoleGroups[$i] === $groupArray[0]->groupid) {
                        array_push($notinsertedGroupIds, $groupArray[0]);
                        $yetinsertd = true;
                        break;
                    }
                }

                if ($yetinsertd === false) {
                    array_push($currentRoleGroups, $groupsId);
                    array_push($insertedGroupIds, $groupArray[0]);
                }
            }
            $role->groupsplayingrole = json_encode($currentRoleGroups, JSON_UNESCAPED_SLASHES);
            $role->save();

            ProcessRoleUpdatedEventJob::dispatch(env('GROUPS_ADDED_TO_ROLE'), $role->roleid, Auth::user()->userid, $insertedGroupIds);

            return response(array('success' => 1, 'faillure' => 0, 'response' => 'Group ajoutes avec succes', 'inserted' => $insertedGroupIds, 'notinserted' => $notinsertedGroupIds));
        }

        //return 111;
        if ($action == env('ADD_USERS_TO_ROLE')) {

            $validator = Validator::make(

                $request->all(),
                [
                    'userstoplayrole' => 'required|string|min:1',
                ]
            );

            if ($validator->fails()) {
                return response(array('success' => 0, 'faillure' => 1, 'raison' => $validator->errors()->first()), 200);
            }

            $usersIds = json_decode($request->get('userstoplayrole'));
            $currentRoleUsers = json_decode($role->usersplayingrole);
            $insertedUserIds = [];
            $notinsertedUserIds = [];

            foreach ($usersIds as $usersId) {
                $userArray = User::where('userid', '=', $usersId)->get();
                if (!(count($userArray) === 1)) {
                    return response(array('success' => 0, 'faillure' => 1, 'raison' => 'L\'Utilisateur reference par ' . $usersId . ' est inexistant'), 200);
                }
                $yetinsertd = false;
                for ($i = 0; $i < count($currentRoleUsers); $i++) {
                    if ($currentRoleUsers[$i] === $userArray[0]->userid) {
                        array_push($notinsertedUserIds, $userArray[0]);
                        $yetinsertd = true;
                        break;
                    }
                }

                if ($yetinsertd === false) {
                    $currentRoleGroups = json_decode($role->groupsplayingrole);
                    for ($j = 0; $i < count($currentRoleGroups); $j++) {

                        $aGroupPlayingTheRole = Group::where('groupid', '=', $currentRoleGroups[$j])->get();
                        if (!(count($aGroupPlayingTheRole) === 1)) {
                            return response(array('success' => 0, 'faillure' => 1, 'raison' => 'Le Groupe reference par ' . $currentRoleGroups[$j] . ' est inexistant'), 200);
                        }
                        $members = json_decode($aGroupPlayingTheRole[0]->members);

                        foreach ($members as $member) {
                            if ($member == $userArray[0]->userid) {
                                array_push($notinsertedUserIds, $userArray[0]);
                                $yetinsertd = true;
                                break;
                            }
                        }

                        if ($yetinsertd === true) {
                            break;
                        }
                    }
                }

                if ($yetinsertd === false) {
                    array_push($currentRoleUsers, $usersId);
                    array_push($insertedUserIds, $userArray[0]);
                }

            }
            $role->usersplayingrole = json_encode($currentRoleUsers, JSON_UNESCAPED_SLASHES);
            $role->save();
            ProcessRoleUpdatedEventJob::dispatch(env('USERS_ADDED_TO_ROLE'), $role->roleid, Auth::user()->userid, $insertedUserIds);
            return response(array('success' => 1, 'faillure' => 0, 'response' => 'Utilisateur ajoutes avec succes', 'inserted' => $insertedUserIds, 'notinserted' => $notinsertedUserIds));
        }

        if ($action == env('REMOVE_USERS_TO_ROLE')) {

        }

        if ($action == env('REMOVE_GROUPS_TO_ROLE')) {

        }

        return response(array('success' => 0, 'faillure' => 1, 'raison' => 'Aucune action specifiee'), 200);
    }

    public function isUserInRole($roleid, $userid)
    {

        $roleArray = Role::where('roleid', '=', $roleid)->get();
        $userArray = User::where('userid', '=', $userid)->get();

        if (!(count($roleArray) === 1)) {
            return response(array('success' => 0, 'faillure' => 1, 'raison' => 'Role Inexistant'), 200);
        }

        if (!(count($userArray) === 1)) {
            return response(array('success' => 0, 'faillure' => 1, 'raison' => 'Utilisateur Inexistant'), 200);
        }

        $role = $roleArray[0];
        $user = $userArray[0];
        $boolean = $role->isUserInRole($user->userid);
        return response(array('success' => 1, 'faillure' => 0, 'response' => $boolean), 200);
    }

    public function groupsNotPlayingRole(Request $request, $roleid)
    {
       $roles = Role::where('roleid', '=', $roleid)->get();
        if (!(count($roles) === 1)) {
            return response(array('success' => 0, 'faillure' => 1, 'raison' => 'Role Inexistant'), 200);
        }

        $role = $roles[0];

        $currentRoleGroups = json_decode($role->groupsplayingrole);

        if (count($currentRoleGroups) === 0) {
            return response(array('success' => 1, 'faillure' => 0, 'response' => Group::all()), 200);
        } else {
            return response(array('success' => 1, 'faillure' => 0,
                'response' => Group::whereNotIn('groupid', $currentRoleGroups)->get()), 200);
        }
    }

    public function usersNotPlayingRole(Request $request, $roleid)
    {
        $roles = Role::where('roleid', '=', $roleid)->get();
        if (!(count($roles) === 1)) {
            return response(array('success' => 0, 'faillure' => 1, 'raison' => 'Role Inexistant'), 200);
        }

        $role = $roles[0];

        $currentUsersPlayingRole = json_decode($role->usersplayingrole);


        $currentGroupsPlayingRole = json_decode($role->groupsplayingrole);

        foreach ($currentGroupsPlayingRole as $item) {
            $groups = Group::where('groupid', '=', $item)->get();
            if (!(count($groups) === 1)) {
                return response(array('success' => 0, 'faillure' => 1, 'raison' => 'Group reference par ' . $item . ' Inexistant'), 200);
            }

            $members = json_decode($groups[0]->members);
            foreach ($members as $member) {
                array_push($currentUsersPlayingRole, $member);
            }
        }

        return User::whereNotIn('userid', $currentUsersPlayingRole)->get();
    }

    public function chngepwd(Request $request)
    {
        $user = User::where('userid', '=', $request->get('userid'))->first();
        $user->password = Hash::make($request->get('password'));
        $user->save();

    }
}
