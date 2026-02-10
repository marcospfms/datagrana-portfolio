<?php

namespace App\Http\Requests\Portfolio;

use App\Models\CompanyTicker;
use App\Models\Treasure;
use Illuminate\Foundation\Http\FormRequest;

class StoreCompositionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'compositions' => ['required', 'array', 'min:1'],
            'compositions.*.type' => ['required', 'in:treasure,company'],
            'compositions.*.asset_id' => ['required', 'integer'],
            'compositions.*.percentage' => ['required', 'numeric', 'min:0', 'max:100'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $compositions = $this->input('compositions');

        if (is_array($compositions) && count($compositions) > 0) {
            return;
        }

        $singleTickerId = $this->input('company_ticker_id');
        $singlePercentage = $this->input('percentage');

        if ($singleTickerId !== null && $singlePercentage !== null) {
            $this->merge([
                'compositions' => [
                    [
                        'type' => 'company',
                        'asset_id' => (int) $singleTickerId,
                        'percentage' => $singlePercentage,
                    ],
                ],
            ]);

            return;
        }

        $tickerPercentages = $this->input('ticker_percentages');
        if (!is_array($tickerPercentages) || empty($tickerPercentages)) {
            return;
        }

        $codes = collect(array_keys($tickerPercentages))
            ->map(fn ($code) => strtoupper(trim((string) $code)))
            ->filter()
            ->values()
            ->all();

        if (empty($codes)) {
            return;
        }

        $tickers = CompanyTicker::whereIn('code', $codes)
            ->where('status', true)
            ->get(['id', 'code'])
            ->keyBy(fn ($ticker) => strtoupper((string) $ticker->code));

        $normalized = [];

        foreach ($tickerPercentages as $code => $percentage) {
            $normalizedCode = strtoupper(trim((string) $code));
            if ($normalizedCode === '') {
                continue;
            }

            $ticker = $tickers->get($normalizedCode);
            $normalized[] = [
                'type' => 'company',
                'asset_id' => $ticker?->id,
                'percentage' => $percentage,
                'asset_code' => $normalizedCode,
            ];
        }

        $this->merge(['compositions' => $normalized]);
    }

    public function messages(): array
    {
        return [
            'compositions.required' => 'Informe pelo menos um ativo.',
            'compositions.*.type.required' => 'O tipo do ativo e obrigatorio.',
            'compositions.*.type.in' => 'O tipo do ativo e invalido.',
            'compositions.*.asset_id.required' => 'O ativo e obrigatorio.',
            'compositions.*.asset_id.integer' => 'O ativo deve ser um identificador valido.',
            'compositions.*.percentage.required' => 'A porcentagem e obrigatoria.',
            'compositions.*.percentage.min' => 'A porcentagem nao pode ser negativa.',
            'compositions.*.percentage.max' => 'A porcentagem nao pode ser maior que 100%.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $compositions = $this->input('compositions', []);
            $companyIds = [];
            $treasureIds = [];

            $tickerPercentages = $this->input('ticker_percentages');
            if (is_array($tickerPercentages) && count($tickerPercentages) > 0) {
                $codes = collect(array_keys($tickerPercentages))
                    ->map(fn ($code) => strtoupper(trim((string) $code)))
                    ->filter()
                    ->values()
                    ->all();

                if (empty($codes)) {
                    $validator->errors()->add('ticker_percentages', 'Informe pelo menos um ticker valido.');
                } else {
                    $validCodes = CompanyTicker::whereIn('code', $codes)
                        ->where('status', true)
                        ->pluck('code')
                        ->map(fn ($code) => strtoupper((string) $code))
                        ->all();

                    foreach ($codes as $code) {
                        if (!in_array($code, $validCodes, true)) {
                            $validator->errors()->add(
                                "ticker_percentages.{$code}",
                                "Ticker {$code} nao encontrado ou inativo."
                            );
                        }
                    }
                }
            }

            foreach ($compositions as $index => $composition) {
                $type = $composition['type'] ?? null;
                $assetId = $composition['asset_id'] ?? null;

                if (!$type || !$assetId) {
                    continue;
                }

                if ($type === 'company') {
                    $companyIds[$index] = $assetId;
                }

                if ($type === 'treasure') {
                    $treasureIds[$index] = $assetId;
                }
            }

            if ($companyIds) {
                $validCompanyIds = CompanyTicker::whereIn('id', array_values($companyIds))
                    ->where('status', true)
                    ->pluck('id')
                    ->all();

                foreach ($companyIds as $index => $assetId) {
                    if (!in_array($assetId, $validCompanyIds, true)) {
                        $validator->errors()->add(
                            "compositions.{$index}.asset_id",
                            'O ativo informado nao existe ou esta inativo.'
                        );
                    }
                }
            }

            if ($treasureIds) {
                $validTreasureIds = Treasure::whereIn('id', array_values($treasureIds))
                    ->where('status', true)
                    ->pluck('id')
                    ->all();

                foreach ($treasureIds as $index => $assetId) {
                    if (!in_array($assetId, $validTreasureIds, true)) {
                        $validator->errors()->add(
                            "compositions.{$index}.asset_id",
                            'O titulo informado nao existe ou esta inativo.'
                        );
                    }
                }
            }
        });
    }
}
