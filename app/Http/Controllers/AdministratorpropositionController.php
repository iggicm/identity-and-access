<?php

namespace App\Http\Controllers;

use App\Domain\Model\Access\AdministratorProposition;
use App\Domain\Model\Access\AdministratorSignature;
use App\Domain\Model\Access\Role;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Webpatser\Uuid\Uuid;

class AdministratorpropositionController extends Controller
{
    public function createAdministratorProposition(Request $request){
        $validator = Validator::make($request->all(), [
                'proposeduserid' => 'required|string|max:150',
            ]
        );

        if ($validator->fails()) {
            return response(array('success' => 0, 'faillure' => 1, 'raison' => $validator->errors()->first()), 200);
        }

        $proposeduser = User::where('userid', '=', $request->get('proposeduserid'))->first();
        if ($proposeduser == null){
            return response(array('success' => 0, 'faillure' => 1, 'raison' => 'Candidat inconnu'), 200);
        }

        $proposed_by = $request->user();//User::where('userid', '=', $request->get('proposed_by'))->first();
        if ($proposed_by == null){
            return response(array('success' => 0, 'faillure' => 1, 'raison' => 'Protege inconnu'), 200);
        }

        $adminRole = Role::where('name', '=', 'Administrator')->first();
        if ($adminRole == null){
            return response(array('success' => 0, 'faillure' => 1, 'raison' => 'Erreur inconnue'), 200);
        }

        $usperadminRole = Role::where('name', '=', 'Super Administrator')->first();
        if ($usperadminRole == null){
            return response(array('success' => 0, 'faillure' => 1, 'raison' => 'Erreur inconnue'), 200);
        }

        $administrators = $adminRole->useridsPlayingRole();
        $superadministrators = $usperadminRole->useridsPlayingRole();
        $found = false;

        foreach ($administrators as $administrator){
            if ($administrator == $request->get('proposed_by')){
                $found = true;
                break;
            }
        }

        if (!$found){
            foreach ($superadministrators as $superadministrator){
                if ($superadministrator == $request->get('proposed_by')){
                    $found = true;
                    break;
                }
            }
        }

        if (!$found){
            return response(array('success' => 0, 'faillure' => 1, 'raison' => 'Protege non administrateur'), 200);
        }

        ////////////////////////////////////////

        $trouver = false;
        foreach ($administrators as $administrator){
            if ($administrator == $request->get('proposeduserid')){
                $trouver = true;
                break;
            }
        }

        if (!$trouver){
            foreach ($superadministrators as $superadministrator){
                if ($superadministrator == $request->get('proposeduserid')){
                    $trouver = true;
                    break;
                }
            }
        }

        if ($trouver){
            return response(array('success' => 0, 'faillure' => 1, 'raison' => 'Est deja administrateur'), 200);
        }

        $administratorProposition = AdministratorProposition::where('proposeduserid', '=', $request->get('proposeduserid'))->first();

        if (!($administratorProposition === null)){
            return response(array('success' => 0, 'faillure' => 1, 'raison' => 'Deja propose  administrateur'), 200);
        }


        //array_push($signatures, new AdministratorSignature($member->memberid, date('Y-m-d H:i:s'), $member->firstname.' ' . $member->lastname));
        $adminSignature = new AdministratorSignature($proposed_by->userid, date('Y-m-d H:i:s'), $proposed_by->firstname.' ' . $proposed_by->lastname);
        $administratorproposition = null;
        try{
            $administratorproposition = AdministratorProposition::create(
                [
                    'administratorpropositionid'=>Uuid::generate()->string,
                    'proposeduserid'=>$request->get('proposeduserid'),
                    'proposed_by'=>$request->get('proposed_by'),
                    'administratorssignatures'=>json_encode([$adminSignature]),
                    'state'=>AdministratorProposition::CREATED
                ]
            );


        }catch (\Exception $exception){
            return response(array('success' => 0, 'faillure' => 1, 'raison' => $exception->getMessage()), 200);

        }


        $signatures = json_decode($administratorproposition->administratorssignatures);

        $isFinished = true;
        foreach ($administrators as $item){
            $found = false;
            foreach ($signatures as $administratorssignature){
                if ($administratorssignature->administator_id == $item){
                    $found = true;
                    break;
                }
            }
            if (!$found){
                $isFinished =  false;
                break;
            }
        }

        //$isFinished = $this->unanimityReached($request, $candidature);

        if ($isFinished == true){
            $usersplayingrole = json_decode($adminRole->usersplayingrole);
            array_push($usersplayingrole, $administratorproposition->proposeduserid);
            $adminRole->usersplayingrole = json_encode($usersplayingrole);
            $adminRole->save();
            $administratorproposition->state = AdministratorProposition::ACCEPTED;
            $administratorproposition->save();

        }

        return response(array('success' => 1, 'faillure' => 0, 'response' => 'Proposition enregistree avec succes'), 200);

    }

    public function signeAdministratorProposition(Request $request, $administratorpropositionid){
        /*$validator = Validator::make($request->all(), [
                'adminid' => 'required|string|max:150',
            ]
        );

        if ($validator->fails()) {
            return response(array('success' => 0, 'faillure' => 1, 'raison' => $validator->errors()->first()), 200);
        }*/

        $admin = Auth::user();//User::where('userid', '=', $request->get('adminid'))->first();
        if ($admin == null){
            return response(array('success' => 0, 'faillure' => 1, 'raison' => 'Protege inconnu'), 200);
        }

        $adminRole = Role::where('name', '=', 'Administrator')->first();
        if ($adminRole == null){
            return response(array('success' => 0, 'faillure' => 1, 'raison' => 'Erreur inconnue'), 200);
        }

        $usperadminRole = Role::where('name', '=', 'Super Administrator')->first();
        if ($usperadminRole == null){
            return response(array('success' => 0, 'faillure' => 1, 'raison' => 'Erreur inconnue'), 200);
        }

        $administrators = $adminRole->useridsPlayingRole();
        $superadministrators = $usperadminRole->useridsPlayingRole();
        $found = false;

        foreach ($administrators as $administrator){
            if ($administrator == $request->get('adminid')){
                $found = true;
                break;
            }
        }

        if (!$found){
            foreach ($superadministrators as $superadministrator){
                if ($superadministrator == $request->get('adminid')){
                    $found = true;
                    break;
                }
            }
        }

        if (!$found){
            return response(array('success' => 0, 'faillure' => 1, 'raison' => 'Protege non administrateur'), 200);
        }

        $administratorproposition = AdministratorProposition::where('administratorpropositionid', '=', $administratorpropositionid)->first();

        $signatures = json_decode($administratorproposition->administratorssignatures);
        $adminSignature = new AdministratorSignature($admin->userid, date('Y-m-d H:i:s'), $admin->firstname.' ' . $admin->lastname);
        array_push($signatures, $adminSignature);

        $administratorproposition->administratorssignatures = json_encode($signatures, JSON_UNESCAPED_SLASHES);
        $administratorproposition->save();

        //Voir si l'unanimite est atteinte
/*
        foreach ($superadministrators as $item){
            array_push($administrators, $item);
        }*/

        $isFinished = true;
        foreach ($administrators as $item){
            $found = false;
            foreach ($signatures as $administratorssignature){
                if ($administratorssignature->administator_id == $item){
                    $found = true;
                    break;
                }
            }
            if (!$found){
                $isFinished =  false;
                break;
            }
        }

        //$isFinished = $this->unanimityReached($request, $candidature);

        if ($isFinished == true){
            $usersplayingrole = json_decode($adminRole->usersplayingrole);
            array_push($usersplayingrole, $administratorproposition->proposeduserid);
            $adminRole->usersplayingrole = json_encode($usersplayingrole);
            $adminRole->save();
            $administratorproposition->state = AdministratorProposition::ACCEPTED;
            $administratorproposition->save();

        }

        $request->request->add(['adminid'=>$admin->userid]);
        return $this->getAdministratorPropositions($request);
        //return response(array('success' => 1, 'faillure' => 0, 'response' => 'Signe avec succes', ), 200);
    }

    public function getAdministratorPropositions(Request $request){
       /* $validator = Validator::make($request->all(), [
                'adminid' => 'required|string|max:150',
            ]
        );

        if ($validator->fails()) {
            return response(array('success' => 0, 'faillure' => 1, 'raison' => $validator->errors()->first()), 200);
        }*/

        $admin = $request->user();//User::where('userid', '=', $request->get('adminid'))->first();
        //return $admin;
        if ($admin == null){
            return response(array('success' => 0, 'faillure' => 1, 'raison' => 'Administrateur inconnu'), 200);
        }

        $propositions = AdministratorProposition::where('proposed_by', '!=', $admin->userid)->where('state', '!=', 'ACCEPTED')->get();
        $results = [];
        foreach ($propositions as $proposition){
            $signers = json_decode($proposition->administratorssignatures);
            $found = false;
            foreach ($signers as $signer){
                if ($signer->administator_id == $admin->userid){
                    $found = true;
                    break;
                }
            }
            if (!$found){
                array_push($results, $proposition);
            }
        }

        for ($i = 0; $i<count($results); $i++){
            $signers = json_decode($results[$i]->administratorssignatures);
            $signataires = [];
            foreach ($signers as $signer){
               array_push($signataires, User::where('userid', '=', $signer->administator_id)->first()) ;
            }
            $results[$i]->signataires = $signataires;
            $results[$i]->user = User::where('userid', '=', $results[$i]->proposeduserid)->first();
        }
        return response(array('success' => 1, 'faillure' => 0, 'response' => $results), 200);

    }
}
