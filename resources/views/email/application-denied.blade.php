@extends('beautymail::templates.sunny', ['color' => '#4204a0'])

@section('content')

    @include ('beautymail::templates.sunny.heading' , [
        'heading' => 'Salut ' . $application->firstname . '  ' . $application->lastname,
        'level' => 'h1',
    ])

    @include('beautymail::templates.sunny.contentStart')

    <p>
        Nous avons pris connaissance  avec votre demande.<br>
        Nous l'avons etudiee avec attention. Mais malhereusement votre profil ne correspond pas au profil que nous souhaitons pour nos membres.

    </p>

    <p>
       Pour IGGI Le responsable: {{$responsable->firstname . '  ' . $responsable->lastname}}
    </p>
    @include('beautymail::templates.sunny.contentEnd')

    @include('beautymail::templates.sunny.button', [
           'title' => 'GAPP Accueil',
           'link' => env('HOST_WEB_CLIENT')
   ])
    @include ('beautymail::templates.sunny.heading' , [
        'heading' => 'Cordialement',
        'level' => 'h4',
    ])



@stop
