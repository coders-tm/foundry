<?php

return [
    'email' => [
        'required' => 'Une adresse e-mail est requise.',
        'exists' => 'Votre adresse e-mail n\'existe pas.',
    ],
    'password' => [
        'required' => 'Le champ du mot de passe est requis.',
        'match' => 'Votre mot de passe ne correspond pas à nos enregistrements.',
        'old_match' => 'L\'ancien mot de passe ne correspond pas !',
        'confirmation' => 'La confirmation du mot de passe est requise.',
    ],
    'subscription' => [
        'downgrade_plan' => 'Un plan valide est nécessaire pour rétrograder l\'abonnement.',
        'plan_already' => 'Vous êtes déjà abonné au plan :plan.',
    ],
    'recaptch' => 'Le processus de vérification pour reCAPTCHA a échoué. Veuillez réessayer.',
];
