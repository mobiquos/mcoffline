<?php

namespace App\Validator;

use App\Entity\Client;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class RutValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        /* @var Rut $constraint */

        if (null === $value || '' === $value) {
            return;
        }

        $result = Client::validateRut(str_replace(["-", "."], "", $value));

        if (true !== $result) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $value)
                ->setCause($result)
                ->addViolation();
        }
    }
}
