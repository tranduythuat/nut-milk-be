<?php

namespace App\Filament\Resources\ProductionPlans\RelationManagers;

use App\Enums\ProductionStatus;
use App\Services\ProductionQuantityService;
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
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use RuntimeException;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                // TextInput::make('quantity')
                //     ->required()
                //     ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('productVariant.product.name')->label('Sản phẩm'),
                TextColumn::make('productVariant.name')->label('Phân loại'),
                TextColumn::make('planned_quantity')->label('Kế hoạch'),
                TextColumn::make('produced_quantity')->label('Đã sản xuất'),
            ])
            ->recordActions([
                Action::make('updateQuantity')
                    ->label('Cập nhật sản lượng')
                    ->visible(fn() => $this->getOwnerRecord()->status === ProductionStatus::IN_PROGRESS)
                    ->schema([
                        TextInput::make('produced_quantity')->numeric()->required()->minValue(0),
                    ])
                    ->action(function ($record, array $data, ProductionQuantityService $service) {
                        try {
                            $service->update($this->getOwnerRecord(), $record, (int) $data['produced_quantity']);
                            Notification::make()->title('Cập nhật sản lượng thành công')->success()->send();
                        } catch (RuntimeException $e) {
                            Notification::make()->title('Lỗi')->body($e->getMessage())->danger()->send();
                        }
                    }),
            ]);
    }
}
