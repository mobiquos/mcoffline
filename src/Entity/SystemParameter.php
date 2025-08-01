<?php

namespace App\Entity;

use App\Form\BooleanStringType;
use App\Repository\SystemParameterRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

#[ORM\Entity(repositoryClass: SystemParameterRepository::class)]
class SystemParameter
{
    const PARAM_LOCATION_CODE = "location_code";
    const PARAM_SERVER_ADDRESS = "server_address";
    const PARAM_MIN_INSTALLMENTS = "min_installments";
    const PARAM_MAX_INSTALLMENTS = "max_installments";
    const PARAM_INTERESTS = "interests";
    const PARAM_MAX_TOTAL = "max_total";
    const PARAM_DOWN_PAYMENT_ALLOWED = "down_payment_allowed";
    const PARAM_MAX_SALES_PER_CLIENT = "max_sales_per_client";
    const PARAM_PERCENT_REQUIRED_BALANCE = "percent_required_balance";
    const PARAM_MAX_SYNC_AGE_IN_DAYS = "max_sync_age_in_days";
    const PARAM_SESSION_LIFETIME = "session_lifetime";

    const PARAMS = [
        self::PARAM_LOCATION_CODE => [
            'name' => "Código Local",
            'description' => "Código único usado para identificar el local. Revisar tabla de locales.",
            'formType' => TextType::class,
            'defaultValue' => "000X",
        ],
        self::PARAM_SERVER_ADDRESS => [
            'name' => "Dirección IP del servidor",
            'description' => "Dirección IP.",
            'formType' => TextType::class,
            'defaultValue' => "localhost",
        ],
        self::PARAM_MIN_INSTALLMENTS => [
            'name' => "Plazo minimo",
            'description' => "Plazo minimo permitido durante simulación.",
            'formType' => IntegerType::class,
            'defaultValue' => 1,
        ],
        self::PARAM_MAX_INSTALLMENTS => [
            'name' => "Plazo máximo",
            'description' => "Plazo màximo permitido durante simulación.",
            'formType' => IntegerType::class,
            'defaultValue' => 24,
        ],
        self::PARAM_MAX_TOTAL => [
            'name' => "Monto máximo compra",
            'description' => "Corresponde al monto máximo permitido para una compra.",
            'formType' => IntegerType::class,
            'defaultValue' => 1000000,
        ],
        self::PARAM_DOWN_PAYMENT_ALLOWED => [
            'name' => "Permitir pie",
            'description' => "Corresponde al monto máximo permitido para una compra.",
            'formType' => BooleanStringType::class,
            'defaultValue' => "false",
        ],
        self::PARAM_MAX_SALES_PER_CLIENT => [
            'name' => "Número de ventas por cliente",
            'description' => "Corresponde al número máximo de ventas permitido a un solo cliente.",
            'formType' => IntegerType::class,
            'defaultValue' => 3,
        ],
        self::PARAM_PERCENT_REQUIRED_BALANCE => [
            'name' => "Porcentaje de saldo requerido",
            'description' => "Corresponde al porcentaje de cupo que el cliente debe tener disponible para realizar una compra.",
            'formType' => IntegerType::class,
            'defaultValue' => 30,
        ],
        self::PARAM_MAX_SYNC_AGE_IN_DAYS => [
            'name' => "Antiguedad máxima de la sincronización",
            'description' => "Corresponde al número de días desde la ultima sincronización para que el sistema pueda entrar en modo de contingencia.",
            'formType' => IntegerType::class,
            'defaultValue' => 7,
        ],
        self::PARAM_INTERESTS => [
            'name' => "Intereses",
            'description' => "Ingresar un listado de intereses donde cada item corresponde a los intereses del mes. 3.7, 3.7, 4.1, 4.1, etc... significa que de 1 a 2 meses los intereses son 3,7% y de 3 a 4 meses son 4.1%.",
            'formType' => TextType::class,
            'defaultValue' => "3.7,3.7,3.7,3.7,3.7,3.7,3.7,3.7,3.7,3.7,3.7,3.7",
        ],
        self::PARAM_SESSION_LIFETIME => [
            'name' => "Duración de la sesión",
            'description' => "Corresponde a la duración de la sesión en minutos.",
            'formType' => IntegerType::class,
            'defaultValue' => 60,
        ]
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $value = null;

    #[ORM\Column(length: 40)]
    private ?string $code = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function setValue(string $value): static
    {
        $this->value = $value;

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
}
