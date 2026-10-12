<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Services\OrderCancellationService;
use App\Services\OrderStatusService;
use App\Services\PaymentStatusService;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use RuntimeException;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order_number')->searchable(),
                TextColumn::make('customer_name')->searchable(),
                TextColumn::make('customer_phone')->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn(OrderStatus $state) => $state->label())
                    ->color(fn(OrderStatus $state) => match ($state) {
                        OrderStatus::PENDING => 'gray',
                        OrderStatus::CONFIRMED => 'info',
                        OrderStatus::COMPLETED => 'success',
                        OrderStatus::CANCELLED => 'danger',
                    }),
                TextColumn::make('payment_status')->badge(),
                TextColumn::make('total')->money('VND'),
                TextColumn::make('net_payable')->label('Còn phải trả')->money('VND'),
                TextColumn::make('created_at')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(OrderStatus::cases())->mapWithKeys(fn($s) => [$s->value => $s->label()])),

            ])
            ->actions([
                ViewAction::make(),
                Action::make('confirm')
                    ->label('Xác nhận đơn')
                    ->icon('heroicon-o-check')
                    ->visible(fn(Order $record) => $record->status === OrderStatus::PENDING)
                    ->requiresConfirmation()
                    ->action(function (Order $record, OrderStatusService $service) {
                        try {
                            $service->update($record, OrderStatus::CONFIRMED);
                            Notification::make()->title('Đã xác nhận đơn hàng')->success()->send();
                        } catch (RuntimeException $e) {
                            Notification::make()->title('Lỗi')->body($e->getMessage())->danger()->send();
                        }
                    }),

                Action::make('markPaid')
                    ->label('Đánh dấu đã thanh toán')
                    ->icon('heroicon-o-banknotes')
                    ->visible(fn(Order $record) => $record->payment_status === PaymentStatus::PENDING)
                    ->requiresConfirmation()
                    ->action(function (Order $record, PaymentStatusService $service) {
                        $service->update($record, PaymentStatus::PAID);
                        Notification::make()->title('Đã cập nhật thanh toán')->success()->send();
                    }),

                Action::make('cancelOrder')
                    ->label('Hủy toàn bộ đơn')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn(Order $record) => in_array($record->status, [OrderStatus::PENDING, OrderStatus::CONFIRMED], true))
                    ->schema([
                        Textarea::make('note')->label('Lý do hủy'),
                    ])
                    ->requiresConfirmation()
                    ->action(function (Order $record, array $data, OrderCancellationService $service) {
                        try {
                            $service->cancel($record, $data['note'] ?? null);
                            Notification::make()->title('Đã hủy đơn hàng')->success()->send();
                        } catch (RuntimeException $e) {
                            Notification::make()->title('Lỗi')->body($e->getMessage())->danger()->send();
                        }
                    }),
            ]);
    }
}
