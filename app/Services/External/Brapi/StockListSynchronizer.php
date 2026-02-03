<?php

namespace App\Services\External\Brapi;

use App\Models\Company;
use App\Models\CompanyCategory;
use App\Models\CompanyTicker;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StockListSynchronizer
{
    private const DEFAULT_LIMIT = 100;
    private const DEFAULT_MAX_PAGES = 10;
    private const ALLOWED_REFERENCES = ['ACAO', 'FII', 'ETF', 'BDR'];
    private const TYPE_REFERENCE_MAP = [
        'stock' => 'ACAO',
        'common_stock' => 'ACAO',
        'preferred_stock' => 'ACAO',
        'fund' => 'FII',
        'reit' => 'FII',
        'fii' => 'FII',
        'fii_brazil' => 'FII',
        'etf' => 'ETF',
        'bdr' => 'BDR',
    ];

    private ?array $brlCategoryIds = null;
    private array $logDetails = [];

    public function __construct(private readonly BrapiService $brapiService)
    {
    }

    public function sync(int $limit = self::DEFAULT_LIMIT, int $maxPages = self::DEFAULT_MAX_PAGES): array
    {
        $this->logDetails = [];

        $stocks = $this->fetchStocks($limit, $maxPages);

        if ($stocks->isEmpty()) {
            Log::warning('Brapi StockListSynchronizer: nenhuma acao retornada da API');
            $this->logDetails[] = 'Nenhuma acao retornada pela API.';

            return [
                'processed' => 0,
                'created_tickers' => 0,
                'updated_tickers' => 0,
                'deactivated_tickers' => 0,
                'created_companies' => 0,
                'updated_companies' => 0,
            ];
        }

        return DB::transaction(function () use ($stocks) {
            $activeCodes = [];
            $createdTickers = 0;
            $updatedTickers = 0;
            $createdCompanies = 0;
            $updatedCompanies = 0;

            foreach ($stocks as $stock) {
                $rawCode = strtoupper(Arr::get($stock, 'stock'));

                if (!$rawCode) {
                    $this->logDetails[] = 'Ticker ignorado por codigo vazio.';
                    continue;
                }

                $code = $this->normalizeCode($rawCode);

                if (!$code) {
                    $this->logDetails[] = "Ticker ignorado por codigo invalido: {$rawCode}";
                    continue;
                }

                $mappedReference = $this->mapTypeToReference(Arr::get($stock, 'type'))
                    ?? $this->inferReferenceByCurrency($stock);

                if (!$mappedReference) {
                    $this->logDetails[] = "Ticker {$code} ignorado por nao pertencer a referencias permitidas.";
                    continue;
                }

                $category = CompanyCategory::where('reference', $mappedReference)
                    ->whereHas('coin', function ($query) {
                        $query->where('currency_code', 'BRL');
                    })
                    ->first();

                if (!$category) {
                    $this->logDetails[] = "Categoria BRL nao localizada para ticker {$code} ({$mappedReference}).";
                    continue;
                }

                $ticker = CompanyTicker::query()
                    ->where('code', $code)
                    ->orWhere('code', $rawCode)
                    ->first() ?? new CompanyTicker(['code' => $code]);
                $isNewTicker = !$ticker->exists;

                $company = $ticker->company;
                $createdCompany = false;

                if (!$company) {
                    [$company, $createdCompany, $companyUpdated] = $this->resolveCompany($category, $stock, $code);
                    $updatedCompanies += $companyUpdated ? 1 : 0;
                    $ticker->company_id = $company->id;

                    if ($createdCompany) {
                        $this->logDetails[] = "Empresa criada: {$company->name} ({$company->id})";
                    } elseif ($companyUpdated) {
                        $this->logDetails[] = "Empresa reutilizada/atualizada: {$company->name} ({$company->id})";
                    } else {
                        $this->logDetails[] = "Empresa reutilizada: {$company->name} ({$company->id})";
                    }
                } else {
                    $updatedCompanies += $this->updateCompany($company, $category->id, $stock) ? 1 : 0;
                }

                $tradeCode = Arr::get($stock, 'exchange') ?? 'BVMF';
                if ($tradeCode && $ticker->trade_code !== $tradeCode) {
                    $ticker->trade_code = $tradeCode;
                }

                if ((int) $ticker->status !== 1) {
                    $ticker->status = 1;
                }

                if ((int) $ticker->can_update !== 1) {
                    $ticker->can_update = 1;
                }

                if ($close = $this->asDecimal(Arr::get($stock, 'close'))) {
                    if ($ticker->last_price === null || $ticker->last_price !== $close) {
                        $ticker->last_price = $close;
                        $ticker->last_price_updated = Carbon::now();
                    }
                }

                $dirtyTicker = $ticker->isDirty();
                $ticker->save();

                if ($isNewTicker) {
                    $createdTickers++;
                    $this->logDetails[] = sprintf('Ticker %s criado (empresa %d)', $code, $ticker->company_id);
                } elseif ($dirtyTicker) {
                    $updatedTickers++;
                    $this->logDetails[] = sprintf(
                        'Ticker %s atualizado (empresa %d)',
                        $code,
                        $ticker->company_id
                    );
                }

                $activeCodes[] = $code;

                if ($createdCompany) {
                    $createdCompanies++;
                }
            }

            $deactivatedTickers = $this->deactivateMissingTickers($activeCodes);
            $this->updateCompanyStatuses();

            if ($deactivatedTickers > 0) {
                $this->logDetails[] = "Tickers inativados: {$deactivatedTickers}";
            }

            return [
                'processed' => count($activeCodes),
                'created_tickers' => $createdTickers,
                'updated_tickers' => $updatedTickers,
                'deactivated_tickers' => $deactivatedTickers,
                'created_companies' => $createdCompanies,
                'updated_companies' => $updatedCompanies,
            ];
        });
    }

    private function fetchStocks(int $limit, int $maxPages): Collection
    {
        $page = 1;
        $stocks = collect();

        while ($page <= $maxPages) {
            $response = $this->brapiService->listStocks(sortBy: 'name', sortOrder: 'asc', limit: $limit, page: $page);

            if (!$response->isSuccess()) {
                Log::warning('Brapi StockListSynchronizer: falha ao consultar lista de acoes', [
                    'page' => $page,
                    'error' => $response->getError(),
                ]);
                break;
            }

            $payload = $response->getData();
            $current = collect(Arr::get($payload, 'stocks', Arr::get($payload, 'data', [])));

            if ($current->isEmpty()) {
                break;
            }

            $stocks = $stocks->merge($current);

            if ($current->count() < $limit) {
                break;
            }

            $page++;
        }

        return $stocks;
    }

    private function mapTypeToReference(?string $type): ?string
    {
        if (!$type) {
            return null;
        }

        $normalized = strtolower($type);

        return self::TYPE_REFERENCE_MAP[$normalized] ?? null;
    }

    private function inferReferenceByCurrency(array $stock): ?string
    {
        $currency = strtoupper((string) Arr::get($stock, 'currency', ''));
        $exchange = strtoupper((string) Arr::get($stock, 'exchange', ''));

        $isBrazil = str_contains($currency, 'BRL')
            || str_contains($currency, 'R$')
            || in_array($exchange, ['BVMF', 'B3', 'BOVESPA', 'BOLSA'], true);

        return $isBrazil ? 'ACAO' : null;
    }

    private function sanitizeName(?string $name): ?string
    {
        if (!$name) {
            return null;
        }

        return preg_replace('/\s+/', ' ', trim($name));
    }

    private function updateCompany(Company $company, int $categoryId, array $stock): bool
    {
        $needsUpdate = false;

        if ($company->company_category_id !== $categoryId) {
            $company->company_category_id = $categoryId;
            $needsUpdate = true;
        }

        $name = $this->sanitizeName(Arr::get($stock, 'name'));
        $shortName = $this->sanitizeName(Arr::get($stock, 'shortName'));
        $logo = Arr::get($stock, 'logo');

        if ($name && empty($company->name)) {
            $company->name = $name;
            $needsUpdate = true;
        }

        if ($shortName && empty($company->nickname)) {
            $company->nickname = $shortName;
            $needsUpdate = true;
        }

        if ($logo && empty($company->photo)) {
            $company->photo = $logo;
            $needsUpdate = true;
        }

        if (!$company->status) {
            $company->status = 1;
            $needsUpdate = true;
        }

        if ($needsUpdate) {
            $company->save();
        }

        return $needsUpdate;
    }

    private function deactivateMissingTickers(array $activeCodes): int
    {
        $activeCodes = array_unique($activeCodes);

        if (empty($activeCodes)) {
            Log::warning('Brapi StockListSynchronizer: nenhuma acao elegivel encontrada; ignorando desativacao de tickers.');

            return 0;
        }

        $categoryIds = $this->getBrlCategoryIds();

        if (empty($categoryIds)) {
            return 0;
        }

        return CompanyTicker::query()
            ->whereIn('company_id', function ($query) use ($categoryIds) {
                $query->select('id')
                    ->from('companies')
                    ->whereIn('company_category_id', $categoryIds);
            })
            ->whereNotIn('code', $activeCodes)
            ->update([
                'status' => 0,
                'can_update' => 0,
            ]);
    }

    private function updateCompanyStatuses(): void
    {
        $categoryIds = $this->getBrlCategoryIds();

        if (empty($categoryIds)) {
            return;
        }

        Company::query()
            ->whereIn('company_category_id', $categoryIds)
            ->chunkById(100, function ($companies) {
                foreach ($companies as $company) {
                    $hasActiveTicker = $company->tickers()->where('status', 1)->exists();

                    if ($company->status !== (int) $hasActiveTicker) {
                        $company->status = $hasActiveTicker ? 1 : 0;
                        $company->save();
                    }
                }
            });
    }

    private function asDecimal(mixed $value): ?string
    {
        if (!is_numeric($value)) {
            return null;
        }

        return number_format((float) $value, 8, '.', '');
    }

    private function normalizeCode(string $code): ?string
    {
        $code = trim($code);

        if ($code === '') {
            return null;
        }

        if (str_ends_with($code, 'F')) {
            return substr($code, 0, -1);
        }

        return $code;
    }

    private function resolveCompany(CompanyCategory $category, array $stock, string $code): array
    {
        $name = $this->sanitizeName(Arr::get($stock, 'name'));
        $nickname = $this->sanitizeName(Arr::get($stock, 'shortName') ?? $name ?? $code);
        $logo = Arr::get($stock, 'logo');
        $cnpj = $this->normalizeCnpj(Arr::get($stock, 'cnpj'));

        $company = Company::query()
            ->whereHas('companyCategory.coin', function ($query) {
                $query->where('currency_code', 'BRL');
            })
            ->where(function ($query) use ($name, $nickname, $code, $cnpj) {
                if ($cnpj) {
                    $query->orWhere('cnpj', $cnpj);
                }

                $query->orWhere('name', $code);

                if ($name) {
                    $query->orWhere('name', $name);
                }

                if ($nickname) {
                    $query->orWhere('nickname', $nickname);
                }
            })
            ->first();

        $updated = false;

        if ($company) {
            if ($name && empty($company->name)) {
                $company->name = $name;
                $updated = true;
            }

            if ($nickname && empty($company->nickname)) {
                $company->nickname = $nickname;
                $updated = true;
            }

            if ($logo && empty($company->photo)) {
                $company->photo = $logo;
                $updated = true;
            }

            if (!$company->status) {
                $company->status = 1;
                $updated = true;
            }

            if ($updated) {
                $company->save();
            }

            return [$company, false, $updated];
        }

        $company = Company::create([
            'company_category_id' => $category->id,
            'name' => $name ?: $code,
            'nickname' => $nickname ?: ($name ?: $code),
            'cnpj' => $cnpj,
            'photo' => $logo,
            'status' => 1,
        ]);

        return [$company, true, false];
    }

    private function normalizeCnpj(?string $cnpj): ?string
    {
        if (!$cnpj) {
            return null;
        }

        $digits = preg_replace('/[^0-9]/', '', $cnpj);

        return strlen($digits) === 14 ? $digits : null;
    }

    private function getBrlCategoryIds(): array
    {
        if ($this->brlCategoryIds !== null) {
            return $this->brlCategoryIds;
        }

        return $this->brlCategoryIds = CompanyCategory::query()
            ->whereIn('reference', self::ALLOWED_REFERENCES)
            ->whereHas('coin', function ($query) {
                $query->where('currency_code', 'BRL');
            })
            ->pluck('id')
            ->all();
    }

    public function getLastLogDetails(): array
    {
        return $this->logDetails;
    }
}
