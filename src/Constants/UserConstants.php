<?php

namespace App\Constants;

class UserConstants
{
    public const MAX_LENGTHS = [
        'pseudo' => 50,
        'prenom' => 50,
        'nom' => 50,
        'adresse' => 255,
        'ville' => 50,
    ];

    public const EMAIL_MAX_LENGTH = 180;
}
