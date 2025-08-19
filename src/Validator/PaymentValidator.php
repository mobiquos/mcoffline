<?php

namespace App\Validator;

use App\Entity\Client;
use App\Entity\Contingency;
use App\Entity\Payment as PaymentEntity;
use App\Entity\SystemParameter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class PaymentValidator extends ConstraintValidator
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function validate($value, Constraint $constraint)
    {
        if (!$value instanceof PaymentEntity) {
            return;
        }

        $maxPaymentAllowed = $this->em->getRepository(SystemParameter::class)->findOneBy(['code' => SystemParameter::PARAM_MAX_PAYMENT_ALLOWED]);
        if ($maxPaymentAllowed && $value->getAmount() > (int)$maxPaymentAllowed->getValue()) {
            $this->context->buildViolation($constraint->maxPaymentAmountMessage)
                ->atPath('amount')
                ->addViolation();
        }

        if (!Client::validateRut($value->getRut())) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ rut }}', $value->getRut())
                ->atPath('rut')
                ->addViolation();
        }

        $client = $this->em->getRepository(Client::class)->findOneBy(['rut' => str_replace(['.', '-'], '', $value->getRut())]);
        if (!$client) {
            $this->context->buildViolation($constraint->clientNotFoundMessage)
                ->setParameter('{{ rut }}', $value->getRut())
                ->atPath('rut')
                ->addViolation();
        }

        if ($client->getOverdue() <= 0) {
            $this->context->buildViolation($constraint->clientWithoutDebtMessage)
                ->atPath('rut')
                ->addViolation();
        }

        if ($value->getPaymentMethod() === PaymentEntity::PAYMENT_METHOD_CASH && $value->getAmount() % 10 !== 0) {
            $this->context->buildViolation($constraint->amountNotDivisibleBy10Message)
                ->atPath('amount')
                ->addViolation();
        }

        if ($value->getPaymentMethod() !== PaymentEntity::PAYMENT_METHOD_CASH && empty($value->getVoucherId())) {
            $this->context->buildViolation($constraint->voucherRequiredMessage)
                ->atPath('voucherId')
                ->addViolation();
        }

        if ($value->getPaymentMethod() !== PaymentEntity::PAYMENT_METHOD_CASH && !empty($value->getVoucherId())) {
            $contingency = $this->em->getRepository(Contingency::class)->findOneBy(['endedAt' => null]);
            if ($contingency) {
                $existingPayment = $this->em->getRepository(PaymentEntity::class)->findOneBy([
                    'voucherId' => $value->getVoucherId(),
                    'contingency' => $contingency,
                ]);

                if ($existingPayment) {
                    $this->context->buildViolation($constraint->voucherExistsMessage)
                        ->setParameter('{{ voucherId }}', $value->getVoucherId())
                        ->atPath('voucherId')
                        ->addViolation();
                }
            }
        }
    }
}
