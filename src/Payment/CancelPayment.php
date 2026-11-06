<?php

namespace Macroseso\Enzona\Payment;

use InvalidArgumentException;

class CancelPayment
{
    private $request;

    public function __construct(PaymentRequest $request)
    {
        $this->request = $request;
    }

    public function cancel($transactionUuid): array
    {
        $this->validateTransactionUuid($transactionUuid);

        return $this->request->send('POST', rawurlencode($transactionUuid) . '/cancel');
    }

    private function validateTransactionUuid($transactionUuid): void
    {
        if (!is_string($transactionUuid) || trim($transactionUuid) === '') {
            throw new InvalidArgumentException('El identificador de la transacción es obligatorio.');
        }
    }
}
