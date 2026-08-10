<?php

namespace App\Tests\Service;

use App\Service\PasswordValidatorService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PasswordValidatorServiceTest extends TestCase
{
    private PasswordValidatorService $passwordValidatorService;

    protected function setUp(): void
    {
        $this->passwordValidatorService = new PasswordValidatorService();
    }

    public function testMotDePasseValideNeRenvoieAucuneErreur(): void
    {
        $errors = $this->passwordValidatorService->validate('MotDePasse1!');

        $this->assertSame([], $errors);
    }

    #[DataProvider('provideMotsDePasseInvalides')]
    public function testMotDePasseInvalideRenvoieLErreurAttendue(string $password, string $expectedError): void
    {
        $errors = $this->passwordValidatorService->validate($password);

        $this->assertContains($expectedError, $errors);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function provideMotsDePasseInvalides(): array
    {
        return [
            'trop court' => ['Ab1!', 'Le mot de passe doit contenir au moins 8 caractères'],
            'sans majuscule' => ['motdepasse1!', 'Le mot de passe doit contenir au moins une majuscule'],
            'sans minuscule' => ['MOTDEPASSE1!', 'Le mot de passe doit contenir au moins une minuscule'],
            'sans chiffre' => ['MotDePasse!', 'Le mot de passe doit contenir au moins un chiffre'],
            'sans caractere special' => ['MotDePasse1', 'Le mot de passe doit contenir au moins un caractère spécial'],
        ];
    }

   public function testMotDePasseCumulantPlusieursDefautsRenvoieToutesLesErreurs(): void
    {
    // Chaîne vide : viole les 5 règles à la fois
    $errors = $this->passwordValidatorService->validate('');

    $this->assertCount(5, $errors);
    }
}