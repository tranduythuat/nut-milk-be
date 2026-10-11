<?php

namespace App\Filament\Resources\RawMaterials\Tables;

use App\Models\RawMaterial;
use App\Services\RawMaterialInventoryService;
use App\Services\UnitConversionService;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use RuntimeException;

class RawMaterialsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('code')->searchable(),
                TextColumn::make('stock')
                    ->suffix(fn(RawMaterial $r) => " {$r->unit}")
                    ->color(fn(RawMaterial $r) => $r->is_low_stock ? 'danger' : null),
                TextColumn::make('reserved_quantity')->suffix(fn(RawMaterial $r) => " {$r->unit}"),
                TextColumn::make('available_quantity')
                    ->state(fn(RawMaterial $r) => $r->available_quantity)
                    ->suffix(fn(RawMaterial $r) => " {$r->unit}"),
                IconColumn::make('is_low_stock')->boolean()->label('Sắp hết'),
                IconColumn::make('is_active')->boolean(),
            ])
            ->filters([
                TernaryFilter::make('is_active'),
            ])
            ->recordActions([
                Action::make('receive')
                    ->label('Nhập kho')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->schema([
                        TextInput::make('quantity')->numeric()->required()->minValue(0.0001),
                        Select::make('unit')
                            ->options(fn() => collect(app(UnitConversionService::class)->allUnits())
                                ->mapWithKeys(fn($u) => [$u => $u]))
                            ->helperText('Để trống = dùng đúng đơn vị gốc của nguyên liệu.'),
                        Textarea::make('note'),
                    ])
                    ->action(function (RawMaterial $record, array $data, RawMaterialInventoryService $service) {
                        try {
                            $service->receive($record, (float) $data['quantity'], $data['unit'] ?: null, null, $data['note'] ?? null);
                            Notification::make()->title('Nhập kho thành công')->success()->send();
                        } catch (RuntimeException $e) {
                            Notification::make()->title('Lỗi')->body($e->getMessage())->danger()->send();
                        }
                    }),

                Action::make('adjust')
                    ->label('Kiểm kê')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->color('warning')
                    ->schema([
                        TextInput::make('stock')->numeric()->required()->minValue(0)
                            ->helperText('Số lượng thực tế đếm được.'),
                        Select::make('unit')
                            ->options(fn() => collect(app(UnitConversionService::class)->allUnits())
                                ->mapWithKeys(fn($u) => [$u => $u])),
                        Textarea::make('note')->required(),
                    ])
                    ->action(function (RawMaterial $record, array $data, RawMaterialInventoryService $service) {
                        try {
                            $service->adjustTo($record, (float) $data['stock'], $data['unit'] ?: null, $data['note']);
                            Notification::make()->title('Kiểm kê thành công')->success()->send();
                        } catch (RuntimeException $e) {
                            Notification::make()->title('Lỗi')->body($e->getMessage())->danger()->send();
                        }
                    }),

                EditAction::make(),

            ])
            ->toolbarActions([
                // BulkActionGroup::make([
                //     DeleteBulkAction::make(),
                // ]),
            ]);
    }
}
