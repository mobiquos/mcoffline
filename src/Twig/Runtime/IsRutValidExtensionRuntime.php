<?php

namespace App\Twig\Runtime;

use App\Entity\Client;
use Twig\Extension\RuntimeExtensionInterface;

class IsRutValidExtensionRuntime implements RuntimeExtensionInterface
{
    public function __construct()
    {
    }

    public function validate(string $rut): bool|string
    {
        return Client::validateRut($rut);
    }
}
