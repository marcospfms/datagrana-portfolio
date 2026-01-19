<?php

namespace App\Exceptions;

use Exception;

class InsufficientAssetException extends Exception
{
    public $transactionIndex;
    public $assetInfo;
    public $availableQuantity;
    public $requestedQuantity;

    public function __construct(
        string $message = '',
        ?int $transactionIndex = null,
        ?array $assetInfo = null,
        float $availableQuantity = 0,
        float $requestedQuantity = 0,
        int $code = 0,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);

        $this->transactionIndex = $transactionIndex;
        $this->assetInfo = $assetInfo;
        $this->availableQuantity = $availableQuantity;
        $this->requestedQuantity = $requestedQuantity;
    }

    public function getErrorData(): array
    {
        return [
            'type' => 'insufficient_asset',
            'transaction_index' => $this->transactionIndex,
            'asset_info' => $this->assetInfo,
            'available_quantity' => $this->availableQuantity,
            'requested_quantity' => $this->requestedQuantity,
            'message' => $this->getMessage(),
        ];
    }
}
