<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    @include('pdf.partials.liste-style')
</head>
<body>
    {{-- Toutes les salles du département, une page chacune, dans l'ordre niveau → filière → FI/FA → nom. --}}
    @foreach ($listes as $liste)
        <div class="page">
            @include('pdf.partials.liste-salle', $liste)
        </div>
    @endforeach
</body>
</html>
