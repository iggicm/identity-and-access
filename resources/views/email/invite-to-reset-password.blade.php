@extends('beautymail::templates.sunny', ['color' => '#4204a0'])

@section('content')

    @include ('beautymail::templates.sunny.heading' , [
        'heading' => 'Invitation A reconfigurer votre mot de passe',
        'level' => 'h1',
    ])

    @include('beautymail::templates.sunny.contentStart')

    <hr>
    <p>{{$user->gender == 'FEMALE'?'Mme':'M.'}} <strong>{{$invitation->firstname . '  ' . $invitation->lastname}}</strong>
        <br>Vous avez demande la reconfiguration de votre mot de passe.<br>
        Pour cela vous etes invite a le faire en cliquant sur le lien ci-dessous.<br>
    </p>

    <p>
        <a class="btn btn-primary btn-lg" href="{{$invitation->url}}">Veuillez cliquer ici pour reconfigurer votre mot de passe</a>
    </p><br>
    <p>
        vous pouvez egalement copier et coller dans la barre de recherche de votre navigateur le lien ci-dessous:<br>
        <a href="{{$invitation->url}}">{{$invitation->url}}</a>
    </p>
    <p>
            Voiala
    </p>

    @include('beautymail::templates.sunny.contentEnd')

    @include('beautymail::templates.sunny.button', [
        	'title' => 'IGGI Accueil',
        	'link' => env('HOST_WEB_CLIENT')
    ])

@stop

