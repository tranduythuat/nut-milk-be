<?php

namespace App\Filament\Resources\Products\RelationManagers;

use App\Filament\Resources\ProductVariants\ProductVariantResource;
use Filament\Actions\Action;
use Filament\Actions\AssociateAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DissociateAction;
use Filament\Actions\DissociateBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Table;

class VariantsRelationManager extends RelationManager
{
    protected static string $relationship = 'variants';

    protected static ?string $title = 'Phân loại (variants)';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->required()->maxLength(255)
                    ->helperText('Ví dụ: 250ml, 500ml, 1L'),

                TextInput::make('price')->numeric()->required()->minValue(0)->suffix('đ'),

                TextInput::make('attributes.volume')
                    ->label('Dung tích')
                    ->numeric()
                    ->required(),

                Select::make('attributes.unit')
                    ->label('Đơn vị')
                    ->options(['ml' => 'ml', 'L' => 'L'])
                    ->default('ml')
                    ->required(),

                // KHÔNG có field 'stock': tồn kho thành phẩm chỉ đổi qua
                // ProductionQuantityService (sản xuất) và
                // OrderDeliveryWorkflowService (xuất kho khi giao). Variant
                // mới tạo luôn bắt đầu với stock = 0 (default của DB).

                TextInput::make('sort_order')->numeric()->default(0),
                Toggle::make('is_active')->default(true),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')->label('Phân loại'),
                TextColumn::make('price')->money('VND'),
                TextColumn::make('stock')->label('Tồn kho thành phẩm')
                    ->tooltip('Chỉ thay đổi qua sản xuất / giao hàng, không sửa tay.'),
                TextColumn::make('ingredients_count')->counts('ingredients')->label('Số nguyên liệu (BOM)'),
                IconColumn::make('is_active')->boolean(),
            ])
            ->defaultSort('sort_order')
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make(),
                // AssociateAction::make(),
            ])
            ->recordActions([
                Action::make('manageIngredients')
                    ->label('Công thức (BOM)')
                    ->icon('heroicon-o-beaker')
                    ->url(fn($record) => ProductVariantResource::getUrl('edit', ['record' => $record])),
                EditAction::make(),
                // DissociateAction::make(),
                // DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DissociateBulkAction::make(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
