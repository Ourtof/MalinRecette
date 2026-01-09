<?php

namespace App\Service;

class EmailValidatorService
{
    /**
     * validation et sécurité d'un email
     * @return array{valid: bool, email: string|null, error: string|null}
     */
    public function validateAndNormalize(string $email): array
    {
        $email = trim($email);
        
        if (empty($email)) {
            return [
                'valid' => false,
                'email' => null,
                'error' => 'Email requis'
            ];
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [
                'valid' => false,
                'email' => null,
                'error' => 'Email invalide'
            ];
        }

        $email = strtolower($email);

        if (strlen($email) > \App\Constants\UserConstants::EMAIL_MAX_LENGTH) {
            return [
                'valid' => false,
                'email' => null,
                'error' => 'Email trop long'
            ];
        }

        return [
            'valid' => true,
            'email' => $email,
            'error' => null
        ];
    }
}
