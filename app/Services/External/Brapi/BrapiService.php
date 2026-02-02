<?php

namespace App\Services\External\Brapi;

use App\Models\ApiCredential;
use App\Services\External\ApiResponse;
use Exception;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Servico para integracao com a API do Brapi.
 * Baseado na documentacao https://brapi.dev/docs
 */
class BrapiService
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
                $this->credential = ApiCredential::where('key', 'brapi_dev')->first();
            } catch (Throwable $exception) {
                Log::info('BrapiService: ignorando carregamento de credenciais pois o banco ainda nao esta disponivel.', [
                    'exception' => $exception->getMessage(),
                ]);
                $credentialLoadFailed = true;
            }
        }

        if (!$this->credential && !$credentialLoadFailed) {
            Log::warning('Credenciais Brapi nao encontradas na tabela api_credentials com key="brapi_dev"');
        }

        $this->baseUrl = $this->credential?->url_base ?? 'https://brapi.dev/api';
        $this->token = $token ?? $this->credential?->token;
        $this->timeout = 30;
        $this->verifySsl = true;
    }

    private function getBrapiRequest(?int $timeout = null)
    {
        $timeout ??= $this->timeout;
        $request = Http::baseUrl($this->baseUrl)
            ->timeout($timeout)
            ->withOptions([
                'verify' => $this->verifySsl,
            ]);

        if ($this->token) {
            $request->withHeaders([
                'Authorization' => 'Bearer ' . $this->token,
            ]);
        }

        return $request;
    }

    private function isPaidPlan(): bool
    {
        if (!$this->credential) {
            return true;
        }

        return $this->credential->plan !== 'free';
    }

    private function guardPaidFeature(string $featureName, string $endpoint): ?ApiResponse
    {
        if ($this->isPaidPlan()) {
            return null;
        }

        $message = "O recurso '{$featureName}' esta disponivel apenas para planos pagos da Brapi.";
        Log::warning($message);

        return ApiResponse::error($message, 403, $this->baseUrl . $endpoint);
    }

    private function validateQuoteRequestForFreePlan(
        string|array $tickers,
        ?string $range,
        ?string $interval,
        bool $fundamental,
        array $modules
    ): ?ApiResponse {
        if (!$this->isPaidPlan()) {
            $tickersArray = is_array($tickers) ? $tickers : array_map('trim', explode(',', $tickers));
            $tickersArray = array_values(array_filter($tickersArray));

            if (count($tickersArray) > 1) {
                $message = 'O plano gratuito permite consultar apenas um ativo por requisicao.';
                Log::warning($message);
                return ApiResponse::error($message, 403, $this->baseUrl . '/quote');
            }

            if ($fundamental) {
                $message = 'Parametro "fundamental" disponivel apenas em planos pagos da Brapi.';
                Log::warning($message);
                return ApiResponse::error($message, 403, $this->baseUrl . '/quote');
            }

            if ($modules) {
                $allowedModules = ['summaryProfile'];
                $requestedModules = array_filter(array_map('trim', $modules));
                $invalidModules = array_diff($requestedModules, $allowedModules);

                if (!empty($invalidModules)) {
                    $message = 'O plano gratuito suporta apenas o modulo "summaryProfile". ' .
                        'Solicitados: ' . implode(', ', $invalidModules);
                    Log::warning($message);
                    return ApiResponse::error($message, 403, $this->baseUrl . '/quote');
                }
            }

            if ($range) {
                $allowedRanges = ['1d', '2d', '5d', '7d', '1mo', '3mo'];
                if (!in_array($range, $allowedRanges, true)) {
                    $message = 'O plano gratuito permite historico de ate 3 meses ' .
                        '(valores aceitos: 1d, 2d, 5d, 7d, 1mo, 3mo).';
                    Log::warning($message);
                    return ApiResponse::error($message, 403, $this->baseUrl . '/quote');
                }
            }

            if ($interval && $interval !== '1d') {
                $message = 'O plano gratuito permite apenas o intervalo "1d" para dados historicos.';
                Log::warning($message);
                return ApiResponse::error($message, 403, $this->baseUrl . '/quote');
            }
        }

        return null;
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
        array $data = [],
        array $queryParams = [],
        ?int $timeout = null
    ): ApiResponse {
        $url = $endpoint;

        try {
            $this->checkRateLimit();

            $queryParams = array_filter($queryParams, fn ($value) => $value !== null);
            if (!empty($queryParams)) {
                $url .= '?' . http_build_query($queryParams);
            }

            $request = $this->getBrapiRequest($timeout);
            $response = match (strtoupper($method)) {
                'GET' => $request->get($url),
                'POST' => $request->post($url, $data),
                'PUT' => $request->put($url, $data),
                'DELETE' => $request->delete($url, $data),
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

            return ApiResponse::error($exception->getMessage(), $exception->getCode(), $url, $exception->response);
        } catch (Exception $exception) {
            $errorMsg = "Erro na requisicao Brapi [{$method} {$url}]: " . $exception->getMessage();
            Log::error($errorMsg);

            return ApiResponse::error($errorMsg, 500, $url);
        }
    }

    public function getQuote(
        string|array $tickers,
        ?string $range = null,
        ?string $interval = null,
        bool $fundamental = false,
        array $modules = []
    ): ApiResponse {
        $tickersString = is_array($tickers) ? implode(',', $tickers) : $tickers;

        if ($errorResponse = $this->validateQuoteRequestForFreePlan($tickers, $range, $interval, $fundamental, $modules)) {
            return $errorResponse;
        }

        return $this->makeRequest(
            method: 'GET',
            endpoint: "/quote/{$tickersString}",
            queryParams: [
                'range' => $range,
                'interval' => $interval,
                'fundamental' => $fundamental ? 'true' : null,
                'modules' => !empty($modules) ? implode(',', $modules) : null,
            ]
        );
    }

    public function listStocks(
        ?string $search = null,
        ?string $sortBy = null,
        string $sortOrder = 'desc',
        int $limit = 100,
        ?int $page = null
    ): ApiResponse {
        return $this->makeRequest(
            method: 'GET',
            endpoint: '/quote/list',
            queryParams: array_filter([
                'search' => $search,
                'sortBy' => $sortBy,
                'sortOrder' => $sortOrder,
                'limit' => $limit,
                'page' => $page,
            ], fn ($value) => $value !== null)
        );
    }

    public function getCrypto(string|array $coins, string $currency = 'BRL'): ApiResponse
    {
        if ($errorResponse = $this->guardPaidFeature('criptoativos', '/v2/crypto')) {
            return $errorResponse;
        }

        $coinsString = is_array($coins) ? implode(',', $coins) : $coins;

        return $this->makeRequest(
            method: 'GET',
            endpoint: "/v2/crypto/{$coinsString}",
            queryParams: [
                'currency' => $currency,
            ]
        );
    }

    public function listCrypto(string $currency = 'BRL'): ApiResponse
    {
        if ($errorResponse = $this->guardPaidFeature('listagem de criptoativos', '/v2/crypto/available')) {
            return $errorResponse;
        }

        return $this->makeRequest(
            method: 'GET',
            endpoint: '/v2/crypto/available',
            queryParams: [
                'currency' => $currency,
            ]
        );
    }

    public function getCurrency(string|array $currencies): ApiResponse
    {
        if ($errorResponse = $this->guardPaidFeature('taxas de cambio', '/v2/currency')) {
            return $errorResponse;
        }

        $currenciesString = is_array($currencies) ? implode(',', $currencies) : $currencies;

        return $this->makeRequest(
            method: 'GET',
            endpoint: "/v2/currency/{$currenciesString}"
        );
    }

    public function listCurrencies(): ApiResponse
    {
        if ($errorResponse = $this->guardPaidFeature('listagem de moedas', '/v2/currency/available')) {
            return $errorResponse;
        }

        return $this->makeRequest(
            method: 'GET',
            endpoint: '/v2/currency/available'
        );
    }

    public function getInflation(string|array $countries, ?string $historical = null): ApiResponse
    {
        if ($errorResponse = $this->guardPaidFeature('dados de inflacao', '/v2/inflation')) {
            return $errorResponse;
        }

        $countriesString = is_array($countries) ? implode(',', $countries) : $countries;

        return $this->makeRequest(
            method: 'GET',
            endpoint: "/v2/inflation/{$countriesString}",
            queryParams: [
                'historical' => $historical,
            ]
        );
    }

    public function listInflationCountries(): ApiResponse
    {
        if ($errorResponse = $this->guardPaidFeature('listagem de paises com dados de inflacao', '/v2/inflation/available')) {
            return $errorResponse;
        }

        return $this->makeRequest(
            method: 'GET',
            endpoint: '/v2/inflation/available'
        );
    }

    public function getInterestRate(string|array $countries, ?string $historical = null): ApiResponse
    {
        if ($errorResponse = $this->guardPaidFeature('dados de taxa de juros', '/v2/prime-rate')) {
            return $errorResponse;
        }

        $countriesString = is_array($countries) ? implode(',', $countries) : $countries;

        return $this->makeRequest(
            method: 'GET',
            endpoint: "/v2/prime-rate/{$countriesString}",
            queryParams: [
                'historical' => $historical,
            ]
        );
    }

    public function listInterestRateCountries(): ApiResponse
    {
        if ($errorResponse = $this->guardPaidFeature('listagem de paises com taxa de juros', '/v2/prime-rate/available')) {
            return $errorResponse;
        }

        return $this->makeRequest(
            method: 'GET',
            endpoint: '/v2/prime-rate/available'
        );
    }

    public function getDividends(string $ticker, ?string $range = null): ApiResponse
    {
        if ($errorResponse = $this->guardPaidFeature('dividendos', "/quote/{$ticker}/dividends")) {
            return $errorResponse;
        }

        return $this->makeRequest(
            method: 'GET',
            endpoint: "/quote/{$ticker}/dividends",
            queryParams: [
                'range' => $range,
            ]
        );
    }

    public function testarConectividade(): bool
    {
        try {
            $response = $this->getQuote('PETR4');
            return $response->isSuccess();
        } catch (Exception $exception) {
            Log::warning('Falha no teste de conectividade Brapi: ' . $exception->getMessage());
            return false;
        }
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
