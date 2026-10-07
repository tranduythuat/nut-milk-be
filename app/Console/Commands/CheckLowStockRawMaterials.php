<?php

namespace App\Console\Commands;

use App\Models\RawMaterial;
use Illuminate\Console\Command;

class CheckLowStockRawMaterials extends Command
{
    protected $signature = 'raw-materials:check-low-stock';
    protected $description = 'Liệt kê các nguyên liệu đang dưới mức tồn kho tối thiểu (min_stock)';

    public function handle(): int
    {
        $lowStockMaterials = RawMaterial::query()
            ->whereNotNull('min_stock')
            ->whereColumn('stock', '<=', 'min_stock')
            ->where('is_active', true)
            ->get();

        if ($lowStockMaterials->isEmpty()) {
            $this->info('Không có nguyên liệu nào dưới mức tồn kho tối thiểu.');
            return self::SUCCESS;
        }

        $this->warn("Có {$lowStockMaterials->count()} nguyên liệu cần nhập thêm:");

        $this->table(
            ['ID', 'Tên', 'Tồn kho', 'Mức tối thiểu', 'Đơn vị'],
            $lowStockMaterials->map(fn($m) => [
                $m->id,
                $m->name,
                $m->stock,
                $m->min_stock,
                $m->unit,
            ])
        );

        // TODO: tích hợp gửi Slack/email tại đây khi cần, dùng
        // config/services.php['slack'] đã có sẵn trong dự án.

        return self::SUCCESS;
    }
}
