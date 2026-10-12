<?php

namespace App\Filament\Resources\ProductionPlans\Tables;

use App\Enums\ProductionStatus;
use App\Models\ProductionPlan;
use App\Services\ProductionCompletionService;
use App\Services\ProductionStatusService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Table;
use RuntimeException;

class ProductionPlansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('production_date')->date('d/m/Y')->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn(ProductionStatus $state) => $state->label())
                    ->color(fn(ProductionStatus $state) => match ($state) {
                        ProductionStatus::DRAFT => 'gray',
                        ProductionStatus::PLANNED => 'warning',
                        ProductionStatus::IN_PROGRESS => 'info',
                        ProductionStatus::COMPLETED => 'success',
                        ProductionStatus::CANCELLED => 'danger',
                    }),
                TextColumn::make('items_count')->counts('items')->label('Số loại SP'),
                TextColumn::make('material_status')
                    ->label('Nguyên liệu')
                    ->badge()
                    ->state(fn(ProductionPlan $record) => $record->materialStatus()['label'])
                    ->color(fn(ProductionPlan $record) => $record->materialStatus()['color']),
            ])
            ->modifyQueryUsing(fn(Builder $query) => $query->with([
                'rawMaterialRequirements.rawMaterial',
                'items.productVariant.ingredients',
            ]))
            ->defaultSort('production_date', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(ProductionStatus::cases())->mapWithKeys(fn($s) => [$s->value => $s->label()])),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('confirmPlan')
                    ->label('Xác nhận (giữ nguyên liệu)')
                    ->icon('heroicon-o-lock-closed')
                    ->visible(fn(ProductionPlan $record) => $record->status === ProductionStatus::DRAFT)
                    ->requiresConfirmation()
                    ->action(fn(ProductionPlan $record, ProductionStatusService $service) =>
                    self::runTransition($record, $service, ProductionStatus::PLANNED)),

                Action::make('startProduction')
                    ->label('Bắt đầu sản xuất')
                    ->icon('heroicon-o-play')
                    ->visible(fn(ProductionPlan $record) => $record->status === ProductionStatus::PLANNED)
                    ->requiresConfirmation()
                    ->action(fn(ProductionPlan $record, ProductionStatusService $service) =>
                    self::runTransition($record, $service, ProductionStatus::IN_PROGRESS)),

                Action::make('completePlan')
                    ->label('Hoàn thành sản xuất')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn(ProductionPlan $record) => $record->status === ProductionStatus::IN_PROGRESS)
                    ->requiresConfirmation()
                    ->action(function (ProductionPlan $record, ProductionCompletionService $service) {
                        try {
                            $service->complete($record);
                            Notification::make()->title('Hoàn thành kế hoạch sản xuất')->success()->send();
                        } catch (RuntimeException $e) {
                            Notification::make()->title('Lỗi')->body($e->getMessage())->danger()->send();
                        }
                    }),

                Action::make('cancelPlan')
                    ->label('Hủy kế hoạch')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn(ProductionPlan $record) => in_array($record->status, [ProductionStatus::DRAFT, ProductionStatus::PLANNED], true))
                    ->requiresConfirmation()
                    ->action(fn(ProductionPlan $record, ProductionStatusService $service) =>
                    self::runTransition($record, $service, ProductionStatus::CANCELLED)),
            ]);
    }

    protected static function runTransition(ProductionPlan $record, ProductionStatusService $service, ProductionStatus $target): void
    {
        try {
            $service->updateStatus($record, $target);
            Notification::make()->title('Cập nhật trạng thái thành công')->success()->send();
        } catch (RuntimeException $e) {
            Notification::make()->title('Lỗi')->body($e->getMessage())->danger()->send();
        }
    }
}
