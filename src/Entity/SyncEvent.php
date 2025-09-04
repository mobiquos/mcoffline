<?php

namespace App\Entity;

use App\Repository\SyncEventRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SyncEventRepository::class)]
class SyncEvent
{
    const STATUS_PENDING = 'pending';
    const STATUS_INPROGRESS = 'in progress';
    const STATUS_SUCCESS = 'success';
    const STATUS_FAILED = 'failed';

    const TYPE_PUSH = 'push'; // Location to admin
    const TYPE_PULL = 'pull'; // Admin to location
    const TYPE_LOAD = 'load'; // load client data

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 36)]
    private ?string $syncId = null;

    #[ORM\Column]
    private ?\DateTime $createdAt = null;

    #[ORM\ManyToOne]
    private ?User $createdBy = null;

    #[ORM\Column]
    private ?string $status = self::STATUS_INPROGRESS;

    #[ORM\Column(length: 10)]
    private ?string $type = self::TYPE_PUSH;

    #[ORM\ManyToOne(inversedBy: 'syncEvents')]
    private ?Location $location = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $comments = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime;
        // Generate a unique sync ID
        $this->syncId = uniqid('', true);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSyncId(): ?string
    {
        return $this->syncId;
    }

    public function setSyncId(string $syncId): static
    {
        $this->syncId = $syncId;

        return $this;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTime $createdAt): static
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

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getLocation(): ?Location
    {
        return $this->location;
    }

    public function setLocation(?Location $location): static
    {
        $this->location = $location;

        return $this;
    }

    public function getComments(): ?string
    {
        return $this->comments;
    }

    public function setComments(?string $comments): static
    {
        $this->comments = $comments;

        return $this;
    }
}
