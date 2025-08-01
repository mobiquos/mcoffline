<?php

namespace App\Validator;

use App\Entity\Client;
use App\Entity\Sale as SaleEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class SaleValidator extends ConstraintValidator
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function validate($value, Constraint $constraint)
    {
        if (!$value instanceof SaleEntity) {
            return;
        }

        if (!Client::validateRut($value->getQuote()->getRut())) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ rut }}', $value->getQuote()->getRut())
                ->atPath('rut')
                ->addViolation();
        }

        $client = $this->em->getRepository(Client::class)->findOneBy(['rut' => str_replace(['.', '-'], '', $value->getQuote()->getRut())]);
        if (!$client) {
            $this->context->buildViolation($constraint->clientNotFoundMessage)
                ->setParameter('{{ rut }}', $value->getQuote()->getRut())
                ->atPath('rut')
                ->addViolation();
        }

        $existingSale = $this->em->getRepository(SaleEntity::class)->findOneBy([
            'folio' => $value->getFolio(),
            'contingency' => $value->getContingency(),
        ]);

        if ($existingSale) {
            $this->context->buildViolation($constraint->folioExistsMessage)
                ->setParameter('{{ folio }}', $value->getFolio())
                ->atPath('folio')
                ->addViolation();
        }
    }
}
