<?php

namespace App\Http\Controllers;

use App\Domain\Model\Access\AdministratorProposition;
use App\Domain\Model\Access\AdministratorSignature;
use App\Domain\Model\Access\Role;
use App\Domain\Model\Identity\Application;
use App\Domain\Model\Identity\Group;
use App\Domain\Model\Identity\PasswordResetInvitation;
use App\Domain\Model\Identity\RevokedUser;
use App\Domain\Model\Identity\UserDescriptor;
use App\Domain\Model\Identity\UserDetails;
use App\Domain\Model\Identity\UserRevokationProposition;
use App\Http\Controllers\Oauth\ApiAuthProviderService;
use App\Interestcenter;
use App\Jobs\ProcessRestCall;
use App\Jobs\ProcessSendEmail;
use App\Mail\MailApplicationAccepted;
use App\Mail\MailApplicationArrived;
use App\Mail\MailApplicationDenied;
use App\Mail\MailApplicationReceived;
use App\Mail\MailNotificator;
use App\User;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Laravel\Passport\Token;
use Propaganistas\LaravelIntl\Facades\Country;
use Pusher\Pusher;
use Pusher\PusherException;
use Webpatser\Uuid\Uuid;

class UserController extends Controller
{
    protected $apiAuthProviderService;
    protected $pusher;
    public function __construct()
    {
        $this->apiAuthProviderService =  new ApiAuthProviderService();

        $options = array(
            'cluster' => 'eu',
            'useTLS' => true
        );

        try {
            $this->pusher = new Pusher(env('PUSHER_APP_KEY'),//'db07cb8dbf0131afd0f6',
                env('PUSHER_APP_SECRET'),
                env('PUSHER_APP_ID'),
                $options);
        } catch (PusherException $e) {
            $fp = fopen("error.txt", "w");
            fprintf($fp, "%s", $e->getMessage());
            fclose($fp);
        }
    }

    public function is_JSON($args) {
        json_decode($args);
        return json_last_error();
    }

    public  function requestPasswordReset(Request $request, $email){

        $usersToResetPassword = User::where('email', '=', $email)->get();

        if(!(count($usersToResetPassword) === 1)){
            return response(array('success'=>0, 'faillure'=>1, 'raison'=>'Aucun utilisateur ayant pour identifiant "'. $email .'" trouve dans le system'));
        }


        $userToResetPassword = $usersToResetPassword[0];

        if(!($userToResetPassword->enablement)){
            return response(array('success'=>0, 'faillure'=>1, 'raison'=>'Compte Bloque'));
        }

        $currentValidPasswordResetInvitations = PasswordResetInvitation::where('email', '=', $email)->where('used', '=', false)->get();

        if(count($currentValidPasswordResetInvitations) === 1){

            $currentValidPasswordResetInvitation = $currentValidPasswordResetInvitations[0];
            //$url = env('HOST_WEB_CLIENT').'/users/' . $userToResetPassword->userid . '/password-reset/'.$currentValidPasswordResetInvitation->invitationid;

            $to =  $userToResetPassword->email;

            $template = 'email.invite-to-reset-password';
            $from = env('MAIL_USERNAME');

            $mailNotificator = new MailNotificator($from,$template,$currentValidPasswordResetInvitation, $userToResetPassword);

            ProcessSendEmail::dispatch($to, $mailNotificator);

            return response(array('success' => 1, 'faillure' => 0, 'response' => 'Un lien a ete envoye a l\'email "' . $to . '". Veuillez utiliser ce lien pour reconfigurer votre mot de passe'), 200);


        }elseif ((count($currentValidPasswordResetInvitations) > 1)){

            return response(array('success' => 0, 'faillure' => 1, 'raison' => 'Multiple invitation trouve'), 200);

        }else{


            $uuid = Uuid::generate()->string;
            $url = env('HOST_WEB_CLIENT') . 'reset-password/' . $userToResetPassword->email . '/' . $uuid;
            $passwordResetInvitation = new PasswordResetInvitation(
                $uuid,
                $userToResetPassword->firstname,
                $userToResetPassword->lastname,
                $userToResetPassword->email,
                $url
            );


            DB::beginTransaction();

            try {

                $passwordResetInvitation->save();


            } catch (\Exception $e) {

                DB::rollBack();

                return response(array('success' => 0, 'faillure' => 1, 'raison' => 'Unable to Create a Password Reset Invitation ' . $e->getMessage()), 200);

            }

            DB::commit();


            $to = $userToResetPassword->email;
            $from = env('MAIL_USERNAME');
            $template = 'email.invite-to-reset-password';
            $mailNotificator = new MailNotificator($from,$template,$passwordResetInvitation, $userToResetPassword);

            /*$mailNotificator = new MailNotificator("PASSWORD RESET",
                [' ', 'Dear  ' . $userToResetPassword->firstname, 'Please use the link below to reset your password', ' ', 'Sincerely,'],
                $url, 'mails.password-reset-temp');*/

            ProcessSendEmail::dispatch($to, $mailNotificator);
            return response(array('success' => 1, 'faillure' => 0, 'response' => 'Un lien a ete envoye a l\'email "' . $to .
                '". Veuillez utiliser ce lien pour reconfigurer votre mot de passe'), 200);
        }
    }

    public  function resetPassword(Request $request, $email, $invitationid){

        $validator = Validator::make(
            $request->all(),
            [
                'newpassword' => 'required|string|min:6|max:150',
                'newpassword_confirmation' => 'required|string|min:6|max:150',
            ]
        );

        if ($validator->fails()) {
            return response(array('success' => 0, 'faillure' => 1, 'raison' => $validator->errors()->first()), 200);
        }

        if (!($request->get('newpassword') === $request->get('newpassword_confirmation'))){
            return response(array('success' => 0, 'faillure' => 1, 'raison' => 'Le Mot de passe et sa confirmation sont distincts'), 200);
        }


        DB::beginTransaction();

        try {

            //return $invitationid;
            $currentValidPasswordResetInvitations = PasswordResetInvitation::where('invitationid', '=', $invitationid)->
            where('email', '=', $email)->where('used', '=', false)->get();

            //return $currentValidPasswordResetInvitations;

            if(!(count($currentValidPasswordResetInvitations) === 1)){

                return response(array('success'=>0, 'faillure'=>1, 'raison'=>'Invitation Non existant ou Lien deja utilise'));

            }

            $usersToResetPassword = User::where('email', '=', $email)->get();

            if(!(count($usersToResetPassword) === 1)){
                return response(array('success'=>0, 'faillure'=>1, 'raison'=>'Utilisateur inexistant dans le projet'));
            }


            $userToResetPassword = $usersToResetPassword[0];

            if(!($userToResetPassword->enablement)){
                return response(array('success'=>0, 'faillure'=>1, 'raison'=>'Utilisateur Bloque'));
            }

            $newpassword = $request->get('newpassword');


            $currentValidPasswordResetInvitations[0]->used = true;


            $userToResetPassword->password = Hash::make($newpassword);
            $userToResetPassword->mustresetpassword = false;

            $userToResetPassword->save();
            $currentValidPasswordResetInvitations[0]->save();


        } catch (\Exception $e) {
            DB::rollBack();
            return response(array('success' => 0, 'faillure' => 1, 'raison' => 'Unable to reset Password'), 200);

        }
        DB::commit();

        $params = [
            'grant_type' => 'client_credentials',
            'client_id' => env('DEBATE_CLIENT_ID'),
            'client_secret' => env('DEBATE_CLIENT_SECRET'),
        ];

        $data = [
            /*'userid'=>$userToResetPassword->userid,
            'firstname'=>$userToResetPassword->firstname,
            'lastname'=>$userToResetPassword->lastname,
            'gender'=>$userToResetPassword->gender,
            'photo'=>$userToResetPassword->photo,
            'country'=>$userToResetPassword->country,
            'cityofresidency'=>$userToResetPassword->cityofresidency,
            'interestcenter'=>$userToResetPassword->interestcenter,
            'email'=>$userToResetPassword->email,
            'phone'=>$userToResetPassword->phone,
            'enablement'=>$userToResetPassword->enablement,
            'accepted_by'=>$userToResetPassword->accepted_by,
            'commenttotheregistration'=>$userToResetPassword->commenttotheregistration,
            'mustresetpassword'=>$userToResetPassword->mustresetpassword,*/
            'password'=>$newpassword,
            /*'latitude'=>$userToResetPassword->latitude,
            'longitude'=>$userToResetPassword->longitude*/
        ];

        ProcessRestCall::dispatch(env('HOST_DEBATE'), $params, env('HOST_DEBATE') . 'api/members/'.$userToResetPassword->userid.'?action='.User::RESET_PASSWORD,$data);

        //$userToResetPassword->password = $newpassword;
        //ProcessUserChangePassword::dispatch(env('USER_PASSWORD_CHANGE_SSL_EXCHANGE'), env('RABBIT_MQ_EXCHANGE_TYPE'), json_encode($userToResetPassword));
        return response(array('success' => 1, 'faillure' => 0, 'response' => 'Password Reset successfully'), 200);
    }

    /*public function completeRegistration(Request $request, $responsibleid){

        $countries = join (',',array_keys(Country::all()));
        $validator = Validator::make($request->all(),
            [
                'birthdate' => 'required|string|max:250',
                'placeofbirth' => 'required|string|max:250',
                'documenttype'=> 'required|string|max:250',
                'documentid' => 'required|string|max:250',
                'password' => 'required|string|max:250',
                'password_confirmation' => 'required|string|max:250',
            ]
        );


        if ($validator->fails()) {
            return response(array('success' => 0, 'faillure' => 1, 'raison' => $validator->errors()->first()), 200);
        }

        if (!($request->get('password') === $request->get('password_confirmation'))){
            return response(array('success' => 0, 'faillure' => 1, 'raison' => 'Le Mot de passe et sa confirmation sont distincts'), 200);
        }


        $userInvitationToChoosePwds = UserInvitationToChoosePassword::where('userid','=', $responsibleid)->get();
        if (!(count($userInvitationToChoosePwds) === 1)){
            return response(array('success' => 0, 'faillure' => 1, 'raison' => "Invitation Obsolete"), 200);
        }

        $responsible = User::where('userid', '=', $userInvitationToChoosePwds[0]->userid)->first();

        DB::beginTransaction();

        try {

            $responsible->birthdate = $request->get('birthdate');
            $responsible->placeofbirth = $request->get('placeofbirth');
            $responsible->documenttype = $request->get('documenttype');
            $responsible->documentid = $request->get('documentid');
            $responsible->password = Hash::make($request->get('password'));
            $responsible->enablement = true;

            if (!($request->get('firstname') == null)){
                $responsible->firstname = $request->get('firstname');
            }

            if (!($request->get('lastname') == null)){
                $responsible->lastname = $request->get('lastname');
            }

            if (!($request->get('gender') == null)){
                $responsible->gender = $request->get('gender');
            }

            $photo = $request->file('photo');
            $photoPath = null;
            if (!($photo === null) and $photo->isValid()) {
                $validator = Validator::make($request->all(),
                    [
                        'photo' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:1000000',

                    ]
                );

                if ($validator->fails()) {
                    return response(array('success' => 0, 'faillure' => 1, 'raison' => $validator->errors()->first()), 200);
                }

                $photoPath = Storage::disk('local')->put('users' , $photo);
                $responsible->photo = $photoPath;
            }

            if (!($request->get('country') == null)){
                $responsible->country = $request->get('country');
            }

            if (!($request->get('countryname') == null)){
                $responsible->countryname = $request->get('countryname');
            }

            if (!($request->get('townname') == null)){
                $responsible->townname = $request->get('townname');
            }

            if (!($request->get('phone') == null)){
                $validator = Validator::make($request->all(),
                    [
                        'phone' => 'phone:'.$countries,

                    ]
                );

                if ($validator->fails()) {
                    return response(array('success' => 0, 'faillure' => 1, 'raison' => $validator->errors()->first()), 200);
                }

                $responsible->phone = $request->get('phone');
            }

            $responsible->save();
            $userInvitationToChoosePwds[0]->delete();
        } catch (\Exception $e) {
            DB::rollBack();
            return response(array('success' => 0, 'faillure' => 1, 'raison' => $e->getMessage()), 200);

        }
        DB::commit();
        return response(array('success' => 1, 'faillure' => 0, 'response' => 'Enregistrement complete avec succes'), 200);
    }*/

    public function cities(Request $request){
        //MyModel::distinct()->get(['column_name']);

        return response(array('success' => 1, 'faillure' => 0, 'response' => User::distinct()->get(['cityofresidency'])), 200);

    }

    public function interestcenters(Request $request){
        return response(array('success' => 1, 'faillure' => 0, 'response' => Interestcenter::all()), 200);
    }

    public function getApplications(Request $request){
        return response(array('success' => 1, 'faillure' => 0, 'response' => Application::all()), 200);
    }


    public function registerApplication(Request $request){
       //return $request->all();
        $countries = join (',',array_keys(Country::all()));
        $validator = Validator::make($request->all(),
            [
                'firstname' => 'required|string|max:250',
                'lastname' => 'required|string|max:250',
                'gender' => 'required|string|max:250',
                'photo'=> 'required|image|mimes:jpeg,png,jpg,gif,svg|max:10000000',
                'country' => 'required|string|min:2|max:2',
                'cityofresidency' => 'required|string|max:250',
                'interestcenter' => 'required|string|max:5000',
                'email' => 'required|email|max:250',
                'phone' => 'phone:'.$countries,
                'commenttotheregistration'=>'required|string|max:5000',
                'latitude' => 'required|numeric',
                'longitude' => 'required|numeric',

            ]
        );


        if ($validator->fails()) {
            return response(array('success' => 0, 'faillure' => 1, 'raison' => $validator->errors()->first()), 200);
        }

        $utilisateur = User::where('email', '=', $request->get('email'))->first();
        if (!($utilisateur === null)){
            return response(array('success' => 0, 'faillure' => 1, 'raison' =>'E-Mail "'.$request->get('email').'" deja pris'), 200);
        }

        $photo = $request->file('photo');
        $photoPath = '';
        if (!($photo === null) and $photo->isValid()) {
            $photoPath = Storage::disk('local')->put('application' , $photo);
        }

        try {

            $application = new Application(
                Uuid::generate()->string,
                $request->get('firstname'),
                $request->get('lastname'),
                $request->get('gender'),
                $photoPath,
                $request->get('country'),
                $request->get('cityofresidency'),
                $request->get('interestcenter'),
                $request->get('email'),
                $request->get('phone'),
                $request->get('commenttotheregistration'),
                Application::DEFAULT_APPLICATION_STATE,
                $request->get('latitude'),
                $request->get('longitude')
            );

            $application->save();

        }catch (\Exception $exception){
            return response(array('success' => 0, 'faillure' => 1, 'raison' =>$request->get('email') . ' deja pris.'), 200);

        }
        $administratorroles = Role::where('name', '=', 'Administrator')->get();
        $roles = [];
        foreach ($administratorroles as $administratorrole){
            array_push($roles, $administratorrole);
        }

        $template = 'email.application-arrived';
        $from = env('MAIL_USERNAME');

        foreach ($roles as $r){
            $users = $r->usersPlayingRole();
            foreach ($users as $user){
                $mailApplicationArrived = new MailApplicationArrived($from, $template, $application, $user);
                ProcessSendEmail::dispatch($user->email, $mailApplicationArrived);
            }
        }

        $templateRequestor = 'email.application-received';

        $mailApplicationReceived = new MailApplicationReceived($from, $templateRequestor, $application);


        ProcessSendEmail::dispatch($application->email, $mailApplicationReceived);

        return response(array('success' => 1, 'faillure' => 0, 'response' => 'Votre candidature a ete enregistre avec succes'), 200);
    }

    public function makeDecisionOnApplication(Request $request, $applicationid){
        $validator = Validator::make($request->all(),
            [
                //'roleid' => 'required|string|min:1|max:150',
                'action' => 'required|string|max:250',
            ]
        );
 

        if ($validator->fails()) {
            return response(array('success' => 0, 'faillure' => 1, 'raison' => $validator->errors()->first()), 200);
        }

        $application = Application::where('applicationid', '=', $applicationid)->first();
        if ($application === null){
            return response(array('success' => 0, 'faillure' => 1, 'raison' =>'Demande inexistant'), 200);
        }

        if ($request->get('action') === Application::APPLICATION_DENIED){

            $application->state = Application::APPLICATION_DENIED;
            $application->save();

            $template = 'email.application-denied';
            $from = env('MAIL_USERNAME');
            $mailApplicationDenied = new MailApplicationDenied($from, $template, $application, Auth::user());
            ProcessSendEmail::dispatch($application->email, $mailApplicationDenied);
            return response(array('success' => 1, 'faillure' => 0, 'response' => 'Demande rejetee avec succes'), 200);

        } elseif ($request->get('action') === Application::APPLICATION_ACCEPTED){

            DB::beginTransaction();

            $user = new User(
                $applicationid,
                $application->firstname,
                $application->lastname,
                $application->gender,
                $application->photo,
                $application->country,
                $application->cityofresidency,
                $application->interestcenter,
                $application->email,
                $application->phone,
                true,
                Auth::user()->userid,
                User::DEFAULT_PASSWORD,
                $application->commenttotheregistration,
                true,
                $application->latitude,
                $application->longitude,
                0
            );

            $uuid = Uuid::generate()->string;
            $url = env('HOST_WEB_CLIENT') . 'reset-password/' . $user->email . '/' . $uuid;
            //$url = env('HOST_WEB_CLIENT') . '/users/' . $user->userid . '/password-reset/' . $uuid;
            $passwordResetInvitation = new PasswordResetInvitation(
                $uuid,
                $user->firstname,
                $user->lastname,
                $user->email,
                $url
            );

            try {

                $ic = Interestcenter::where('name', '=', $application->interestcenter)->first();
                if ($ic === null){
                    Interestcenter::create(['interestcenterid'=>Uuid::generate()->string, 'name'=>$application->interestcenter, 'description'=>' ']);
                }

                $user->save();
                $passwordResetInvitation->save();

                //$uuid = Uuid::generate()->string;
                //$url = env('HOST_WEB_CLIENT') . '/users/' . $user->userid . '/password-reset/' . $uuid;

                $template = 'email.application-accepted';
                $from = env('MAIL_USERNAME');
                $applicationroleid = $application->requestedrole;
                $mailApplicationAccepted = new MailApplicationAccepted($from, $template, $user, Auth::user(), $applicationroleid, $url);
                ProcessSendEmail::dispatch($user->email, $mailApplicationAccepted);

                $application->delete();

                //Default group to user

                $group = Group::where('name', '=', 'DEFAULT GROUP')->first();

                $members = json_decode($group->members);
                array_push($members, $user->userid);
                $group->members = json_encode($members);
                $group->save();

                /////////////

                $params = [
                    'grant_type' => 'client_credentials',
                    'client_id' => env('DEBATE_CLIENT_ID'),
                    'client_secret' => env('DEBATE_CLIENT_SECRET'),
                ];


                $data = [
                    'userid'=>$user->userid,
                    'firstname'=>$user->firstname,
                    'lastname'=>$user->lastname,
                    'gender'=>$user->gender,
                    'photo'=>$user->photo,
                    'country'=>$user->country,
                    'cityofresidency'=>$user->cityofresidency,
                    'interestcenter'=>$user->interestcenter,
                    'email'=>$user->email,
                    'phone'=>$user->phone,
                    'enablement'=>$user->enablement,
                    'accepted_by'=>$user->accepted_by,
                    'commenttotheregistration'=>$user->commenttotheregistration,
                    'mustresetpassword'=>$user->mustresetpassword,
                    'password'=>User::DEFAULT_PASSWORD,
                    'latitude'=>$user->latitude,
                    'longitude'=>$user->longitude
                ];

                ProcessRestCall::dispatch(env('HOST_DEBATE'), $params, env('HOST_DEBATE') . 'api/members',$data);



                /*$options = array(
                    'cluster' => 'eu',
                    'useTLS' => true
                );*/

                //$pusher = null;
                try {
                    /*$pusher = new Pusher(env('PUSHER_APP_KEY'),//'db07cb8dbf0131afd0f6',
                        env('PUSHER_APP_SECRET'),
                        env('PUSHER_APP_ID'),
                        $options);*/

                    $data['message'] = $user;//'hello world';
                    $this->pusher->trigger('user-accepted', 'user-accepted', $data);

                } catch (PusherException $e) {
                    return response(array('success' => 0, 'faillure' => 1, 'raison' => $e->getMessage()), 200);
                }


                //$passwordResetInvitation = new PasswordResetInvitation($uuid, $user->firstname, $user->lastname, $user->email, $url);

                //$passwordResetInvitation->save();
                //$this->requestPasswordReset($request, $user->email);

            } catch (\Exception $e) {

                DB::rollBack();

                return response(array('success' => 0, 'faillure' => 1, 'raison' => 'Unable to Create a Password Reset Invitation ' . $e->getMessage()), 200);

            }

            DB::commit();
            return response(array('success' => 1, 'faillure' => 0, 'response' => 'Demande acceptee avec succes'), 200);
        }

        return response(array('success' => 0, 'faillure' => 1, 'raison' =>'Action non specifiee'), 200);

    }

    public  function login(Request $request){

        $validator = Validator::make($request->all(), [
                'grant_type' => 'required|string',
                'client_id' => 'required|numeric|min:1',
                'client_secret' => 'required|string',
                'email' => 'required|string|min:1|max:250',
                'password' => 'required|string|min:1|max:250',
                //'latitude' => 'required|numeric',
                //'longitude' => 'required|numeric',
            ]
        );

        if ($validator->fails()) {
            return response(array('success' => 0, 'faillure' => 1, 'raison' => $validator->errors()->first()), 200);
        }

        $password = $request->get('password');
        $email = $request->get('email');

        $user = User::where('email', '=', $email)->first();
        if ($user === null){
            return response(array('success' => 0, 'faillure' => 1, 'raison' =>'E-Mail ou mot de passe incorrect'), 200);
        }

        if(!($user->enablement == true)){
            return response(array('success'=>0, 'faillure' => 1, 'raison' => "E-Mail ou mot de passe incorrect ou bien utilisateur bloque"), 200);
        }

        if(($user->mustresetpassword == true)){
            return response(array('success'=>0, 'faillure' => 1, 'raison' => "Vous devez choisir votre mot de passe"), 200);
        }

        if (Hash::check($password, $user->password)){
            $request->request->add(['username' => $email]);
           // $apiAuthProviderService = new ApiAuthProviderService();

            //return $request;
        try {

            /*$fp = fopen('android.txt', 'w');
            fprintf($fp , '%s', json_encode($request->all()));
            fclose($fp);*/
            $response = $this->apiAuthProviderService->createAccessToken($request);

            if (is_string($response)){
                $object = json_decode($response);
                return response(array('success'=>0, 'faillure' => 1, 'raison' => $object->error.' (' . $object->message.')'), 200);
            }

            $status = $response->status();

            if (!($status == 200)) {
                return response(array('success' => 0, 'faillure' => 1, 'raison' => 'Application non autorisee ' . $status), 200);
            }


            $adminRoles = Role::where('name', '=', 'Administrator')->orWhere('name', 'Super Administrator')->get();
            $isAdmin = false;

            foreach ($adminRoles as $adminRole){
                if ($adminRole->isUserInRole($user->userid)){
                    $isAdmin = true;
                    break;
                }
            }

            $userdetails = new UserDetails($user->userid, $user->firstname, $user->lastname, $user->email, $user->phone, $user->enablement, $isAdmin);



            $userdescriptor = new UserDescriptor($userdetails);
            //sleep(5);


            $user->latitude = $request->get('latitude');
            $user->longitude = $request->get('longitude');
            $user->isconnected = true;

            $token = json_decode($response->content(), true);
            $token['deliver_at'] = time();
            $token['expired_at'] = time() + $token['expires_in'];

            //$user->access_token = $token['access_token'];
            $user->access_token = '';
                $user->save();


            /*$options = array(
               'cluster' => 'eu',
               'useTLS' => true
           );*/

            //$pusher = null;
            try {
                /*$pusher = new Pusher(env('PUSHER_APP_KEY'),//'db07cb8dbf0131afd0f6',
                    env('PUSHER_APP_SECRET'),
                    env('PUSHER_APP_ID'),
                    $options);*/



                $user->isAdmin = $isAdmin;

                $wasProposed = false;
                if (!$user->isAdmin){
                    $administratorProposition = AdministratorProposition::where('proposeduserid','=', $user->userid)->first();
                    if (!($administratorProposition == null)){
                        $wasProposed = true;
                    }
                }

                $user->wasProposedAsAdmin = $wasProposed;

                $user->temoins = Token::where('user_id', '=', $user->id)->orderBy('created_at', 'desc')->first();

                $data['message'] = $user;//'hello world';
                $this->pusher->trigger('user-logedin', 'user-logedin', $data);

            } catch (PusherException $e) {
                return response(array('success' => 0, 'faillure' => 1, 'raison' => $e->getMessage()), 200);
            }



            return response(array('success' => 1, 'faillure' => 0, 'response' => $userdescriptor, 'token' => $token), 200);
        }catch (\Exception $e){
            return response(array('success'=>0, 'faillure' => 1, 'raison' => $e->getMessage() . " Execption"), 200);
        }
        }
        return response(array('success'=>0, 'faillure' => 1, 'raison' => "Bad credentials"), 200);
    }

    public  function changePassword(Request $request, $userid){

        $validator = Validator::make($request->all(), [
                'currentpassword' => 'required|string|max:250',
                'newpassword' => 'required|string|min:6|max:250',
                'newpassword_confirmation' => 'required|string|min:6|max:250',
            ]
        );

        if ($validator->fails()) {
            return response(array('success' => 0, 'faillure' => 1, 'raison' => $validator->errors()->first()), 200);
        }


        $oldpassword = $request->get('currentpassword');
        $newpassword = $request->get('newpassword');
        $newpasswordconfirmation = $request->get('newpassword_confirmation');


        if(!($newpassword === $newpasswordconfirmation)){
            return response(array('success'=>0, 'faillure'=>1, 'raison'=>'Echec de confirmation du mot de passe'));
        }

        $user = User::where('userid', '=', $userid)->first();

        if ($user == null){
            return response(array('success'=>0, 'faillure'=>1, 'raison'=>'Utilisateur inexistant'));
        }

        if ($user->enablement == false){
            return response(array('success'=>0, 'faillure'=>1, 'raison'=>'Utilisateur bloque'));
        }

        if (Hash::check($oldpassword, $user->password))
        {

            $user->password = $newpassword;

            $userToPubplish = $user;

            $user->password =  Hash::make($newpassword);

            $user->mustresetpassword = false;

            $user->save();

            $userToPubplish->password = $newpassword;

            //ProcessUserChangePassword::dispatch(env('USER_PASSWORD_CHANGE_SSL_EXCHANGE'), env('RABBIT_MQ_EXCHANGE_TYPE'), json_encode($userToPubplish));

            return response(array('success'=>1, 'faillure'=>0, 'response'=>'Mot de passe modifie avec succes'));
        } else{
            return response(array('success'=>0, 'faillure'=>1, 'raison'=>'Mot de passe courant incorrect!'));

        }
    }

    public function updateUser(Request $request, $userid){
        $validator = Validator::make($request->all(), [
                'action' => 'required|string|max:250',
            ]
        );

        if ($validator->fails()) {
            return response(array('success' => 0, 'faillure' => 1, 'raison' => 'Aucune action specifiee'), 200);
        }

        $action = $request->get('action');
        if ($action == User::CHANGE_PASSWORD){
            return $this->changePassword($request, $userid);
        }
        if ($action == User::DEACTIVATE_USER){
            $user = User::where('userid', '=', $userid)->first();

            if ($user == null){
                return response(array('success'=>0, 'faillure'=>1, 'raison'=>'Utilisateur inexistant'));
            }

            if ($user->enablement == false){
                return response(array('success'=>0, 'faillure'=>1, 'raison'=>'Utilisateur deja bloque'));
            }


            $user->enablement = false;

            $userTokens = $user->tokens;

            foreach($userTokens as $token) {
                $token->revoke();
            }

            $user->save();
            //ProcessMessages::dispatch(env('DEACTIVATED_USERS_EXCHANGE'), env('RABBIT_MQ_EXCHANGE_TYPE'), json_encode($user));

            return response(array('success'=>1, 'faillure'=>0, 'response'=>'Utilisateur bloque avec succes'));
        }

        if ($action == User::REACTIVATE_USER){
            $user = User::where('userid', '=', $userid)->first();

            if ($user == null){
                return response(array('success'=>0, 'faillure'=>1, 'raison'=>'Utilisateur inexistant'));
            }

            if ($user->enablement == true){
                return response(array('success'=>0, 'faillure'=>1, 'raison'=>'Utilisateur deja actif'));
            }

            $user->enablement = true;

            $user->save();
            //ProcessMessages::dispatch(env('DEACTIVATED_USERS_EXCHANGE'), env('RABBIT_MQ_EXCHANGE_TYPE'), json_encode($user));

            return response(array('success'=>1, 'faillure'=>0, 'response'=>'Utilisateur bloque avec succes'));
        }

        return response(array('success' => 0, 'faillure' => 1, 'raison' => 'Action non supportee'), 200);
    }

    public function logout(Request $request, $userid){


        $user = User::where('userid', '=', $userid)->first();

        if ($user == null){
            return response(array('success'=>0, 'faillure'=>1, 'response'=>'User inexistant'));
        }

        //return $user;

        $userTokens = $request->user()->tokens; //$user->tokens;

        /*$fp = fopen('aaa.txt', 'w');
        fprintf($fp, '%s, %s,         %s', json_encode($userTokens), $request->header('Authorization'), $request->header('authorization'));
        fclose($fp);*/

        foreach($userTokens as $token) {

            $token->revoke();
        }

        $tokens = Token::where('user_id', '=', $user->id)->get();

        foreach ($tokens as $t){
            $t->revoked = true;
            $t->expires_at = date('Y-m-d H:i:s');

            $t->save();
        }

        $user->isconnected = false;
        $user->save();

        //$pusher = null;
        try {

            $adminRoles = Role::where('name', '=', 'Administrator')->orWhere('name', 'Super Administrator')->get();
            $isAdmin = false;

            foreach ($adminRoles as $adminRole){
                if ($adminRole->isUserInRole($user->userid)){
                    $isAdmin = true;
                    break;
                }
            }
            
            $user->isAdmin = $isAdmin;

            $wasProposed = false;
            if (!$user->isAdmin){
                $administratorProposition = AdministratorProposition::where('proposeduserid','=', $user->userid)->first();
                if (!($administratorProposition == null)){
                    $wasProposed = true;
                }
            }

            $user->wasProposedAsAdmin = $wasProposed;

            $user->temoins = Token::where('user_id', '=', $user->id)->orderBy('created_at', 'desc')->first();

            $data['message'] = $user;//'hello world';
            $this->pusher->trigger('user-logedin', 'user-logedin', $data);
        } catch (PusherException $e) {
            return response(array('success' => 0, 'faillure' => 1, 'raison' => $e->getMessage()), 200);
        }

        /*$pusher = null;
        try {


            $data['message'] = $user;//'hello world';
            $this->pusher->trigger('user-logedout', 'user-logedout', $data);

        } catch (PusherException $e) {
            return response(array('success' => 0, 'faillure' => 1, 'raison' => $e->getMessage()), 200);
        }*/

        return response(array('success'=>1, 'faillure'=>0, 'response'=>'Utilisateur deconnecte avec succes. ' , 'user'=>$user, 'userid'=>$userid));
    }


    /**
     * @SWG\Get(
     *   path="/users",
     *   summary="List of users except the user making the request",
     *   operationId="getUsers",
     *   @SWG\Parameter(
     *     name="customerId",
     *     in="path",
     *     description="Target customer.",
     *     required=true,
     *     type="integer"
     *   ),
     *   @SWG\Parameter(
     *     name="filter",
     *     in="query",
     *     description="Filter results based on query string value.",
     *     required=false,
     *     enum={"active", "expired", "scheduled"},
     *     type="string"
     *   ),
     *   @SWG\Response(response=200, description="successful operation"),
     *   @SWG\Response(response=406, description="not acceptable"),
     *   @SWG\Response(response=500, description="internal server error")
     * )
     *
     */


    public function getUsers(Request $request){
        $users = User::where('userid', '!=', $request->user()->userid)->get();
        $utilisateurs = [];

        $adminRoles = Role::where('name', '=', 'Administrator')->orWhere('name', 'Super Administrator')->get();
        foreach ($users as $user){
            $temoins = Token::where('user_id', '=', $user->id)->orderBy('created_at', 'desc')->first();
            if (!($temoins == null)){
                $temoins->revoked = (Carbon::parse($temoins->expires_at) < Carbon::now()) or $temoins->revoked;
                //$temoins->save();
                //return response(array('success' => 0, 'faillure' => 1, 'raison' => "Whoop une erreur s'est produite"), 200);
            }

            //Carbon::parse($this->attributes['expires_at']) < Carbon::now()
            //expires_at
            $user->temoins = $temoins;//json_encode($user->tokens());

            $user->isAdmin = false;

            foreach ($adminRoles as $adminRole){
                if ($adminRole->isUserInRole($user->userid)){
                    $user->isAdmin = true;
                    break;
                }
            }
            $wasProposed = false;
            if (!$user->isAdmin){
                $administratorProposition = AdministratorProposition::where('proposeduserid','=', $user->userid)->first();
                if (!($administratorProposition == null)){
                    $wasProposed = true;
                }
            }

            $user->wasProposedAsAdmin = $wasProposed;

            array_push($utilisateurs, $user);
        }

        return response(array('success'=>1, 'faillure'=>0, 'response'=> $utilisateurs/*,
            'utilisateurs'=>$utilisateurs, 'userlength'=>count($users)*/));
    }

    public function getApplicationPhoto(Request $request, $applicationid){
        $application  = Application::where('applicationid', '=', $applicationid)->first();
        if ($application === null){
            return response()->make(null, 200, array(
                'Content-Type' => (new \finfo(FILEINFO_MIME))->buffer(null)
            ));
        }
        //return $applicationid;
        $exists = Storage::disk('local')->exists($application->photo);
        if (!$exists){
            //return "file not exists";
            return response()->make(null, 200, array(
                'Content-Type' => (new \finfo(FILEINFO_MIME))->buffer(null)
            ));
        }
        $contents = Storage::disk('local')->get($application->photo);
        return response()->make($contents, 200, array(
            'Content-Type' => (new \finfo(FILEINFO_MIME))->buffer($contents)
        ));
    }

    public function getUserPhoto(Request $request, $userid){
        $user  = User::where('userid', '=', $userid)->first();
        if ($user === null){
            return response()->make(null, 200, array(
                'Content-Type' => (new \finfo(FILEINFO_MIME))->buffer(null)
            ));
        }

        $exists = Storage::disk('local')->exists($user->photo);
        if (!$exists){
            //return "file not exists";
            return response()->make(null, 200, array(
                'Content-Type' => (new \finfo(FILEINFO_MIME))->buffer(null)
            ));
        }
        $contents = Storage::disk('local')->get($user->photo);
        return response()->make($contents, 200, array(
            'Content-Type' => (new \finfo(FILEINFO_MIME))->buffer($contents)
        ));
    }



    public function push($data){
        try {

            $data['message'] = $data;//'hello world';
            $this->pusher->trigger('user-logedout', 'user-logedout', $data);
            //$this->pusher->trigger('user-logedin', 'user-logedin', $data);

        } catch (PusherException $e) {
            //return response(array('success' => 0, 'faillure' => 1, 'raison' => $e->getMessage()), 200);
        }

        //return response(array('success'=>1, 'faillure'=>0, 'response'=>'Utilisateur deconnecte avec succes'));
    }


    public function getAdministrators(Request $request){

        $role = Role::where('name', '=', 'Administrator')->first();
        if ($role == null){
            return response(array('success'=>0, 'faillure'=>1, 'raison'=> 'Aucun role pour les Administrateurs'));
        }



        return response(array('success'=>1, 'faillure'=>0, 'response'=> $role->usersPlayingRole()));

    }


    private function _getAdministrators(){
        $role = Role::where('name', '=', 'Administrator')->first();
        if ($role == null){
            return [];
        }

        return $role->usersPlayingRole();
    }

    private function getMembersNotAdministrator(){

        $revokePropositionIds = UserRevokationProposition::all('proposeduserid');
        $revokePropositionIdsArray = [];
        foreach ($revokePropositionIds as $revokePropositionId){
            array_push($revokePropositionIdsArray, $revokePropositionId->proposeduserid);
        }

        $users = User::where('enablement', '=', 1)->get();
        $adminRole = Role::where('name', '=', 'Administrator')->first();
        $superAdminRole = Role::where('name', '=', 'Super Administrator')->first();

        $retVal = [];

        foreach ($users as $user){
            if (!($adminRole->isUserInRole($user->userid) or $superAdminRole->isUserInRole($user->userid))){
                if (!in_array($user->userid, $revokePropositionIdsArray, false)){
                    array_push($retVal, $user);
                }
            }
        }
        /*$fp = fopen('ids.txt', 'w');
        fprintf($fp, '%s\n\n\n%s', json_encode($retVal), json_encode($revokePropositionIdsArray));
        fclose($fp);*/

        return $retVal;
    }

    public function getUsersNotAdministrators(Request $request){
        $adminrole = Role::where('name', '=', 'Administrator')->first();
        if ($adminrole == null){
            return response(array('success'=>0, 'faillure'=>1, 'raison'=> 'Aucun role pour les Administrateurs'));
        }

        $superadminrole = Role::where('name', '=', 'Super Administrator')->first();
        if ($superadminrole == null){
            return response(array('success'=>0, 'faillure'=>1, 'raison'=> 'Aucun role pour les Administrateurs'));
        }

        $allUsers = User::all();

        $users = [];

        foreach ($allUsers as $allUser){
            $proposition = AdministratorProposition::where('proposeduserid', '=', $allUser->userid)->first();

            /*if ($proposition == null){
                $cantBeTake = true;
            }else{
                if (($proposition->proposed_by === Auth::user()->userid)){
                    $cantBeTake = false;
                }
                $administratorssignatures = json_decode($proposition->administratorssignatures);
                $trouver = false;
                foreach ($administratorssignatures as $administratorssignature){
                    if ($administratorssignature->administator_id == Auth::user()->userid){
                        $trouver = true;
                        break;
                    }
                }
                if ($trouver == false){
                    $cantBeTake = true;
                }else{
                    $cantBeTake = false;
                }
            }*/

            if (!$adminrole->isUserInRole($allUser->userid) and !$superadminrole->isUserInRole($allUser->userid) and ($proposition == null)){
               array_push($users, $allUser);
            }
        }

        return response(array('success'=>1, 'faillure'=>0, 'response'=> $users));
    }

    public function revokeUser(Request $request, $userid){
        $user = User::where('userid', '=', $userid)->first();
        if ($user == null){
            return response(array('success'=>0, 'faillure'=>1, 'raison'=> 'Membre inconnu.'));
        }

        $userRevokation = UserRevokationProposition::where('proposeduserid', '=', $userid)->first();


        if (!($userRevokation === null)){
            return response(array('success'=>0, 'faillure'=>1, 'raison'=> 'Membre deja revoque.'));
        }

        $message = '';


        DB::beginTransaction();
        try{
            $revokedUser = new RevokedUser(
                $user->userid,
                $user->firstname,
                $user->lastname,
                $user->gender,
                $user->photo,
                $user->country,
                $user->cityofresidency,
                $user->interestcenter,
                $user->email,
                $user->phone,
                $user->enablement,
                $request->user()->userid,
                $user->password,
                $user->commenttotheregistration,
                $user->mustresetpassword,
                $user->latitude,
                $user->longitude,
                $user->isconnected,
                $user->access_token
            );


            $signature = new AdministratorSignature($request->user()->userid,date('Y-m-d H:i:s'), $request->user()->firstname.' ' . $request->user()->lastname);
            $userRevokationProposition = UserRevokationProposition::create([
                'userrevokationpropositionid'=>Uuid::generate()->string,
                'proposeduserid'=>$userid,
                'proposed_by'=>$request->user()->userid,
                'administratorssignatures'=> json_encode([$signature], JSON_UNESCAPED_SLASHES),
                'state'=> UserRevokationProposition::CREATED
            ]);


            $params = [
                'grant_type' => 'client_credentials',
                'client_id' => env('DEBATE_CLIENT_ID'),
                'client_secret' => env('DEBATE_CLIENT_SECRET'),
            ];

            $client = new Client();

            $response = $client->post(env('HOST_DEBATE').'oauth/token', [
                'form_params' => $params,
            ]);

            $token = json_decode((string) $response->getBody(), true)['access_token'];


            $res = $client->get(env('HOST_DEBATE') . 'api/have-participated-to-debates/'.$userid, [
                'headers'=>[
                    'Authorization' =>  'Bearer '.$token,
                ],
            ]);

            $aParticipe = json_decode((string) $res->getBody(), true)['response'];

            if ($aParticipe == 1){

                $message = 'Le membre ' . $user->firstname.'  '.$user->lastname . '  ne peut etre revoque par vous seul. 
                Sa revocation requiert l\'unanimite des administrateurs. Neanmoins votre signature a ete retenu. ';

            }else{

                $revokedUser->save();



                $userRevokationProposition->state = UserRevokationProposition::ACCEPTED;

                $userRevokationProposition->save();


                $params = [
                    'grant_type' => 'client_credentials',
                    'client_id' => env('DEBATE_CLIENT_ID'),
                    'client_secret' => env('DEBATE_CLIENT_SECRET'),
                ];


                $data = [];

                ProcessRestCall::dispatch(env('HOST_DEBATE'), $params, env('HOST_DEBATE') . 'api/revoke-members/'.$user->userid,$data);

                $user->delete();


                $message = 'Utilisateur revoque avec succes.';
            }
            //protected $fillable = ['userrevokationpropositionid', 'proposeduserid', 'proposed_by', 'administratorssignatures', 'state'];
            //new AdministratorSignature($member->memberid, date('Y-m-d H:i:s'), $member->firstname.' ' . $member->lastname));



        }catch (\Exception $e){
            DB::rollBack();
            return response(array('success' => 0, 'faillure' => 1, 'raison' => 'Erreur: ' . $e->getMessage()), 200);

        }
        DB::commit();

        return response(array('success' => 1, 'faillure' => 0, 'response' => $message), 200);

    }

    public function signUserRevokationProposition(Request $request, $pid){
        $userRevokationProposition = UserRevokationProposition::where('userrevokationpropositionid', '=', $pid)->first();
        if ($userRevokationProposition == null){
            return response(array('success'=>0, 'faillure'=>1, 'raison'=> 'Proposition de revocation inconnu.'));
        }

        $connectedUserId = $request->user()->userid;
        $signatures = json_decode($userRevokationProposition->administratorssignatures);
        $hasSigned = false;
        foreach ($signatures as $signature){
            if ($signature->administator_id === $connectedUserId){
                $hasSigned = true;
                break;
            }
        }

        if ($hasSigned){
            return response(array('success'=>0, 'faillure'=>1, 'raison'=> 'Vous avez deja signe.'));
        }



        array_push($signatures, new AdministratorSignature($request->user()->userid,date('Y-m-d H:i:s'), $request->user()->firstname.' ' . $request->user()->lastname));
        $userRevokationProposition->administratorssignatures = json_encode($signatures, JSON_UNESCAPED_SLASHES);
        $userRevokationProposition->save();

        /*$fp = fopen('signatures.txt', 'w');
        fprintf($fp, '%s', json_encode($signatures));
        fclose($fp);*/

        DB::beginTransaction();
        try{

        $user = User::where('userid', '=', $userRevokationProposition->proposeduserid)->first();

        $administrators = $this->_getAdministrators();

        $allHasSigned = true;

        foreach ($administrators as $administrator){
            $found = false;
            foreach ($signatures as $sign){
                if ($sign->administator_id === $administrator->userid){
                    $found = true;
                    break;
                }
            }

            if (!$found){
                $allHasSigned = false;
                break;
            }
        }



        if ($allHasSigned){

            $revokedUser = new RevokedUser(
                $user->userid,
                $user->firstname,
                $user->lastname,
                $user->gender,
                $user->photo,
                $user->country,
                $user->cityofresidency,
                $user->interestcenter,
                $user->email,
                $user->phone,
                $user->enablement,
                $userRevokationProposition->proposed_by,
                $user->password,
                $user->commenttotheregistration,
                $user->mustresetpassword,
                $user->latitude,
                $user->longitude,
                $user->isconnected,
                $user->access_token
            );

            $revokedUser->save();

            $params = [
                'grant_type' => 'client_credentials',
                'client_id' => env('DEBATE_CLIENT_ID'),
                'client_secret' => env('DEBATE_CLIENT_SECRET'),
            ];


            $data = [];

            ProcessRestCall::dispatch(env('HOST_DEBATE'), $params, env('HOST_DEBATE') . 'api/revoke-members/'.$user->userid,$data);

            $user->delete();

        }

    }catch (\Exception $e){
        DB::rollBack();
        return response(array('success' => 0, 'faillure' => 1, 'raison' => 'Erreur: ' . $e->getMessage()), 200);
    }

        DB::commit();


        return response(array('success' => 1, 'faillure' => 0, 'response' => 'Signature enregistre avec succes'), 200);

    }


    public function getMembersForRevokation(Request $request){
        return response(array('success' => 1, 'faillure' => 0, 'response' => $this->getMembersNotAdministrator()), 200);
    }

    public function getRevocationPropositionCreted(Request $request){
        $revocationsPropositions = UserRevokationProposition::where('state', '=', 'CREATED')->get();

        $retVal = [];

        for ($i = 0; $i<count($revocationsPropositions); $i++){

            $signatures = json_decode($revocationsPropositions[$i]->administratorssignatures);

            $signatureIds = [];

            foreach ($signatures as $signature){
                array_push($signatureIds, $signature->administator_id);
            }

            if (!in_array($request->user()->userid, $signatureIds, true)){
                $userid = $revocationsPropositions[$i]->proposeduserid;
                $revocationsPropositions[$i]->proposeduser = User::where('userid', '=', $userid)->first();
                array_push($retVal, $revocationsPropositions[$i]);
            }
        }

        return response(array('success' => 1, 'faillure' => 0, 'response' => $retVal), 200);

    }

}
