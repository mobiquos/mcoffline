<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_CLASS)]
class Sale extends Constraint
{
    public $message = 'El RUT "{{ rut }}" no es valido.';
    public $clientNotFoundMessage = 'El cliente con RUT "{{ rut }}" no está registrado.';
    public $folioExistsMessage = 'El número de boleta "{{ folio }}" ya está registrada durante la contingencia.';
    public $quoteNotValidMessage = 'La cotización #{{ quote }} no está vigente.';

    public function __construct(
        public string $mode = 'strict',
        ?array $groups = null,
        mixed $payload = null
    ) {
        parent::__construct([], $groups, $payload);
    }
}
