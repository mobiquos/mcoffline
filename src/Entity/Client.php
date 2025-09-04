<?php

namespace App\Entity;

use App\Repository\ClientRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ClientRepository::class)]
class Client
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 9)]
    private ?string $rut = null;

    #[ORM\Column(length: 100)]
    private ?string $firstLastName = null;

    #[ORM\Column(length: 100)]
    private ?string $secondLastName = null;

    #[ORM\Column(length: 200)]
    private ?string $name = null;

    # Cupo autorizado
    #[ORM\Column]
    private ?int $creditLimit = null;

    # Cupo disponible
    #[ORM\Column]
    private ?int $creditAvailable = null;

    #[ORM\Column(nullable: true)]
    private ?int $originalCreditAvailable = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $blockComment = null;

    # deuda
    #[ORM\Column]
    private ?int $overdue = null;

    #[ORM\Column]
    private ?\DateTime $nextBillingAt = null;

    #[ORM\Column]
    private ?\DateTime $lastUpdatedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRut(): ?string
    {
        return $this->rut;
    }

    public function setRut(string $rut): static
    {
        $this->rut = str_replace(["-", "."], "",  $rut);

        return $this;
    }

    public function getFirstLastName(): ?string
    {
        return $this->firstLastName;
    }

    public function setFirstLastName(string $firstLastName): static
    {
        $this->firstLastName = $firstLastName;

        return $this;
    }

    public function getSecondLastName(): ?string
    {
        return $this->secondLastName;
    }

    public function setSecondLastName(string $secondLastName): static
    {
        $this->secondLastName = $secondLastName;

        return $this;
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

    public function getCreditLimit(): ?int
    {
        return $this->creditLimit;
    }

    public function setCreditLimit(int $creditLimit): static
    {
        $this->creditLimit = $creditLimit;

        return $this;
    }

    public function getCreditAvailable(): ?int
    {
        return $this->creditAvailable;
    }

    public function setCreditAvailable(int $creditAvailable): static
    {
        $this->creditAvailable = $creditAvailable;

        return $this;
    }

    public function getOriginalCreditAvailable(): ?int
    {
        return $this->originalCreditAvailable;
    }

    public function setOriginalCreditAvailable(?int $originalCreditAvailable): static
    {
        $this->originalCreditAvailable = $originalCreditAvailable;

        return $this;
    }

    public function getBlockComment(): ?string
    {
        return $this->blockComment;
    }

    public function setBlockComment(?string $blockComment): static
    {
        $this->blockComment = $blockComment;

        return $this;
    }

    public function getOverdue(): ?int
    {
        return $this->overdue;
    }

    public function setOverdue(int $overdue): static
    {
        $this->overdue = $overdue;

        return $this;
    }

    public function getNextBillingAt(): ?\DateTime
    {
        return $this->nextBillingAt;
    }

    public function setNextBillingAt(\DateTime $nextBillingAt): static
    {
        $this->nextBillingAt = $nextBillingAt;

        return $this;
    }

    public function getLastUpdatedAt(): ?\DateTime
    {
        return $this->lastUpdatedAt;
    }

    public function setLastUpdatedAt(\DateTime $lastUpdatedAt): static
    {
        $this->lastUpdatedAt = $lastUpdatedAt;

        return $this;
    }

    public static function validateRut(string $rut): bool|string
    {
        // Verifica que no esté vacio y que el string sea de tamaño mayor a 3 carácteres(1-9)
        if ((empty($rut)) || strlen($rut) < 3) {
            return 'RUT vacío o con menos de 3 caracteres.';
        }

        // Quitar los últimos 2 valores (el guión y el dígito verificador) y luego verificar que sólo sea
        // numérico
        $parteNumerica = str_replace(substr($rut, -1, 1), '', $rut);

        if (!preg_match("/^[0-9]*$/", $parteNumerica)) {
            return 'La parte numérica del RUT sólo debe contener números.';
        }

        $guionYVerificador = substr($rut, -1, 1);
        // Verifica que el guion y dígito verificador tengan un largo de 2.
        if (strlen($guionYVerificador) != 1) {
            return 'Error en el largo del dígito verificador.';
        }

        // obliga a que el dígito verificador tenga la forma -[0-9] o -[kK]
        /* if (!preg_match('/(^[-]{1}+[0-9kK]).{0}$/', $guionYVerificador)) { */
        /*     return 'El dígito verificador no cuenta con el patrón requerido'; */
        /* } */

        // Valida que sólo sean números, excepto el último dígito que pueda ser k
        if (!preg_match("/^[0-9.]+[-]?+[0-9kK]{1}/", $rut)) {
            return 'Error al digitar el RUT';
        }

        $rutV = preg_replace('/[\.\-]/i', '', $rut);
        $dv = substr($rutV, -1);
        $numero = substr($rutV, 0, strlen($rutV) - 1);
        $i = 2;
        $suma = 0;
        foreach (array_reverse(str_split($numero)) as $v) {
            if ($i == 8) {
                $i = 2;
            }
            $suma += $v * $i;
            ++$i;
        }
        $dvr = 11 - ($suma % 11);
        if ($dvr == 11) {
            $dvr = 0;
        }
        if ($dvr == 10) {
            $dvr = 'K';
        }
        if ($dvr != strtoupper($dv)) {
            return "El RUT ingresado no es válido.";
        }
        return true;
    }

    public function getFullName(): string
    {
        return join(" ", [$this->name, $this->firstLastName, $this->secondLastName]);
    }
}
