<?php

namespace Macroseso\Enzona;

use Macroseso\Enzona\Models\EnzonaSetting;
use Macroseso\Enzona\Payment\CancelPayment;
use Macroseso\Enzona\Payment\CompletePayment;
use Macroseso\Enzona\Payment\CreatePayment;
use Macroseso\Enzona\Payment\GetPayment;
use Macroseso\Enzona\Payment\PaymentRequest;
use Macroseso\Enzona\Payment\RefundPayment;

class Enzona
{
    private $createPayment;
    private $getPayment;
    private $cancelPayment;
    private $completePayment;
    private $refundPayment;

    public function __construct(EnzonaSetting $settings, PaymentRequest $request)
    {
        $this->createPayment = new CreatePayment($settings, $request);
        $this->getPayment = new GetPayment($request);
        $this->cancelPayment = new CancelPayment($request);
        $this->completePayment = new CompletePayment($request);
        $this->refundPayment = new RefundPayment($request);
    }

    public function createPayment(array $data): array
    {
        return $this->createPayment->processPayment($data);
    }

    public function getPayment($transactionUuid): array
    {
        return $this->getPayment->get($transactionUuid);
    }

    public function cancelPayment($transactionUuid): array
    {
        return $this->cancelPayment->cancel($transactionUuid);
    }

    public function completePayment($transactionUuid): array
    {
        return $this->completePayment->complete($transactionUuid);
    }

    public function refundPayment($transactionUuid, array $data): array
    {
        return $this->refundPayment->refund($transactionUuid, $data);
    }
}
