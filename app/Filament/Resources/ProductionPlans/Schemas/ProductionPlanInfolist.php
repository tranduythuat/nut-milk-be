<?php

namespace App\Filament\Resources\ProductionPlans\Schemas;

use App\Models\ProductionPlan;
use App\Models\ProductionPlanRawMaterialRequirement;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProductionPlanInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Kế hoạch')
                ->columns(3)
                ->schema([
                    TextEntry::make('production_date')->label('Ngày sản xuất')->date('d/m/Y'),
                    TextEntry::make('status')
                        ->label('Trạng thái')
                        ->badge()
                        ->formatStateUsing(fn($state) => $state->label()),
                    TextEntry::make('material_status')
                        ->label('Nguyên liệu')
                        ->badge()
                        ->state(fn(ProductionPlan $record) => $record->materialStatus()['label'])
                        ->color(fn(ProductionPlan $record) => $record->materialStatus()['color']),
                    TextEntry::make('note')->label('Ghi chú')->placeholder('—')->columnSpanFull(),
                ]),

            Section::make('Cảnh báo')
                ->visible(fn(ProductionPlan $record) => $record->items_without_recipe->isNotEmpty())
                ->schema([
                    TextEntry::make('missing_recipe')
                        ->hiddenLabel()
                        ->color('warning')
                        ->state(fn(ProductionPlan $record) => 'Chưa khai công thức (BOM) cho: ' .
                            $record->items_without_recipe
                            ->map(fn($i) => $i->productVariant->product->name . ' ' . $i->productVariant->name)
                            ->implode(', ') .
                            '. Nhu cầu nguyên liệu của các sản phẩm này chưa được tính.'),
                ]),

            Section::make('Nhu cầu nguyên liệu')
                ->schema([
                    RepeatableEntry::make('rawMaterialRequirements')
                        ->hiddenLabel()
                        ->columns(6)
                        ->schema([
                            TextEntry::make('rawMaterial.name')->label('Nguyên liệu'),

                            TextEntry::make('required_quantity')
                                ->label('Cần')
                                ->state(fn(ProductionPlanRawMaterialRequirement $record) => self::qty($record->required_quantity, $record)),

                            TextEntry::make('available')
                                ->label('Khả dụng trong kho')
                                ->state(fn(ProductionPlanRawMaterialRequirement $record) => self::qty($record->rawMaterial?->available_quantity ?? 0, $record)),

                            TextEntry::make('reserved_quantity')
                                ->label('Đã giữ')
                                ->state(fn(ProductionPlanRawMaterialRequirement $record) => self::qty($record->reserved_quantity, $record)),

                            TextEntry::make('consumed_quantity')
                                ->label('Đã dùng')
                                ->state(fn(ProductionPlanRawMaterialRequirement $record) => self::qty($record->consumed_quantity, $record)),

                            TextEntry::make('shortage')
                                ->label('Tình trạng')
                                ->badge()
                                ->state(function (ProductionPlanRawMaterialRequirement $record) {
                                    $shortage = self::shortage($record);

                                    return $shortage > 0
                                        ? 'Thiếu ' . self::qty($shortage, $record)
                                        : ($record->productionPlan->status->value === 'draft' ? 'Đủ' : 'Đã giữ');
                                })
                                ->color(fn(ProductionPlanRawMaterialRequirement $record) => self::shortage($record) > 0 ? 'danger' : 'success'),
                        ]),
                ]),
        ]);
    }

    /** Chỉ tính thiếu khi plan còn DRAFT (sau đó nguyên liệu đã được giữ chỗ). */
    protected static function shortage(ProductionPlanRawMaterialRequirement $record): float
    {
        if ($record->productionPlan->status->value !== 'draft' || ! $record->rawMaterial) {
            return 0;
        }

        return max(0, (float) $record->required_quantity - $record->rawMaterial->available_quantity);
    }

    protected static function qty(float|string|null $value, ProductionPlanRawMaterialRequirement $record): string
    {
        $formatted = rtrim(rtrim(number_format((float) $value, 4, '.', ','), '0'), '.');

        return $formatted . ' ' . ($record->rawMaterial?->unit ?? '');
    }
}
