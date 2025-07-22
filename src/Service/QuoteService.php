<?php

namespace App\Service;

use App\Entity\Client;
use App\Entity\Quote;
use App\Entity\SystemParameter;
use App\Repository\SystemParameterRepository;
use Doctrine\ORM\EntityManagerInterface;

class QuoteService
{
    private SystemParameterRepository $systemParameterRepository;
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
        $this->systemParameterRepository = $em->getRepository(SystemParameter::class);
    }

    public function calculateInstallment(Quote $quote): array
    {
        $interests = $this->systemParameterRepository->findBy(['code' => SystemParameter::PARAM_INTERESTS]);
        $interests = explode(",", current($interests)->getValue());

        $x = $quote->getAmount();
        $p = $quote->getInstallments();
        $t = (float) $p >= 12 ? $interests[11] : $interests[$p - 1];
        $installmentAmount = $t == 0 ? round($x / $p) : round(($x * $t / 100) / (1 - pow((1 + $t / 100), -$p)));
        $total = $installmentAmount * $quote->getInstallments();

        return [
            'installment_amount' => $installmentAmount,
            'total' => $total,
            'interest' => $t,
        ];
    }
}
