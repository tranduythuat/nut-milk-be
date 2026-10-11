<?php

namespace App\Filament\Resources\Combos\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Thành phần combo (1 bộ gồm)';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('product_variant_id')
                ->label('Sản phẩm / phân loại')
                ->relationship(
                    'productVariant',
                    'name',
                    fn($query) => $query->where('is_active', true)->with('product')
                )
                ->getOptionLabelFromRecordUsing(fn($record) => "{$record->product->name} - {$record->name}")
                ->searchable()
                ->preload()
                ->required(),

            TextInput::make('quantity')
                ->label('Số lượng trong 1 bộ')
                ->numeric()
                ->integer()
                ->minValue(1)
                ->default(1)
                ->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('productVariant.product.name')->label('Sản phẩm'),
                TextColumn::make('productVariant.name')->label('Phân loại'),
                TextColumn::make('quantity')->label('Số lượng / bộ'),
            ])
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
