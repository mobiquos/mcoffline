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
    public $amountNotDivisibleBy10Message = 'El monto para pagos en efectivo debe ser divisible por 10.';
    public $clientWithoutDebtMessage = 'El cliente no tiene deuda pendiente.';
    public $maxPaymentAmountMessage = 'El monto del pago supera el máximo permitido.';

    public function __construct(
        public string $mode = 'strict',
        ?array $groups = null,
        mixed $payload = null
    ) {
        parent::__construct([], $groups, $payload);
    }
}
