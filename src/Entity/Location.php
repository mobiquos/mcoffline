<?php

namespace App\Entity;

use App\Repository\LocationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints\Length;

#[ORM\Entity(repositoryClass: LocationRepository::class)]
class Location
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[Length(max:80)]
    #[ORM\Column(length: 80)]
    private ?string $name = null;

    #[Length(max:10)]
    #[ORM\Column(length: 10, unique: true)]
    private ?string $code = null;

    /**
     * @var Collection<int, Contingency>
     */
    #[ORM\OneToMany(targetEntity: Contingency::class, mappedBy: 'location')]
    private Collection $contingencies;

    /**
     * @var Collection<int, Device>
     */
    #[ORM\OneToMany(targetEntity: Device::class, mappedBy: 'location')]
    private Collection $devices;

    public function __construct()
    {
        $this->contingencies = new ArrayCollection();
        $this->devices = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

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

    /**
     * @return Collection<int, Contingency>
     */
    public function getContingencies(): Collection
    {
        return $this->contingencies;
    }

    public function addContingency(Contingency $contingency): static
    {
        if (!$this->contingencies->contains($contingency)) {
            $this->contingencies->add($contingency);
            $contingency->setLocation($this);
        }

        return $this;
    }

    public function removeContingency(Contingency $contingency): static
    {
        if ($this->contingencies->removeElement($contingency)) {
            // set the owning side to null (unless already changed)
            if ($contingency->getLocation() === $this) {
                $contingency->setLocation(null);
            }
        }

        return $this;
    }
    public function __toString(): string
    {
        return sprintf("%s - %s", $this->code, $this->name);
    }

    /**
     * @return Collection<int, Device>
     */
    public function getDevices(): Collection
    {
        return $this->devices;
    }

    public function addDevice(Device $device): static
    {
        if (!$this->devices->contains($device)) {
            $this->devices->add($device);
            $device->setLocation($this);
        }

        return $this;
    }

    public function removeDevice(Device $device): static
    {
        if ($this->devices->removeElement($device)) {
            // set the owning side to null (unless already changed)
            if ($device->getLocation() === $this) {
                $device->setLocation(null);
            }
        }

        return $this;
    }
}
