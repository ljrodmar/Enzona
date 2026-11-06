<?php

namespace Macroseso\Enzona\Payment;

use Exception;

class CancelPayment
{
    private $request;
    private $cancelRoute = 'cancel';

    public function __construct($accessToken, $usedHost)
    {
        $this->request = new PaymentRequest($accessToken, $usedHost);
    }

    public function cancel($uuid)
    {
        if (!is_string($uuid) || trim($uuid) === '') {
            throw new Exception('El identificador del pago es obligatorio.');
        }

        return $this->request->send('POST', rawurlencode($uuid) . '/' . $this->cancelRoute);
    }
}
