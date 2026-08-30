<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: helvetica, sans-serif; font-size: 11px; color: #1a1a1a; }
        h1 { font-size: 16px; margin-bottom: 2px; }
        .meta { color: #555; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; }
        th { background: #f0f0f0; }
        .present { color: #1a7a1a; font-weight: bold; }
        .absent { color: #b00020; font-weight: bold; }
        .stats { margin-top: 14px; font-size: 12px; }
        .footer { margin-top: 30px; font-size: 10px; color: #777; }
    </style>
</head>
<body>
    <h1>Liste de présence</h1>
    <div class="meta">
        {{ $seance->courseTemplate?->matiere?->nom ?? '—' }} —
        Salle {{ $seance->salle->nom }} —
        {{ $seance->date_seance?->format('d/m/Y') }} ({{ $seance->jour->value }})
        {{ $seance->heure_debut }}–{{ $seance->heure_fin }}<br>
        Enseignant : {{ $seance->enseignant->name }}<br>
        Généré le {{ now()->format('d/m/Y H:i') }}
    </div>

    <table>
        <thead>
            <tr><th>#</th><th>Nom</th><th>Formation</th><th>Présence</th></tr>
        </thead>
        <tbody>
            @foreach ($presences as $i => $presence)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $presence->etudiant->name }}</td>
                    <td>{{ $presence->etudiant->formation?->value }}</td>
                    <td class="{{ $presence->etat->value }}">{{ $presence->etat->value === 'present' ? 'Présent' : 'Absent' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="stats">
        Total : {{ $stats['total'] }} — Présents : {{ $stats['present'] }} — Absents : {{ $stats['absent'] }}
        — Taux de présence : {{ $stats['taux'] }}%
    </div>

    <div class="footer">Signature du délégué : _______________________</div>
</body>
</html>
