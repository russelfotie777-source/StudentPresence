{{--
La liste de présence d'une salle sur une semaine : en-tête bilingue au
nom du département de la salle, tableau des étudiants (une colonne de
signature par jour), tableau des séances de la semaine. Reçoit les
données de App\Services\ListeHebdomadaire::pour().
--}}
@php($e = $etablissement)
@php($signe = $symboles === 'valeur' ? ['present' => '+1', 'absent' => '−1'] : ['present' => '✓', 'absent' => '✗'])

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
                <span class="fort">{{ $departement_fr }}</span><br>
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
            <div class="titre">{{ $departement_fr }}</div>
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
                <span class="fort">{{ $departement_en }}</span><br>
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
                @foreach ($etudiant['jours'] as $marques)
                    <td class="jour">
                        @foreach ($marques as $marque)
                            @if ($marque)<span class="marque {{ $marque }}">{{ $signe[$marque] }}</span>@endif
                        @endforeach
                    </td>
                @endforeach
            </tr>
        @endforeach
        @if ($etudiants->isEmpty())
            <tr><td colspan="9" style="text-align: center; font-weight: normal; color: #666;">Aucun étudiant rattaché à cette salle.</td></tr>
        @endif
    </tbody>
</table>

@if ($contient_marques || ($contient_fm && $etudiants->contains('fm', true)))
    <div class="legende">
        @if ($contient_marques)
            <span class="marque present">{{ $signe['present'] }}</span> présent&nbsp;&nbsp;
            <span class="marque absent">{{ $signe['absent'] }}</span> absent&nbsp;&nbsp;
            (une marque par séance, dans l'ordre des horaires ; case vide : séance à venir ou appel non validé)
        @endif
        @if ($contient_fm && $etudiants->contains('fm', true))
            @if ($contient_marques)<br>@endif
            <span class="pastille"></span>
            Étudiant en formation migrante (FM) : venu de l'alternance, rattaché à cette salle de formation initiale.
        @endif
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
