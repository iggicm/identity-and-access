@extends('beautymail::templates.sunny', ['color' => '#4204a0'])

@section('content')

    @include ('beautymail::templates.sunny.heading' , [
        'heading' => 'Salut ' . $destinataire->firstname . '  ' . $destinataire->lastname,
        'level' => 'h1',
    ])

    @include('beautymail::templates.sunny.contentStart')

    <p>
        Le projet <strong>{{$project->name}}</strong> vient de recevoir une nouvelle demande au poste de <strong>{{$role->name}}</strong> ({{$role->description}})<br>

        <strong>Requerant: </strong> {{$application->firstname . '  ' . $application->lastname}}<br>
        <strong>E-Mail: </strong> {{$application->email}}<br>
        <strong>Telephone: </strong> {{$application->phone}}

    </p>
    <p>
        Pour voir la demande veuillez vous connecter a la plateforme en cliquant sur le lien suivant:  <a href="{{env('HOST_WEB_CLIENT')}}">GAPP Accueil</a>
    </p>

    @include('beautymail::templates.sunny.contentEnd')

    @include('beautymail::templates.sunny.button', [
        	'title' => 'GAPP Accueil',
        	'link' => env('HOST_WEB_CLIENT')
    ])

@stop
