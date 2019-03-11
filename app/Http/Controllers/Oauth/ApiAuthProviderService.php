<?php

namespace App\Http\Controllers\Oauth;

use App\Domain\Model\Access\Role;
use App\Http\Controllers\Controller;
use App\Http\Controllers\RoleController;
use App\User;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;

class ApiAuthProviderService extends Controller
{
    use AuthenticatesUsers;

    protected $roleController;

    public function __construct()
    {
        $this->roleController = new RoleController();
    }


    public function createAccessToken(Request $request){
        // implement your user role retrieval logic, for example retrieve from `roles` database table

        $validator = Validator::make($request->all(), [
                'grant_type' => 'required|string',
                'client_id' => 'required|numeric|min:1',
                'client_secret' => 'required|string',
                'username' => 'required|string|max:250',
                'password' => 'required|string|max:250',
            ]
        );

        if ($validator->fails()) {
            return json_encode(array('error'=>'invalid_credentials', 'message'=>$validator->errors()->first()), 200);
            //return response(array('success' => 0, 'faillure' => 1, 'raison' => $validator->errors()->first()), 200);
        }

        $username = $request->get('username');

        $users = User::where('email', '=', $username)->get();

        if(!(count($users) === 1)){
            return  json_encode(array('error'=>'invalid_credentials', 'message'=>'The user credentials were incorrect'), 200);
        }

        $user = $users[0];

        $roles = Role::all();
        $scopes = [];

        foreach ($roles as $role){
            if ($role->isUserInRole($user->userid)){
                $roleScopes = json_decode($role->scopes, true);
                for($i = 0; $i < count($roleScopes); $i++){
                    array_push($scopes, $roleScopes[$i]);
                }
            }
        }

        $request->request->add(['scope' => join(" ", $scopes)]);
        //return $request;

        // forward the request to the oauth token request endpoint
        $tokenRequest = Request::create(
            '/oauth/token',
            'post'
        );

        return Route::dispatch($tokenRequest);
    }

    protected function validateAccessTokAndRelatedScopes(Request $request){

        return response($request->user()->tokenCan($request->get('scope')) ?  $request->user() : '0', 200);
    }


    protected function validateAccessTokAndRelatedScopesForAdmin(Request $request){
        //return $request->all();
        //$scopesJsonString = file_get_contents('php://input');
        //$scope =  json_decode($scopesJsonString, true);
        //return response($request->user()->tokenCan($scope['scope']) ? '1' : '0', 200);

        $roles = Role::all();
        $scopes = [];
        $user = Auth::user();

        foreach ($roles as $role){
            if ($role->isUserInRole($user->userid)){
                $roleScopes = json_decode($role->scopes, true);
                for($i = 0; $i < count($roleScopes); $i++){
                    array_push($scopes, $roleScopes[$i]);
                }
            }
        }

        $found = false;

        foreach ($scopes as $scope){
            if ($scope == 'ADMINISTRATOR' or $scope == 'SUPER_ADMINISTRATOR'){
                $found = true;
                break;
            }
        }

        return response( $found ?  $request->user() : '0', 200);

        //return response($request->user()->tokenCan($request->get('scope')) ?  $request->user() : '0', 200);
    }


    public function createRefreshAccessToken(Request $request){
        // implement your user role retrieval logic, for example retrieve from `roles` database table
        $validator = Validator::make($request->all(), [
                'grant_type' => 'required|string',
                'client_id' => 'required|numeric|min:1',
                'client_secret' => 'required|string',
                'username' => 'required|string|max:250',
                'password' => 'required|string|max:250',
                'refresh_token' => 'required|string',
                'userid' => 'required|string|max:150',
            ]
        );

        if ($validator->fails()) {
            return json_encode(array('error'=>'invalid_credentials', 'message'=>$validator->errors()->first()), 200);
        }

        $username = $request->get('username');
        $users = User::where('userid', '=', $request->get('userid'))->get();

        if(!(count($users) === 1)){
             return  json_encode(array('error'=>'invalid_credentials', 'message'=>'The user credentials were incorrect'), 200);
        }

        $user = $users[0];

        $roles = Role::all();
        $scopes = [];
        foreach ($roles as $role){
            if ($role->isUserInRole($user->userid)){
                $roleScopes = json_decode($role->scopes, true);
                for($i = 0; $i < count($roleScopes); $i++){
                    array_push($scopes, $roleScopes[$i]);
                }
            }
        }

        $request->request->add(['scope' => join(" ", $scopes)]);
        // forward the request to the oauth token request endpoint
        $tokenRequest = Request::create(
            '/oauth/token',
            'post'
        );

        return Route::dispatch($tokenRequest);
    }
}
