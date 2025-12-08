<?php

namespace App\Entity;

use App\Repository\UserFoodProfileRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserFoodProfileRepository::class)]
class UserFoodProfile
{
    public const GOAL_CLASSIQUE = 'CLASSIQUE';
    public const GOAL_SPORTIF   = 'SPORTIF';
    public const GOAL_MINCEUR   = 'MINCEUR';

    public const DIET_CLASSIQUE   = 'CLASSIQUE';
    public const DIET_VEGETARIEN  = 'VEGETARIEN';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'foodProfile')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column(length: 20)]
    private string $goalType = self::GOAL_CLASSIQUE;

    #[ORM\Column(length: 20)]
    private string $dietType = self::DIET_CLASSIQUE;

    #[ORM\Column(options: ['default' => false])]
    private bool $isHalal = false;

    #[ORM\Column(type: 'json')]
    private array $allergies = [];

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $autreAllergies = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getGoalType(): string
    {
        return $this->goalType;
    }

    public function setGoalType(string $goalType): self
    {
        $this->goalType = $goalType;

        return $this;
    }

    public function getDietType(): string
    {
        return $this->dietType;
    }

    public function setDietType(string $dietType): self
    {
        $this->dietType = $dietType;

        return $this;
    }

    public function isHalal(): bool
    {
        return $this->isHalal;
    }

    public function setIsHalal(bool $isHalal): self
    {
        $this->isHalal = $isHalal;

        return $this;
    }

    public function getAllergies(): array
    {
        return $this->allergies;
    }

    public function setAllergies(array $allergies): self
    {
        $this->allergies = $allergies;

        return $this;
    }

    public function getAutreAllergies(): ?string
    {
        return $this->autreAllergies;
    }

    public function setAutreAllergies(?string $autreAllergies): self
    {
        $this->autreAllergies = $autreAllergies;

        return $this;
    }
}
