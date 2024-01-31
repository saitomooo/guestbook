<?php

namespace App\Entity;
use ApiPlatform\Core\Annotation\ApiResource;
use App\Repository\TestingRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TestingRepository::class)]
#[ApiResource]
class Testing
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $testing = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTesting(): ?string
    {
        return $this->testing;
    }

    public function setTesting(string $testing): static
    {
        $this->testing = $testing;

        return $this;
    }
}
