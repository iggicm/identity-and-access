<!DOCTYPE html>
<html lang="en-US">
<head>
    <meta charset="utf-8">
</head>
<body>
<div>
    <div id="content" class="container ">

        <style>

        </style>

        <div class="row">
            <div class="col-sm-1 col-md-3 col-lg-3 col-xl-4">
            </div>
            <div class="col-sm-10 col-md-6 col-lg-6 col-xl-4">
                <h3>Invitation A completer votre enregistrement</h3>
                <hr>
                <p>{{$responsible->gender == 'FEMALE'?'Mme':'M.'}} <strong>{{$responsible->firstname . '  ' . $responsible->lastname}}</strong>
                <br>vous avez ete choisi comme responsable du projet <strong>{{$project->name}}.({{$project->description}})</strong><br>
                Pour cela vous etes appele a completer votre enregistrement en cliquant sur le lien ci-dessous.<br>
                Par {{$promoter->gender == 'FEMALE'?'Mme':'M.'}} <strong>{{$promoter->firstname . '  ' . $promoter->lastname}}</strong></p>

                <p>
                    <a class="btn btn-primary btn-lg" href="{{$invitation->url}}">Veuillez cliquer ici pour completer votre enregistrement</a>
                </p><br>
                <p>
                    vous pouvez egalement copier et coller dans la barre de recherche de votre navigateur le lien ci-dessous:<br>
                    <a href="{{$invitation->url}}">{{$invitation->url}}</a>
                </p>
            </div>
        </div>

    </div><!-- #content -->
</div>

</body>
</html>
