<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

class ClientDisconnectedException extends HttpException
{
    public function __construct()
    {
        parent::__construct(499, 'Client disconnected');
    }
}
