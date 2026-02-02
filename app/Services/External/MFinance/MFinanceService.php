<?php

namespace App\Services\External\MFinance;

use App\Models\ApiCredential;
use App\Services\External\ApiResponse;
use Exception;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class MFinanceService
{
    private string $baseUrl;
    private ?string $token;
    private int $timeout;
    private bool $verifySsl;
    private ?ApiCredential $credential;

    public function __construct(?string $token = null)
    {
        $this->credential = null;
        $credentialLoadFailed = false;

        if (!app()->configurationIsCached() && !file_exists(base_path('.env'))) {
            $credentialLoadFailed = true;
        }

        if (!$credentialLoadFailed) {
            try {
                $this->credential = ApiCredential::where('key', 'm_finance')->first();
            } catch (Throwable $exception) {
                Log::info('MFinanceService: ignorando carregamento de credenciais pois o banco ainda nao esta disponivel.', [
                    'exception' => $exception->getMessage(),
                ]);
                $credentialLoadFailed = true;
            }
        }

        if (!$this->credential && !$credentialLoadFailed) {
            Log::warning('Credenciais m_finance nao encontradas na tabela api_credentials com key="m_finance"');
        }

        $this->baseUrl = $this->credential?->url_base ?? 'https://mfinance.com.br/api';
        $this->token = $token ?? $this->credential?->token;
        $this->timeout = 30;
        $this->verifySsl = true;
    }

    private function getRequest(?int $timeout = null)
    {
        $timeout ??= $this->timeout;

        $request = Http::baseUrl($this->baseUrl)
            ->timeout($timeout)
            ->withOptions(['verify' => $this->verifySsl]);

        if ($this->token) {
            $request->withHeaders(['X-API-Key' => $this->token]);
        }

        return $request;
    }

    private function checkRateLimit(): void
    {
        if (!$this->credential) {
            return;
        }

        $this->credential->refresh();

        if (
            $this->credential->request_limit &&
            $this->credential->request_counter >= $this->credential->request_limit
        ) {
            $limitType = $this->credential->type_limit ?? 'mensal';
            throw new Exception(
                "Limite de {$this->credential->request_limit} requisicoes {$limitType} atingido. " .
                "Contador atual: {$this->credential->request_counter}"
            );
        }
    }

    private function incrementRequestCounter(): void
    {
        if ($this->credential) {
            $this->credential->increment('request_counter');
        }
    }

    private function makeRequest(
        string $method,
        string $endpoint,
        array $queryParams = [],
        array $payload = [],
        ?int $timeout = null
    ): ApiResponse {
        $url = $endpoint;

        try {
            $this->checkRateLimit();

            $queryParams = array_filter($queryParams, fn ($value) => $value !== null);
            if (!empty($queryParams)) {
                $url .= '?' . http_build_query($queryParams);
            }

            $request = $this->getRequest($timeout);
            $response = match (strtoupper($method)) {
                'GET' => $request->get($url),
                'POST' => $request->post($url, $payload),
                'PUT' => $request->put($url, $payload),
                'DELETE' => $request->delete($url, $payload),
                default => throw new Exception("Metodo HTTP '{$method}' nao suportado"),
            };

            $url = $response->effectiveUri()->__toString() ?: $url;

            if ($response->successful()) {
                $this->incrementRequestCounter();

                return ApiResponse::success($response->json(), $response->status(), $url);
            }

            throw new RequestException($response);
        } catch (RequestException $exception) {
            $response = $exception->response;
            $url = $response->effectiveUri()->__toString() ?: $url;

            return ApiResponse::error($exception->getMessage(), $exception->getCode(), $url, $response);
        } catch (Exception $exception) {
            $errorMsg = "Erro na requisicao m_finance [{$method} {$url}]: " . $exception->getMessage();
            Log::error($errorMsg);

            return ApiResponse::error($errorMsg, 500, $url);
        }
    }

    public function getQuote(string $segment, string $ticker): ApiResponse
    {
        return $this->makeRequest(
            method: 'GET',
            endpoint: "/{$segment}/{$ticker}"
        );
    }

    public function getHistorical(string $segment, string $ticker, int $months = 18): ApiResponse
    {
        return $this->makeRequest(
            method: 'GET',
            endpoint: "/{$segment}/historicals/{$ticker}",
            queryParams: ['months' => $months]
        );
    }

    public function getRequestCounter(): int
    {
        return $this->credential?->request_counter ?? 0;
    }

    public function getRequestLimit(): ?int
    {
        return $this->credential?->request_limit;
    }

    public function getLimitType(): ?string
    {
        return $this->credential?->type_limit;
    }

    public function resetRequestCounter(): void
    {
        if ($this->credential) {
            $this->credential->update(['request_counter' => 0]);
        }
    }
}
