<?php

namespace Tests\Unit\Helpers;

use App\Helpers\PortfolioHelper;
use PHPUnit\Framework\TestCase;

class PortfolioHelperTest extends TestCase
{
    public function test_calculates_to_buy_quantity(): void
    {
        $result = PortfolioHelper::calculateToBuyQuantity(25.00, 10000.00, 1750.00, 35.00);

        $this->assertEquals(21, $result);
    }

    public function test_returns_zero_when_already_reached_target(): void
    {
        $result = PortfolioHelper::calculateToBuyQuantity(25.00, 10000.00, 3000.00, 35.00);

        $this->assertEquals(0, $result);
    }

    public function test_returns_null_when_no_price(): void
    {
        $result = PortfolioHelper::calculateToBuyQuantity(25.00, 10000.00, 0.00, null);

        $this->assertNull($result);
    }

    public function test_returns_null_when_price_is_zero(): void
    {
        $result = PortfolioHelper::calculateToBuyQuantity(25.00, 10000.00, 0.00, 0.00);

        $this->assertNull($result);
    }

    public function test_returns_zero_when_percentage_is_zero(): void
    {
        $result = PortfolioHelper::calculateToBuyQuantity(0.00, 10000.00, 0.00, 35.00);

        $this->assertEquals(0, $result);
    }

    public function test_returns_dash_when_deleted(): void
    {
        $result = PortfolioHelper::calculateToBuyQuantity(25.00, 10000.00, 0.00, 35.00, '2024-01-01 00:00:00');

        $this->assertEquals('-', $result);
    }

    public function test_formats_quantity_correctly(): void
    {
        $this->assertEquals('21 cotas', PortfolioHelper::formatToBuyQuantity(21));
        $this->assertEquals('0 cotas', PortfolioHelper::formatToBuyQuantity(0));
        $this->assertEquals('-', PortfolioHelper::formatToBuyQuantity('-'));
        $this->assertNull(PortfolioHelper::formatToBuyQuantity(null));
    }
}
