<?php

namespace App\Entity;

use App\Repository\ContingencyRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ContingencyRepository::class)]
class Contingency
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'contingencies')]
    #[ORM\JoinColumn(nullable: true, onDelete: "SET NULL")]
    private ?Location $location = null;

    #[ORM\Column(length: 80, nullable: false)]
    private ?string $locationCode = null;

    #[ORM\Column]
    private ?\DateTime $startedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $endedAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: "SET NULL")]
    private ?User $startedBy = null;

    #[ORM\Column(length: 255, nullable: false)]
    private ?string $startedByName = null;

    #[ORM\Column(length: 200, nullable: true)]
    private ?string $comment = null;

    #[ORM\OneToMany(targetEntity: Sale::class, mappedBy: 'contingency')]
    private Collection $sales;

    #[ORM\OneToMany(targetEntity: Payment::class, mappedBy: 'contingency')]
    private Collection $payments;

    public function __construct()
    {
        $this->sales = new ArrayCollection();
        $this->payments = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLocation(): ?Location
    {
        return $this->location;
    }

    public function setLocation(?Location $location): static
    {
        $this->location = $location;
        if ($location != null) {
            $this->locationCode = $location->getCode();
        }

        return $this;
    }

    public function getStartedAt(): ?\DateTime
    {
        return $this->startedAt;
    }

    public function setStartedAt(\DateTime $startedAt): static
    {
        $this->startedAt = $startedAt;

        return $this;
    }

    public function getEndedAt(): ?\DateTime
    {
        return $this->endedAt;
    }

    public function setEndedAt(?\DateTime $endedAt): static
    {
        $this->endedAt = $endedAt;

        return $this;
    }

    public function getStartedBy(): ?User
    {
        return $this->startedBy;
    }

    public function setStartedBy(?User $startedBy): static
    {
        $this->startedBy = $startedBy;
        if ($startedBy != null) {
            $this->startedByName = $startedBy->getFullName();
        }

        return $this;
    }

    public function getStartedByName(): ?string
    {
        return $this->startedByName;
    }

    public function setStartedByName(?string $startedByName): static
    {
        $this->startedByName = $startedByName;

        return $this;
    }

    public function getLocationCode(): ?string
    {
        return $this->locationCode;
    }

    public function setLocationCode(string $locationCode): static
    {
        $this->locationCode = $locationCode;

        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): static
    {
        $this->comment = $comment;

        return $this;
    }

    public function __toString(): string
    {
        return '#' . (string) $this->id;
    }

    public function getSalesQuantity(): int
    {
        return $this->sales->count();
    }

    public function getSalesTotalAmount(): int
    {
        $total = 0;
        foreach ($this->sales as $sale) {
            $total += $sale->getQuote()->getTotalAmount();
        }
        return $total;
    }

    public function getPaymentsQuantity(): int
    {
        return $this->payments->count();
    }

    public function getPaymentsTotalAmount(): int
    {
        $total = 0;
        foreach ($this->payments as $payment) {
            $total += $payment->getAmount();
        }
        return $total;
    }
}
