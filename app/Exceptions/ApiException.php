<?php

namespace App\Exceptions;

use Exception;

class ApiException extends Exception
{
    protected array $errors;

    public function __construct(string $message = 'An error occurred', int $code = 400, array $errors = [])
    {
        parent::__construct($message, $code);
        $this->errors = $errors;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function render(): \Illuminate\Http\JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $this->getMessage(),
        ];

        if (! empty($this->errors)) {
            $response['errors'] = $this->errors;
        }

        return response()->json($response, $this->getCode());
    }
}
