<?php

namespace Macroseso\Enzona\Payment;

use Exception;

class RefundPayment
{
    private $request;
    private $refundRoute = 'refund';

    public function __construct($accessToken, $usedHost)
    {
        $this->request = new PaymentRequest($accessToken, $usedHost);
    }

    public function refund($uuid, $amount)
    {
        if (!is_string($uuid) || trim($uuid) === '') {
            throw new Exception('El identificador del pago es obligatorio.');
        }
        if (!is_numeric($amount) || $amount <= 0) {
            throw new Exception('El monto del reembolso debe ser numérico y mayor que cero.');
        }

        return $this->request->send('POST', rawurlencode($uuid) . '/' . $this->refundRoute, [
            'amount' => $amount,
        ]);
    }
}
