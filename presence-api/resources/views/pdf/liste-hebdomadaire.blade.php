<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 12mm 12mm 12mm 12mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 8.5px; color: #111; }

        /* En-tête institutionnel bilingue : trois colonnes, logo au centre. */
        .entete { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
        .entete td { vertical-align: top; padding: 0; }
        .bloc { font-size: 7.4px; line-height: 1.55; text-align: center; }
        .bloc .sep { color: #333; letter-spacing: 1px; }
        .bloc .fort { font-weight: bold; }
        .centre { text-align: center; }
        .logo { height: 74px; margin-bottom: 3px; }
        .titre { font-weight: bold; font-size: 11px; letter-spacing: 0.3px; margin: 3px 0; }
        .sous-titre { font-weight: bold; font-size: 10px; margin: 3px 0; }

        .ligne-salle { width: 100%; margin: 6px 0 5px; font-weight: bold; font-size: 10px; }
        .ligne-salle td { padding: 0; }

        /* Tableau des étudiants : une colonne de signature par jour. */
        table.etudiants { width: 100%; border-collapse: collapse; }
        table.etudiants th, table.etudiants td { border: 1px solid #222; padding: 3px 4px; }
        /* Hauteur de ligne suffisante pour une signature manuscrite dans chaque case jour. */
        table.etudiants tbody td { height: 13px; }
        table.etudiants th { font-size: 7.6px; text-transform: uppercase; text-align: center; }
        table.etudiants td { font-size: 8px; font-weight: bold; }
        table.etudiants td.num { width: 3.5%; text-align: center; }
        table.etudiants td.mat { width: 9%; }
        table.etudiants td.nom { width: 33%; }
        table.etudiants td.jour { width: 9.08%; }
        tr.fm td { background: #fff3cd; }
        tr.fm td.nom .tag { display: inline-block; margin-left: 5px; padding: 0 4px; border: 1px solid #b7791f;
                             color: #7a4f00; font-size: 6.6px; border-radius: 2px; vertical-align: middle; }
        .legende { margin-top: 4px; font-size: 7.2px; color: #444; }
        .legende .pastille { display: inline-block; width: 9px; height: 9px; background: #fff3cd;
                             border: 1px solid #b7791f; vertical-align: middle; margin-right: 4px; }

        /* Tableau des séances de la semaine. */
        table.seances { width: 100%; border-collapse: collapse; margin-top: 14px; page-break-inside: avoid; }
        table.seances th, table.seances td { border: 1px solid #222; padding: 3px 4px; font-size: 7.8px; }
        table.seances th { font-weight: bold; text-align: center; }
        table.seances td.jour { font-weight: bold; text-align: center; width: 11%; }
        table.seances td.ec { width: 27%; }
        table.seances td.ens { width: 16%; }
        table.seances td.h { width: 9%; text-align: center; }
        table.seances td.sig { width: 9.5%; }
        table.seances tr td { height: 12px; }
    </style>
</head>
<body>
    @php($e = $etablissement)

    <table class="entete">
        <tr>
            <td style="width: 27%;">
                <div class="bloc">
                    REPUBLIQUE DU CAMEROUN<br>
                    Paix – Travail – Patrie<br>
                    <span class="sep">---------------</span><br>
                    MINISTERE DE L'ENSEIGNEMENT SUPERIEUR<br>
                    <span class="sep">---------------</span><br>
                    UNIVERSITE DE DOUALA<br>
                    <span class="sep">---------------</span><br>
                    INSTITUT UNIVERSITAIRE DE TECHNOLOGIE<br>
                    <span class="sep">---------------</span><br>
                    <span class="fort">{{ $e['departement_fr'] }}</span><br>
                    <span class="sep">---------------</span><br>
                    BP. {{ $e['bp'] }}<br>
                    Tél : {{ $e['tel'] }}<br>
                    E-mail : {{ $e['email'] }}
                </div>
            </td>
            <td style="width: 46%;" class="centre">
                @if ($logo)
                    <img class="logo" src="{{ $logo }}" alt="Logo">
                @endif
                <div class="titre">{{ $e['departement_fr'] }}</div>
                <div class="sous-titre">
                    OPTION : {{ $option }}&nbsp;&nbsp;&nbsp;
                    NIVEAU : {{ $niveau_romain }}&nbsp;&nbsp;&nbsp;
                    ANNEE ACADEMIQUE : {{ $annee_academique }}
                </div>
                <div class="titre">LISTE DE PRESENCE DES ETUDIANTS</div>
                <div class="sous-titre">SEMESTRE {{ $semestre }}</div>
            </td>
            <td style="width: 27%;">
                <div class="bloc">
                    REPUBLIC OF CAMEROON<br>
                    Peace – Work – Fatherland<br>
                    <span class="sep">---------------</span><br>
                    MINISTRY OF HIGHER EDUCATION<br>
                    <span class="sep">---------------</span><br>
                    THE UNIVERSITY OF DOUALA<br>
                    <span class="sep">---------------</span><br>
                    UNIVERSITY INSTITUTE OF TECHNOLOGY<br>
                    <span class="sep">---------------</span><br>
                    <span class="fort">{{ $e['departement_en'] }}</span><br>
                    <span class="sep">---------------</span><br>
                    PO Box : {{ $e['bp'] }}<br>
                    Phone : {{ $e['tel'] }}<br>
                    E-mail : {{ $e['email'] }}
                </div>
            </td>
        </tr>
    </table>

    <table class="ligne-salle">
        <tr>
            <td style="width: 25%;">SALLE : {{ $salle }}</td>
            <td style="width: 30%;">{{ $groupe }}</td>
            <td style="width: 45%; text-align: right;">SEMAINE DU {{ $semaine_du }} AU {{ $semaine_au }}</td>
        </tr>
    </table>

    <table class="etudiants">
        <thead>
            <tr>
                <th>N°</th>
                <th>Matricule</th>
                <th>Noms &amp; Prénoms</th>
                <th>Lundi</th><th>Mardi</th><th>Mercredi</th><th>Jeudi</th><th>Vendredi</th><th>Samedi</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($etudiants as $etudiant)
                <tr class="{{ $etudiant['fm'] ? 'fm' : '' }}">
                    <td class="num">{{ $etudiant['numero'] }}</td>
                    <td class="mat">{{ $etudiant['matricule'] }}</td>
                    <td class="nom">{{ $etudiant['nom'] }}@if ($etudiant['fm'])<span class="tag">FM</span>@endif</td>
                    <td class="jour"></td><td class="jour"></td><td class="jour"></td>
                    <td class="jour"></td><td class="jour"></td><td class="jour"></td>
                </tr>
            @endforeach
            @if ($etudiants->isEmpty())
                <tr><td colspan="9" style="text-align: center; font-weight: normal; color: #666;">Aucun étudiant rattaché à cette salle.</td></tr>
            @endif
        </tbody>
    </table>

    @if ($contient_fm && $etudiants->contains('fm', true))
        <div class="legende">
            <span class="pastille"></span>
            Étudiant en formation migrante (FM) : venu de l'alternance, rattaché à cette salle de formation initiale.
        </div>
    @endif

    <table class="seances">
        <thead>
            <tr>
                <th>Dates</th>
                <th>Codes et intitulés des EC</th>
                <th>Noms des enseignants</th>
                <th>Heure de début</th>
                <th>Heure de fin</th>
                <th>Durée</th>
                <th>Signature enseignant</th>
                <th>Signature délégué</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($jours as $jour)
                @foreach ($jour['seances'] as $i => $s)
                    <tr>
                        @if ($i === 0)
                            <td class="jour" rowspan="{{ count($jour['seances']) }}">{{ $jour['label'] }}</td>
                        @endif
                        <td class="ec">{{ $s['ec'] ?? '' }}</td>
                        <td class="ens">{{ $s['enseignant'] ?? '' }}</td>
                        <td class="h">{{ $s['debut'] ?? '' }}</td>
                        <td class="h">{{ $s['fin'] ?? '' }}</td>
                        <td class="h">{{ $s['duree'] ?? '' }}</td>
                        <td class="sig"></td>
                        <td class="sig"></td>
                    </tr>
                @endforeach
            @endforeach
        </tbody>
    </table>
</body>
</html>
