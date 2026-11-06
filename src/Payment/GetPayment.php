<?php

namespace Macroseso\Enzona\Payment;

use InvalidArgumentException;

class GetPayment
{
    private $request;

    public function __construct(PaymentRequest $request)
    {
        $this->request = $request;
    }

    public function get($transactionUuid): array
    {
        if (!is_string($transactionUuid) || trim($transactionUuid) === '') {
            throw new InvalidArgumentException('El identificador de la transacción es obligatorio.');
        }

        return $this->request->send('GET', rawurlencode($transactionUuid));
    }
}
