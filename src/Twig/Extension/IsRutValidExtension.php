<?php

namespace App\Twig\Extension;

use App\Twig\Runtime\IsRutValidExtensionRuntime;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class IsRutValidExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('is_rut_valid', [IsRutValidExtensionRuntime::class, 'validate']),
        ];
    }
}
