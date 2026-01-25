<?php

namespace App\Entity;

use App\Repository\RecetteRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RecetteRepository::class)]
class Recette
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: false)]
    private string $titre;

    #[ORM\Column(type: Types::TEXT, nullable: false)]
    private string $contenu;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: false)]
    private \DateTime $dateRecette;

    #[ORM\ManyToOne(inversedBy: 'recettes')]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $auteur = null;

    #[ORM\OneToOne(inversedBy: 'recette', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: true)] // TODO: Quand toute la BDD sera nettoyée et qu'on aura une illustration par défaut, passer nullable à false
    private ?Illustration $illustration = null;

    /**
     * @var Collection<int, Tag>
     */
    #[ORM\ManyToMany(targetEntity: Tag::class, inversedBy: 'recettes')]
    private Collection $tags;

    #[ORM\Column(type: 'json')]
    private array $allergies = [];

    public function __construct()
    {
        $this->tags = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitre(): ?string
    {
        return $this->titre;
    }

    public function setTitre(string $titre): static
    {
        $this->titre = $titre;

        return $this;
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

    public function getDateRecette(): ?\DateTime
    {
        return $this->dateRecette;
    }

    public function setDateRecette(\DateTime $dateRecette): static
    {
        $this->dateRecette = $dateRecette;

        return $this;
    }

    public function getAuteur(): ?User
    {
        return $this->auteur;
    }

    public function setAuteur(?User $auteur): static
    {
        $this->auteur = $auteur;

        return $this;
    }

    public function getIllustration(): ?Illustration
    {
        return $this->illustration;
    }

    public function setIllustration(?Illustration $illustration): static
{
    $this->illustration = $illustration;

    // côté inverse déjà synchronisé dans Illustration::setRecette()
    if ($illustration !== null && $illustration->getRecette() !== $this) {
        $illustration->setRecette($this);
    }

    return $this;
}

        // TODO : remettre cette fonction recette pour la rendre non nullable après avoir testé api.

    /* public function setIllustration(Illustration $illustration): static
    {
        $this->illustration = $illustration;

        return $this;
    } */

    /**
     * @return Collection<int, Tag>
     */
    public function getTags(): Collection
    {
        return $this->tags;
    }

    public function addTag(Tag $tag): static
    {
        if (!$this->tags->contains($tag)) {
            $this->tags->add($tag);
        }

        return $this;
    }

    public function removeTag(Tag $tag): static
    {
        $this->tags->removeElement($tag);

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
}
