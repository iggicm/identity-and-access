@extends('beautymail::templates.sunny', ['color' => '#4204a0'])

@section('content')

    @include ('beautymail::templates.sunny.heading' , [
        'heading' => 'Salut ' . $application->firstname . '  ' . $application->lastname,
        'level' => 'h1',
    ])
    @include('beautymail::templates.sunny.contentStart')
    <p>
        Votre demande a ete accepte
    </p>

    <p>
        Vous devez completer votre enregistrement en cliquant sur le lien suivant: <a href="{{$url}}">{{$url}}</a>
    </p>
    <p>
        Par le responsable : {{$responsable->firstname . '  ' . $responsable->lastname}}
        <br>

    </p>
    @include('beautymail::templates.sunny.contentEnd')

    @include ('beautymail::templates.sunny.heading' , [
        'heading' => 'Cordialement',
        'level' => 'h4',
    ])

    @include('beautymail::templates.sunny.button', [
           'title' => 'GAPP Accueil',
           'link' => env('HOST_WEB_CLIENT')
   ])

@stop
