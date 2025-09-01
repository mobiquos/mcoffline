<?php

namespace App\Service;

use App\Entity\Sale;
use App\Entity\Payment;
use App\Entity\Device;
use App\Entity\SystemParameter;
use App\Repository\SystemParameterRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class PrintVoucherService
{
    private EntityManagerInterface $entityManager;
    private RequestStack $requestStack;
    private SystemParameterRepository $systemParameterRepository;

    public function __construct(EntityManagerInterface $entityManager, RequestStack $requestStack)
    {
        $this->entityManager = $entityManager;
        $this->requestStack = $requestStack;
        $this->systemParameterRepository = $entityManager->getRepository(SystemParameter::class);
    }

    /**
     * Print a sale voucher
     *
     * @param Sale $sale The sale entity
     * @return bool True if successful, false otherwise
     * @throws \Exception
     */
    public function printSaleVoucher(Sale $sale): bool
    {
        // Get the device associated with the sale
        $device = $sale->getDevice();

        // If no device is associated, try to find one based on the request IP
        if (!$device) {
            $device = $this->findDeviceByRequestIp();
        }

        // If still no device, throw an exception
        if (!$device) {
            throw new \Exception('No se pudo determinar el dispositivo para imprimir el voucher.');
        }

        // Get the voucher file path from the device
        $voucherPath = $device->getPaymentVoucherPath();

        // If no specific path is set, use a default path
        if (!$voucherPath) {
            $voucherPath = $this->getDefaultVoucherPath();
        }

        // Ensure the directory exists
        if (!is_dir($voucherPath)) {
            if (!mkdir($voucherPath, 0777, true)) {
                throw new \Exception('No se pudo crear el directorio para los vouchers: ' . $voucherPath);
            }
        }

        // Get the number of copies to print from system parameters
        $saleVoucherCopiesParam = $this->systemParameterRepository->findByCode(SystemParameter::PARAM_SALE_VOUCHER_COPIES);
        $numberOfCopies = $saleVoucherCopiesParam ? (int)$saleVoucherCopiesParam->getValue() : 1;

        // Generate and write the voucher content for each copy
        $voucherContent = $sale->getVoucherContent();
        if (!$voucherContent) {
            throw new \Exception('No hay contenido de voucher disponible para imprimir.');
        }

        $success = true;
        for ($i = 1; $i <= $numberOfCopies; $i++) {
            // Generate the filename with copy number
            $filename = $this->generateVoucherFilename($sale->getId(), 'sale', $i);
            $filepath = $voucherPath . '/' . $filename;

            $result = file_put_contents($filepath, $voucherContent);
            if ($result === false) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Print a payment voucher
     *
     * @param Payment $payment The payment entity
     * @return bool True if successful, false otherwise
     * @throws \Exception
     */
    public function printPaymentVoucher(Payment $payment): bool
    {
        // Get the device associated with the payment
        $device = $payment->getDevice();

        // If no device is associated, try to find one based on the request IP
        if (!$device) {
            $device = $this->findDeviceByRequestIp();
        }

        // If still no device, throw an exception
        if (!$device) {
            throw new \Exception('No se pudo determinar el dispositivo para imprimir el voucher.');
        }

        // Get the voucher file path from the device
        $voucherPath = $device->getPaymentVoucherPath();

        // If no specific path is set, use a default path
        if (!$voucherPath) {
            $voucherPath = $this->getDefaultVoucherPath();
        }

        // Ensure the directory exists
        if (!is_dir($voucherPath)) {
            if (!mkdir($voucherPath, 0777, true)) {
                throw new \Exception('No se pudo crear el directorio para los vouchers: ' . $voucherPath);
            }
        }

        // Get the number of copies to print from system parameters
        $paymentVoucherCopiesParam = $this->systemParameterRepository->findByCode(SystemParameter::PARAM_PAYMENT_VOUCHER_COPIES);
        $numberOfCopies = $paymentVoucherCopiesParam ? (int)$paymentVoucherCopiesParam->getValue() : 1;

        // Generate and write the voucher content for each copy
        $voucherContent = $payment->getVoucherContent();
        if (!$voucherContent) {
            throw new \Exception('No hay contenido de voucher disponible para imprimir.');
        }

        $success = true;
        for ($i = 1; $i <= $numberOfCopies; $i++) {
            // Generate the filename with copy number
            $filename = $this->generateVoucherFilename($payment->getId(), 'payment', $i);
            $filepath = $voucherPath . '/' . $filename;

            $result = file_put_contents($filepath, $voucherContent);
            if ($result === false) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Find device by request IP address
     *
     * @return Device|null
     */
    private function findDeviceByRequestIp(): ?Device
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            return null;
        }

        $ipAddress = $request->getClientIp();
        if (!$ipAddress) {
            return null;
        }

        return $this->entityManager->getRepository(Device::class)->findOneBy(['ipAddress' => $ipAddress]);
    }

    /**
     * Generate voucher filename with specific format
     *
     * @param int $entityId The entity ID
     * @param string $type The entity type (sale or payment)
     * @param int $copyNumber The copy number (default: 1)
     * @return string
     */
    private function generateVoucherFilename(int $entityId, string $type, int $copyNumber = 1): string
    {
        // Format: CPTCS{type}_{id}_{copy}_{timestamp}.end
        return sprintf('CPTCS%s_%d_%d_%d.end', ucfirst($type), $entityId, $copyNumber, time());
    }

    /**
     * Get default voucher path
     *
     * @return string
     */
    private function getDefaultVoucherPath(): string
    {
        return '/var/vouchers';
    }
}
