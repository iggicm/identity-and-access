<?php

use Illuminate\Http\Request;
use Pusher\Pusher;
use Pusher\PusherException;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
///
///
///
///             APPLICATIONS
///
///
////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

Route::post('applications', 'UserController@registerApplication');
Route::post('applications/{applicationid}', 'UserController@makeDecisionOnApplication')->middleware(['auth:api','scope:'.env('SUPER_ADMINISTRATOR').','.env('ADMINISTRATOR')]);
Route::get('applications', 'UserController@getApplications')->middleware(['auth:api','scope:'.env('SUPER_ADMINISTRATOR').','.env('ADMINISTRATOR')]);
Route::get('applications/{applicationid}/photo', 'UserController@getApplicationPhoto');//->middleware(['auth:api','scope:'.env('SUPER_ADMINISTRATOR').','.env('ADMINISTRATOR')]);



////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
///
///
///
///                 ROLES
///
///
////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

Route::post('/roles', 'RoleController@createNewRole')->middleware(['auth:api','scope:'.env('SUPER_ADMINISTRATOR').','.env('ADMINISTRATOR')]);
Route::get('/roles', 'RoleController@retrieveAllRoles')->middleware(['auth:api','scope:'.env('SUPER_ADMINISTRATOR').','.env('ADMINISTRATOR')]);
Route::post('/roles/{roleid}', 'RoleController@updateRole')->middleware(['auth:api','scope:'.env('SUPER_ADMINISTRATOR').','.env('ADMINISTRATOR')]);
Route::get('/roles/{roleid}/is-user-in-role/{userid}', 'RoleController@isUserInRole')->middleware(['auth:api','scope:'.env('SUPER_ADMINISTRATOR').','.env('ADMINISTRATOR')]);
Route::get('/roles/{roleid}/groups-not-playing-role', 'RoleController@groupsNotPlayingRole')->middleware(['auth:api','scope:'.env('SUPER_ADMINISTRATOR').','.env('ADMINISTRATOR')]);
Route::get('/roles/{roleid}/users-not-playing-role', 'RoleController@usersNotPlayingRole')->middleware(['auth:api','scope:'.env('SUPER_ADMINISTRATOR').','.env('ADMINISTRATOR')]);
Route::post('/administratorpropositions', 'AdministratorpropositionController@createAdministratorProposition')->middleware(['auth:api','scope:'.env('SUPER_ADMINISTRATOR').','.env('ADMINISTRATOR')]);
Route::post('/administratorpropositions/{administratorpropositionid}', 'AdministratorpropositionController@signeAdministratorProposition')->middleware(['auth:api','scope:'.env('SUPER_ADMINISTRATOR').','.env('ADMINISTRATOR')]);
Route::get('/administratorpropositions', 'AdministratorpropositionController@getAdministratorPropositions')->middleware(['auth:api','scope:'.env('SUPER_ADMINISTRATOR').','.env('ADMINISTRATOR')]);


////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
///
///
///
///         USERS
///
///
////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

Route::get('users', 'UserController@getUsers')->middleware(['auth:api']);
Route::post('users/login', 'UserController@login');
Route::post('users/{username}/password-reset-request', 'UserController@requestPasswordReset');
Route::post('users/{username}/password-reset/{passwordresetinvitationid}', 'UserController@resetPassword');
Route::post('/users/{userid}/change-password', 'UserController@changePassword')->middleware(['auth:api']);
Route::post('/users/{userid}', 'UserController@updateUser')->middleware(['auth:api']);
Route::post('/users/{userid}/logout', 'UserController@logout')->middleware(['auth:api']);
Route::post('/users/refreshtoken', 'Oauth\ApiAuthProviderService@createRefreshAccessToken');
Route::post('/confirm-access-token', 'Oauth\ApiAuthProviderService@validateAccessTokAndRelatedScopes')->middleware(['auth:api']);

Route::get('/confirm-admin-access-token', 'Oauth\ApiAuthProviderService@validateAccessTokAndRelatedScopesForAdmin')->middleware(['auth:api'/*, 'scope:'.env('SUPER_ADMINISTRATOR').','.env('ADMINISTRATOR')*/]);
Route::post('/confirm-admin-access-token', 'Oauth\ApiAuthProviderService@validateAccessTokAndRelatedScopesForAdmin')->middleware(['auth:api'/*, 'scope:'.env('SUPER_ADMINISTRATOR').','.env('ADMINISTRATOR')*/]);

Route::get('/users-administrator', 'UserController@getAdministrators')->middleware(['auth:api', 'scope:'.env('SUPER_ADMINISTRATOR').','.env('ADMINISTRATOR')]);
Route::get('/users-not-admin-and-not-proposed', 'UserController@getUsersNotAdministrators')->middleware(['auth:api', 'scope:'.env('SUPER_ADMINISTRATOR').','.env('ADMINISTRATOR')]);

Route::post('/revokedusers/{userid}', 'UserController@revokeUser')->middleware(['auth:api', 'scope:'.env('SUPER_ADMINISTRATOR').','.env('ADMINISTRATOR')]);
Route::post('/userrevokationpropositions/{userrevokationpropositionid}', 'UserController@signUserRevokationProposition')->middleware(['auth:api', 'scope:'.env('SUPER_ADMINISTRATOR').','.env('ADMINISTRATOR')]);

Route::get('/members', 'UserController@getMembersForRevokation')->middleware(['auth:api', 'scope:'.env('SUPER_ADMINISTRATOR').','.env('ADMINISTRATOR')]);

Route::get('/userrevokationpropositions-created', 'UserController@getRevocationPropositionCreted')->middleware(['auth:api', 'scope:'.env('SUPER_ADMINISTRATOR').','.env('ADMINISTRATOR')]);


Route::get('users-cities', 'UserController@cities');

Route::get('testpusher', 'UserController@push');

Route::get('users/{userid}/photo', 'UserController@getUserPhoto');//->middleware(['auth:api','scope:'.env('SUPER_ADMINISTRATOR').','.env('ADMINISTRATOR')]);


////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
///
///
///
///                 Groups
///
///
////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

Route::post('/groups', 'GroupController@createNewGroup')->middleware(['auth:api','scope:'.env('SUPER_ADMINISTRATOR').','.env('ADMINISTRATOR')]);
Route::post('/groups/{groupid}', 'GroupController@update')->middleware(['auth:api','scope:'.env('SUPER_ADMINISTRATOR').','.env('ADMINISTRATOR')]);
Route::get('/groups', 'GroupController@retrieveAllGroup')->middleware(['auth:api','scope:'.env('SUPER_ADMINISTRATOR').','.env('ADMINISTRATOR')]);
Route::get('/groups/{groupid}/not-members', 'GroupController@notGroupMembers')->middleware(['auth:api','scope:'.env('SUPER_ADMINISTRATOR').','.env('ADMINISTRATOR')]);


/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
///
///
///
///
///         UTILS
///
///
///
///
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

Route::get('interestcenters', 'UserController@interestcenters');

Route::post('wstest', function (Request $request){

    $options = array(
        'cluster' => 'eu',
        'useTLS' => false
    );

    $data['message'] = array('id'=>rand(), 'name'=>'Moi meme',
        'message'=>$request->get('message'), 'date'=>new DateTime());
    //$event =  new \App\Providers\UserTested($data);

    try {
        $pusher = new Pusher(env('PUSHER_APP_KEY'),//'db07cb8dbf0131afd0f6',
            env('PUSHER_APP_SECRET'),
            env('PUSHER_APP_ID'),
            $options, "http://127.0.0.1", 3030, 1000000);
        //'hello world';
        $pusher->trigger('test', 'test', $data);

    } catch (PusherException $e) {
        $fp = fopen("error.txt", "w");
        fprintf($fp, "%s", $e->getMessage());
        fclose($fp);
    }

    /*broadcast(new \App\Events\TestEvent([$request->get('message')]));*/
    return response(['success'=>1, 'faillure'=>0, 'response'=>'broadcast successfully'], 200);
});



