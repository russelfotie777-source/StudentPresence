<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width">
<title>{{ $reinitialisation ? 'Changement de mot de passe' : 'Vérification de votre adresse' }}</title>
</head>
<body style="margin:0;padding:0;background:#f3f4f2;font-family:Georgia,'Times New Roman',serif;color:#1c1f1d;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f3f4f2;padding:32px 16px;">
<tr><td align="center">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:520px;background:#ffffff;border-radius:16px;padding:32px 28px;">
<tr><td style="font-size:13px;letter-spacing:.04em;text-transform:uppercase;color:#0e8a5f;font-family:Arial,Helvetica,sans-serif;">Présence · {{ config('app.name') }}</td></tr>
<tr><td style="padding-top:18px;font-size:22px;line-height:1.3;">Bonjour {{ $prenom }},</td></tr>
<tr><td style="padding-top:12px;font-size:16px;line-height:1.55;">
@if ($reinitialisation)
Vous avez demandé à changer le mot de passe de votre compte. Saisissez ce code dans l'application :
@else
Voici le code qui confirme que cette adresse est bien la vôtre. Saisissez-le dans l'application :
@endif
</td></tr>
<tr><td align="center" style="padding:26px 0 22px;">
<span style="display:inline-block;font-family:Arial,Helvetica,sans-serif;font-size:34px;font-weight:bold;letter-spacing:.32em;padding:14px 22px 14px 28px;border-radius:12px;background:#e9f7f1;color:#0d6f4f;">{{ $code }}</span>
</td></tr>
<tr><td style="font-size:14px;line-height:1.55;color:#5a615d;">
Il reste valable {{ $validite }} minutes.
@if ($reinitialisation)
Si vous n'êtes pas à l'origine de cette demande, ignorez ce message : votre mot de passe ne changera pas.
@else
Si vous n'avez pas fait cette demande, ignorez simplement ce message.
@endif
</td></tr>
<tr><td style="padding-top:26px;border-top:1px solid #e6e8e6;margin-top:24px;font-size:12px;color:#8a908c;font-family:Arial,Helvetica,sans-serif;">Message automatique, n'y répondez pas.</td></tr>
</table>
</td></tr>
</table>
</body>
</html>
