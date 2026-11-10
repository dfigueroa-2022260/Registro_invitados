<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

/** Error controlado de la API: se responde como JSON { message } con el estado indicado. */
class ApiError extends Exception
{
    public function __construct(string $message, public readonly int $estado = 400)
    {
        parent::__construct($message);
    }

    public function render($request): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], $this->estado);
    }
}
