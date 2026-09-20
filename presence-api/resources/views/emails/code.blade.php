<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width">
<title>{{ $reinitialisation ? 'Changement de mot de passe' : 'Votre code' }}</title>
</head>
<body style="margin:0;padding:0;background:#f4f5f3;font-family:Arial,Helvetica,sans-serif;color:#1c1f1d;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f4f5f3;padding:40px 16px;">
<tr><td align="center">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:480px;background:#ffffff;border-radius:16px;">

<tr><td style="padding:36px 36px 0;font-size:15px;font-weight:bold;color:#0e8a5f;">Ziris</td></tr>

<tr><td style="padding:36px 36px 0;font-size:17px;line-height:1.6;">Bonjour {{ $prenom }},</td></tr>

<tr><td style="padding:20px 36px 0;font-size:16px;line-height:1.6;">
@if ($reinitialisation)
Voici votre code pour choisir un nouveau mot de passe.
@else
Voici votre code de vérification.
@endif
</td></tr>

<tr><td align="center" style="padding:32px 36px 0;">
<span style="display:inline-block;font-size:36px;font-weight:bold;letter-spacing:8px;padding:18px 24px 18px 32px;border-radius:12px;background:#eaf6f0;color:#0d6f4f;">{{ substr($code, 0, 3) }} {{ substr($code, 3) }}</span>
</td></tr>

<tr><td style="padding:32px 36px 0;font-size:16px;line-height:1.6;">Il est valable {{ $validite }} minutes.</td></tr>

<tr><td style="padding:20px 36px 0;font-size:16px;line-height:1.6;">
@if ($reinitialisation)
Si vous n'avez rien demandé, ignorez ce message. Votre mot de passe ne changera pas.
@else
Si vous n'avez rien demandé, ignorez ce message.
@endif
</td></tr>

<tr><td style="padding:40px 36px 36px;font-size:13px;line-height:1.6;color:#8a908c;">IUT de Douala<br>Message automatique, merci de ne pas y répondre.</td></tr>

</table>
</td></tr>
</table>
</body>
</html>
