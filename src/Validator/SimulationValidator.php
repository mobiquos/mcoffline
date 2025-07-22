<?php

namespace App\Validator;

use App\Entity\Client;
use App\Entity\Quote;
use App\Entity\SystemParameter;
use App\Repository\ClientRepository;
use App\Repository\QuoteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

final class SimulationValidator extends ConstraintValidator
{
    public function __construct(private EntityManagerInterface $em)
    {
    }
    public function validate(mixed $data, Constraint $constraint): void
    {
        if (!$data instanceof Quote) {
            throw new UnexpectedValueException($data, Quote::class);
        }

        $params = $this->em->getRepository(SystemParameter::class)->createQueryBuilder('e')->select('e.value, e.code')->getQuery()->getResult();
        $params = array_combine(array_column($params, 'code'), array_column($params, 'value'));

        /** @var ClientRepository $clientRepository */
        $clientRepository = $this->em->getRepository(Client::class);
        $client = $clientRepository->findByRut($data->getRut());
        if ($client == null) {
            $this->context->buildViolation("El cliente con el RUT indicado no está registrado.")
                ->atPath('rut')
                ->addViolation()
            ;
            return;
        }

        if (!str_starts_with(trim($client->getBlockComment()), "NO TIENE")) {
            $this->context->buildViolation(sprintf("El cliente no tiene permitido comprar."))
                ->atPath('rut')
                ->addViolation()
            ;
            return;
        }

        if ($params[SystemParameter::PARAM_MAX_TOTAL] < $data->getAmount()) {
            $this->context->buildViolation("El monto a financiar es mayor al máximo permitido durante la contigencia.")
                ->atPath('amount')
                ->addViolation()
            ;
        }

        if ($client->getCreditAvailable() < $data->getAmount()) {
            $this->context->buildViolation("El monto a financiar es mayor al cupo disponible del cliente.")
                ->atPath('amount')
                ->addViolation()
            ;
            return;
        }

        if ($params[SystemParameter::PARAM_MAX_INSTALLMENTS] < $data->getInstallments() || $params[SystemParameter::PARAM_MIN_INSTALLMENTS] > $data->getInstallments() ) {
            $this->context->buildViolation(sprintf("El número de cuotas permitido durante contingencia es entre %d y %d.", $params[SystemParameter::PARAM_MIN_INSTALLMENTS], $params[SystemParameter::PARAM_MAX_INSTALLMENTS]))
                ->atPath('installments')
                ->addViolation()
            ;
            return;
        }

        /** @var QuoteRepository $quotesRepository */
        $quotesRepository = $this->em->getRepository(Quote::class);
        $quotes = $quotesRepository->findByRut($data->getRut());
        if (count($quotes) > $params[SystemParameter::PARAM_MAX_SALES_PER_CLIENT]) {
            $this->context->buildViolation(sprintf("El cliente alcanzo el máximo número de compras permitido durante contingencia."))
                ->atPath('installments')
                ->addViolation()
            ;
            return;
        }
    }
}