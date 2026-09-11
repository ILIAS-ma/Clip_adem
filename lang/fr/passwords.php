<?php

return [
    'reset' => 'Votre mot de passe a été réinitialisé.',
    'sent' => 'Le lien de réinitialisation vous a été envoyé par e-mail.',
    'throttled' => 'Veuillez patienter avant de réessayer.',
    'token' => 'Ce lien de réinitialisation n’est plus valide.',

    // Même prudence que pour `auth.failed` : la réponse est identique que
    // l'adresse existe ou non, sinon le formulaire devient un moyen de
    // savoir qui est inscrit.
    'user' => 'Si un compte existe pour cette adresse, un lien vient d’être envoyé.',
];
