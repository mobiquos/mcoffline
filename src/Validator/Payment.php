<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_CLASS)]
class Payment extends Constraint
{
    public $message = 'El RUT "{{ rut }}" no es valido.';
    public $clientNotFoundMessage = 'El cliente con RUT "{{ rut }}" no existe.';
    public $voucherRequiredMessage = 'El ID del voucher es obligatorio para el método de pago indicado.';
    public $voucherExistsMessage = 'El ID "{{ voucherId }}" ya existe durante la contingencia actual.';

    public function __construct(
        public string $mode = 'strict',
        ?array $groups = null,
        mixed $payload = null
    ) {
        parent::__construct([], $groups, $payload);
    }
}
