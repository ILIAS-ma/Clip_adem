<?php

/*
 * Messages d'authentification.
 *
 * Sans ces fichiers, Laravel affiche la clé brute — un clippeur voyait
 * « auth.failed » à la place d'une phrase. Le framework ne les livre plus
 * depuis la version 11 : il faut les publier, et les traduire.
 */

return [
    // Volontairement vague sur la cause : préciser « cet e-mail est inconnu »
    // dirait à un inconnu quelles adresses sont inscrites chez nous.
    'failed' => 'Ces identifiants ne correspondent à aucun compte.',
    'password' => 'Le mot de passe est incorrect.',
    'throttle' => 'Trop de tentatives. Réessayez dans :seconds secondes.',
];
