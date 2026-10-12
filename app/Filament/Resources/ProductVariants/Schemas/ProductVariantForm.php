<?php

namespace App\Filament\Resources\ProductVariants\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ProductVariantForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('product.name')->label('Sản phẩm')->disabled()->dehydrated(false)->formatStateUsing(fn($record) => $record?->product?->name),
                TextInput::make('name')->label('Phân loại')->disabled(),
                TextInput::make('price')->numeric()->disabled(),
                TextInput::make('stock')->label('Tồn kho thành phẩm')->numeric()->disabled(),
            ]);
    }
}
