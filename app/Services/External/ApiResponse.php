<?php

namespace App\Services\External;

use Illuminate\Http\Client\Response;

class ApiResponse
{
    public function __construct(
        public readonly bool $success,
        public readonly mixed $data,
        public readonly ?string $error,
        public readonly int $statusCode,
        public readonly string $url,
        public readonly ?Response $response = null
    ) {}

    public static function success(mixed $data, int $statusCode = 200, string $url = ''): self
    {
        return new self(true, $data, null, $statusCode, $url, null);
    }

    public static function error(
        string $error,
        int $statusCode = 500,
        string $url = '',
        ?Response $response = null
    ): self {
        return new self(false, null, $error, $statusCode, $url, $response);
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function hasError(): bool
    {
        return !$this->success;
    }

    public function getData(): mixed
    {
        return $this->data;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getResponse(): ?Response
    {
        return $this->response;
    }

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'data' => $this->data,
            'error' => $this->error,
            'status_code' => $this->statusCode,
            'url' => $this->url,
        ];
    }
}
