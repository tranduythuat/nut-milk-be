<?php

namespace App\Filament\Resources\Orders\RelationManagers;

use App\Enums\RefundStatus;
use App\Enums\RefundType;
use App\Services\RefundService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use RuntimeException;

class RefundsRelationManager extends RelationManager
{
    protected static string $relationship = 'refunds';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('amount')->money('VND'),
                TextColumn::make('type')->badge()->formatStateUsing(fn(RefundType $state) => $state->label()),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn(RefundStatus $state) => $state === RefundStatus::COMPLETED ? 'success' : 'warning'),
                TextColumn::make('note')->limit(50),
            ])
            ->recordActions([
                Action::make('completeCashRefund')
                    ->label('Xác nhận đã chuyển khoản')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->visible(fn($record) => $record->type === RefundType::CASH_REFUND && $record->status === RefundStatus::PENDING)
                    ->schema([Textarea::make('note')->label('Ghi chú xác nhận')])
                    ->requiresConfirmation()
                    ->action(function ($record, array $data, RefundService $service) {
                        try {
                            $service->completeCashRefund($record, $data['note'] ?? null);
                            Notification::make()->title('Đã xác nhận hoàn tiền')->success()->send();
                        } catch (RuntimeException $e) {
                            Notification::make()->title('Lỗi')->body($e->getMessage())->danger()->send();
                        }
                    }),
            ]);
    }
}
