<?php

namespace App\Entity;

use App\Repository\IllustrationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: IllustrationRepository::class)]
class Illustration
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $nomFichier = null;

    #[ORM\OneToOne(mappedBy: 'illustration', cascade: ['persist', 'remove'])] // TODO : enlever le nullable après avoir testé l'api sur postman. Avoir une image par défaut stockée dans le projet.
    private ?Recette $recette = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNomFichier(): ?string
    {
        return $this->nomFichier;
    }

    public function setNomFichier(string $nomFichier): static
    {
        $this->nomFichier = $nomFichier;

        return $this;
    }

    public function getRecette(): ?Recette
    {
        return $this->recette;
    }

    public function setRecette(?Recette $recette): static
{
    // délie l'ancienne recette si besoin
    if ($this->recette !== null && $this->recette->getIllustration() === $this) {
        $this->recette->setIllustration(null);
    }

    $this->recette = $recette;

    // set the owning side of the relation if necessary
    if ($recette !== null && $recette->getIllustration() !== $this) {
        $recette->setIllustration($this);
    }

    return $this;
}

    // TODO : remettre cette fonction recette pour la rendre non nullable après avoir testé api.
    /* public function setRecette(Recette $recette): static
    {
        // set the owning side of the relation if necessary
        if ($recette->getIllustration() !== $this) {
            $recette->setIllustration($this);
        }

        $this->recette = $recette;

        return $this;
    }*/
}
