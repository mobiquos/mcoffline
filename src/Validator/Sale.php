<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_CLASS)]
class Sale extends Constraint
{
    public $message = 'El RUT "{{ rut }}" no es valido.';
    public $clientNotFoundMessage = 'El cliente con RUT "{{ rut }}" no está registrado.';
    public $folioMissingMessage = 'El número DTE es obligatorio.';
    public $folioExistsMessage = 'El número DTE "{{ folio }}" ya está registrada durante la contingencia.';
    public $folioInvalidMessage = 'El número DTE debe estar en el rango de 1.000.000 a 99.999.999.';
    public $quoteNotValidMessage = 'La simulación #{{ quote }} no está vigente.';

    public function __construct(
        public string $mode = 'strict',
        ?array $groups = null,
        mixed $payload = null
    ) {
        parent::__construct([], $groups, $payload);
    }
}
