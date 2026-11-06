<?php

namespace Macroseso\Enzona\Http\Controllers;

class EnzonaCallbackController
{
    public function return()
    {
        return response()->noContent();
    }

    public function cancel()
    {
        return response()->noContent();
    }
}
