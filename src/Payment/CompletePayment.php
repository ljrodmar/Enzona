<?php

namespace Macroseso\Enzona\Payment;

use InvalidArgumentException;

class CompletePayment
{
    private $request;

    public function __construct(PaymentRequest $request)
    {
        $this->request = $request;
    }

    public function complete($transactionUuid): array
    {
        if (!is_string($transactionUuid) || trim($transactionUuid) === '') {
            throw new InvalidArgumentException('El identificador de la transacción es obligatorio.');
        }

        return $this->request->send('POST', rawurlencode($transactionUuid) . '/complete');
    }
}
