<?php

namespace App\Tests\Service;

use App\Service\EmailValidatorService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class EmailValidatorServiceTest extends TestCase
{
    private EmailValidatorService $emailValidatorService;

    protected function setUp(): void
    {
        $this->emailValidatorService = new EmailValidatorService();
    }

    public function testEmailValideNeRenvoieAucuneErreur(): void
    {
        $result = $this->emailValidatorService->validateAndNormalize('utilisateur@example.com');

        $this->assertTrue($result['valid']);
        $this->assertSame('utilisateur@example.com', $result['']);
        $this->assertNull($result['error']);
    }

    public function testEmailValideEstNormalise(): void
    {
        $result = $this->emailValidatorService->validateAndNormalize('  Utilisateur@Example.COM  ');

        $this->assertTrue($result['valid']);
        $this->assertSame('utilisateur@example.com', $result['email']);
        $this->assertNull($result['error']);
    }

    #[DataProvider('provideEmailsInvalides')]
    public function testEmailInvalideRenvoieLErreurAttendue(string $email, string $expectedError): void
    {
        $result = $this->emailValidatorService->validateAndNormalize($email);

        $this->assertFalse($result['valid']);
        $this->assertNull($result['email']);
        $this->assertSame($expectedError, $result['error']);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function provideEmailsInvalides(): array
    {
        $emailTropLong = 'a@' . str_repeat('b', 63) . '.' . str_repeat('c', 63) . '.' . str_repeat('d', 50) . '.com';

        return [
            'vide' => ['', 'Email requis'],
            'espaces uniquement' => ['   ', 'Email requis'],
            'format invalide' => ['pas-un-email', 'Email invalide'],
            'domaine manquant' => ['utilisateur@', 'Email invalide'],
            'trop long' => [$emailTropLong, 'Email trop long'],
        ];
    }
}
