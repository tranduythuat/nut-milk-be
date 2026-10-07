<?php

namespace Tests\Feature;

use App\Models\RawMaterial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LowStockCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_lists_low_stock_materials(): void
    {
        RawMaterial::factory()->create(['name' => 'Sắp hết', 'stock' => 5, 'min_stock' => 10]);
        RawMaterial::factory()->create(['name' => 'Đủ hàng', 'stock' => 100, 'min_stock' => 10]);

        $this->artisan('raw-materials:check-low-stock')
            ->expectsOutputToContain('Sắp hết')
            ->doesntExpectOutputToContain('Đủ hàng')
            ->assertExitCode(0);
    }
}
