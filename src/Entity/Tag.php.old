<?php

namespace App\Entity;

use App\Repository\TagRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TagRepository::class)]
class Tag
{
    // Catégories de tags
    public const CATEGORIE_OBJECTIF  = 'OBJECTIF';
    public const CATEGORIE_ALLERGENE = 'ALLERGENE';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // Libellé lisible (ex. "Healthy", "Contient gluten")
    #[ORM\Column(length: 100, nullable: false)]
    private string $contenu;

    // Code technique stable (ex. "HEALTHY", "GLUTEN", "HALAL")
    #[ORM\Column(length: 50, unique: true, nullable: false)]
    private string $code;

    // Catégorie de tag (ex. "OBJECTIF", "ALLERGENE")
    #[ORM\Column(length: 50, nullable: false)]
    private string $categorie;

    // Tag actif / inactif
    #[ORM\Column]
    private bool $isActive = true;

    /**
     * @var Collection<int, Recette>
     */
    #[ORM\ManyToMany(targetEntity: Recette::class, mappedBy: 'tags')]
    private Collection $recettes;

    public function __construct()
    {
        $this->recettes = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getContenu(): ?string
    {
        return $this->contenu;
    }

    public function setContenu(string $contenu): static
    {
        $this->contenu = $contenu;

        return $this;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;

        return $this;
    }

    public function getCategorie(): ?string
    {
        return $this->categorie;
    }

    public function setCategorie(string $categorie): static
    {
        $this->categorie = $categorie;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    /**
     * @return Collection<int, Recette>
     */
    public function getRecettes(): Collection
    {
        return $this->recettes;
    }

    public function addRecette(Recette $recette): static
    {
        if (!$this->recettes->contains($recette)) {
            $this->recettes->add($recette);
            $recette->addTag($this);
        }

        return $this;
    }

    public function removeRecette(Recette $recette): static
    {
        if ($this->recettes->removeElement($recette)) {
            $recette->removeTag($this);
        }

        return $this;
    }

    public function __toString(): string
    {
        return $this->contenu ?? $this->code ?? 'Tag';
    }
}
