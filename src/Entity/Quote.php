<?php

namespace App\Entity;

use App\Repository\QuoteRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: QuoteRepository::class)]
class Quote
{
    const PAYMENT_METHOD_CASH = 'cash';
    const PAYMENT_METHOD_CREDIT = 'credit';
    const PAYMENT_METHOD_DEBIT = 'credit';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 9)]
    private ?string $rut = null;

    #[ORM\Column]
    private ?int $amount = null;

    #[ORM\Column(length: 15, nullable: true)]
    private ?string $paymentMethod = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $tbkNumber = null;

    #[ORM\Column(length: 10)]
    private ?string $locationCode = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTime $quoteDate = null;

    #[ORM\ManyToOne()]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $createdBy = null;

    #[ORM\Column()]
    private ?int $downPayment = 0;

    #[ORM\Column(nullable: true)]
    private ?int $deferredPayment = null;

    #[ORM\Column(nullable: false)]
    private ?int $installments = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    private ?float $interest = null;

    #[ORM\Column(nullable: true)]
    private ?int $installmentAmount = null;

    #[ORM\Column(nullable: true)]
    private ?int $totalAmount = null;

    #[ORM\OneToOne(mappedBy: 'quote', cascade: ['persist', 'remove'])]
    private ?Sale $sale = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Contingency $contingency = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $billingDate = null;

    #[ORM\Column(type: Types::INTEGER)]
    private ?int $publicId = null;

    public function __construct()
    {
        $this->quoteDate = new \DateTime;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRut(): ?string
    {
        $rut = str_replace(['-', '.'], '', $this->rut);
        return $rut;
    }

    public function setRut(string $rut): static
    {
        $this->rut = str_replace(["-", "."], "",  $rut);

        return $this;
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

    public function getPaymentMethod(): ?string
    {
        return $this->paymentMethod;
    }

    public function setPaymentMethod(string $paymentMethod): static
    {
        $this->paymentMethod = $paymentMethod;

        return $this;
    }

    public function getTbkNumber(): ?string
    {
        return $this->tbkNumber;
    }

    public function setTbkNumber(?string $tbkNumber): static
    {
        $this->tbkNumber = $tbkNumber;

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

    public function getQuoteDate(): ?\DateTime
    {
        return $this->quoteDate;
    }

    public function setQuoteDate(\DateTime $quoteDate): static
    {
        $this->quoteDate = $quoteDate;

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

    public function getDownPayment(): ?int
    {
        return $this->downPayment;
    }

    public function setDownPayment(int $downPayment): static
    {
        $this->downPayment = $downPayment;

        return $this;
    }

    public function getDeferredPayment(): ?int
    {
        return $this->deferredPayment;
    }

    public function setDeferredPayment(?int $deferredPayment): static
    {
        $this->deferredPayment = $deferredPayment;

        return $this;
    }

    public function getInstallments(): ?int
    {
        return $this->installments;
    }

    public function setInstallments(?int $installments): static
    {
        $this->installments = $installments;

        return $this;
    }

    public function getInterest(): ?float
    {
        return $this->interest;
    }

    public function setInterest(?float $interest): static
    {
        $this->interest = $interest;

        return $this;
    }

    public function getInstallmentAmount(): ?int
    {
        return $this->installmentAmount;
    }

    public function setInstallmentAmount(?int $installmentAmount): static
    {
        $this->installmentAmount = $installmentAmount;

        return $this;
    }

    public function getTotalAmount(): ?int
    {
        return $this->totalAmount;
    }

    public function setTotalAmount(?int $totalAmount): static
    {
        $this->totalAmount = $totalAmount;

        return $this;
    }

    public function getSale(): ?Sale
    {
        return $this->sale;
    }

    public function setSale(Sale $sale): static
    {
        // set the owning side of the relation if necessary
        if ($sale->getQuote() !== $this) {
            $sale->setQuote($this);
        }

        $this->sale = $sale;

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

    public function getBillingDate(): ?\DateTimeInterface
    {
        return $this->billingDate;
    }

    public function setBillingDate(?\DateTimeInterface $billingDate): static
    {
        $this->billingDate = $billingDate;

        return $this;
    }

    public function getPublicId(): ?int
    {
        return $this->publicId;
    }

    public function setPublicId(int $publicId): static
    {
        $this->publicId = $publicId;

        return $this;
    }
}
