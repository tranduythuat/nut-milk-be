<?php

namespace App\Filament\Resources\RawMaterials\Pages;

use App\Filament\Resources\RawMaterials\RawMaterialResource;
use Filament\Resources\Pages\CreateRecord;
use App\Services\RawMaterialInventoryService;
use Illuminate\Database\Eloquent\Model;

class CreateRawMaterial extends CreateRecord
{
    protected static string $resource = RawMaterialResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $initialStock = $data['stock'] ?? 0;
        unset($data['stock']); // tạo record với stock = 0, rồi receive() để có movement log

        $record = static::getModel()::create([...$data, 'stock' => 0]);

        if ($initialStock > 0) {
            app(RawMaterialInventoryService::class)->receive(
                $record,
                (float) $initialStock,
                null,
                null,
                'Tồn kho ban đầu lúc tạo nguyên liệu.'
            );
        }

        return $record->fresh();
    }
}
