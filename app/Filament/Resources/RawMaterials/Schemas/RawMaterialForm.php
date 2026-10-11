<?php

namespace App\Filament\Resources\RawMaterials\Schemas;

use App\Models\RawMaterial;
use App\Services\UnitConversionService;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class RawMaterialForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->required()->maxLength(255),
                TextInput::make('code')->required()->unique(ignoreRecord: true),

                Select::make('unit')
                    ->options(fn() => collect(app(UnitConversionService::class)->baseUnits())
                        ->mapWithKeys(fn($u) => [$u => $u]))
                    ->required()
                    ->disabled(fn(?RawMaterial $record) => $record !== null)
                    ->dehydrated(fn(?RawMaterial $record) => $record === null),

                TextInput::make('stock')->numeric()->disabled()
                    ->helperText('Không sửa trực tiếp — dùng action "Nhập kho" hoặc "Kiểm kê" ở bảng danh sách.'),
                TextInput::make('reserved_quantity')->numeric()->disabled(),
                TextInput::make('min_stock')->numeric(),
                Toggle::make('is_active')->default(true),
            ]);
    }
}
