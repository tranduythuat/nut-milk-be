<?php

namespace Tests\Feature;

use App\Models\RawMaterial;
use App\Services\RawMaterialInventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class RawMaterialConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * KHÔNG phải test concurrency thật (PHPUnit đơn luồng). Đây chỉ xác
     * nhận: khi 2 lần reserve() liên tiếp cộng dồn vượt quá available,
     * lần thứ 2 phải bị chặn — tiền đề bắt buộc để lockForUpdate() có
     * ý nghĩa khi chạy đa luồng thật.
     */
    public function test_sequential_reserve_calls_are_blocked_once_exhausted(): void
    {
        $material = RawMaterial::factory()->create(['stock' => 100, 'reserved_quantity' => 0]);
        $service = app(RawMaterialInventoryService::class);

        $service->reserve($material, 60, null, 'reserve 1');
        $material->refresh();
        $this->assertEquals(60, $material->reserved_quantity);

        $this->expectException(RuntimeException::class);
        $service->reserve($material, 60, null, 'reserve 2 - phải fail vì chỉ còn 40 khả dụng');
    }
}
