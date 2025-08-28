<?php

namespace App\Entity;

use App\Repository\PaymentRepository;
use App\Validator\Payment as PaymentConstraint;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PaymentRepository::class)]
class Payment
{
    const PAYMENT_METHOD_CASH = "1";
    const PAYMENT_METHOD_DEBIT = "5";
    const PAYMENT_METHOD_CREDIT = "8";

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    #[Assert\Positive]
    private ?int $amount = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $createdBy = null;

    #[ORM\Column(length: 2)]
    private ?string $paymentMethod = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $voucherId = null;

    #[ORM\Column(length: 9)]
    private ?string $rut = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?Device $device = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Contingency $contingency = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $voucherContent = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAmount(): ?int
    {
        return $this->amount;
    }

    public function setAmount(int $amount): static
    {
        $this->amount = $amount;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): static
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function getPaymentMethod(): ?string
    {
        return $this->paymentMethod;
    }

    public function setPaymentMethod(string $paymentMethod): static
    {
        $this->paymentMethod = $paymentMethod;

        return $this;
    }

    public function getVoucherId(): ?string
    {
        return $this->voucherId;
    }

    public function setVoucherId(?string $voucherId): static
    {
        $this->voucherId = $voucherId;

        return $this;
    }

    public function getRut(): ?string
    {
        return $this->rut;
    }

    public function setRut(string $rut): static
    {
        $this->rut = $rut;

        return $this;
    }

    public function getDevice(): ?Device
    {
        return $this->device;
    }

    public function setDevice(?Device $device): static
    {
        $this->device = $device;

        return $this;
    }

    public function getContingency(): ?Contingency
    {
        return $this->contingency;
    }

    public function setContingency(?Contingency $contingency): static
    {
        $this->contingency = $contingency;

        return $this;
    }

    public function getVoucherContent(): ?string
    {
        return $this->voucherContent;
    }

    public function setVoucherContent(?string $voucherContent): static
    {
        $this->voucherContent = $voucherContent;

        return $this;
    }
}
