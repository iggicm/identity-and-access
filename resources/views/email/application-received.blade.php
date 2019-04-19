@extends('beautymail::templates.sunny', ['color' => '#4204a0'])

@section('content')

    @include ('beautymail::templates.sunny.heading' , [
        'heading' => 'Salut ' . $application->firstname . '  ' . $application->lastname,
        'level' => 'h1',
    ])

    @include('beautymail::templates.sunny.contentStart')

    <p>
       L'application IGGI vient de recevoir votre demande d'adhesion.<br>
        Vous seriez recontacte tres bientot pour vous informer sur l'issue de votre demande.

    </p>
    <p>

    </p>

    @include('beautymail::templates.sunny.contentEnd')

    @include('beautymail::templates.sunny.button', [
        	'title' => 'IGGI Accueil',
        	'link' => env('HOST_WEB_CLIENT')
    ])

@stop
