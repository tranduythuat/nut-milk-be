<?php

namespace App\Filament\Resources\Orders\RelationManagers;

use App\Enums\DeliveryStatus;
use App\Services\OrderCancellationService;
use App\Services\OrderDeliveryWorkflowService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use RuntimeException;

class DeliveriesRelationManager extends RelationManager
{
    protected static string $relationship = 'deliveries';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                // TextInput::make('delivery_date')
                //     ->required()
                //     ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('delivery_date')
            ->columns([
                TextColumn::make('delivery_date')->date('d/m/Y'),
                TextColumn::make('status')->badge(),
                TextColumn::make('item_subtotal')->money('VND'),
                TextColumn::make('delivery_fee')->money('VND'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                // CreateAction::make(),
                // AssociateAction::make(),
            ])
            ->recordActions([
                Action::make('startShipping')
                    ->label('Bắt đầu giao (trừ kho)')
                    ->icon('heroicon-o-truck')
                    ->visible(fn($record) => $record->status === DeliveryStatus::PREPARING)
                    ->requiresConfirmation()
                    ->action(function ($record, OrderDeliveryWorkflowService $service) {
                        try {
                            $service->startShipping($record);
                            Notification::make()->title('Đã bắt đầu giao — đã trừ tồn kho thành phẩm')->success()->send();
                        } catch (RuntimeException $e) {
                            Notification::make()->title('Lỗi')->body($e->getMessage())->danger()->send();
                        }
                    }),

                Action::make('markDelivered')
                    ->label('Đã giao thành công')
                    ->icon('heroicon-o-check-circle')
                    ->visible(fn($record) => $record->status === DeliveryStatus::SHIPPING)
                    ->requiresConfirmation()
                    ->action(function ($record, OrderDeliveryWorkflowService $service) {
                        $service->completeDelivery($record);
                        Notification::make()->title('Đã hoàn tất đợt giao')->success()->send();
                    }),

                Action::make('cancelDelivery')
                    ->label('Hủy đợt giao này')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn($record) => in_array($record->status, [DeliveryStatus::PENDING, DeliveryStatus::PREPARING], true))
                    ->schema([Textarea::make('note')])
                    ->requiresConfirmation()
                    ->action(function ($record, array $data, OrderCancellationService $service) {
                        try {
                            $service->cancelDelivery($record, $data['note'] ?? null);
                            Notification::make()->title('Đã hủy đợt giao + tạo khoản hoàn/điều chỉnh tương ứng')->success()->send();
                        } catch (RuntimeException $e) {
                            Notification::make()->title('Lỗi')->body($e->getMessage())->danger()->send();
                        }
                    }),

            ])
            ->toolbarActions([
                // BulkActionGroup::make([
                //     DissociateBulkAction::make(),
                //     DeleteBulkAction::make(),
                // ]),
            ]);
    }
}
