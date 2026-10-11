<?php

namespace App\Filament\Resources\DeliverySlots;

use App\Filament\Resources\DeliverySlots\Pages\CreateDeliverySlot;
use App\Filament\Resources\DeliverySlots\Pages\EditDeliverySlot;
use App\Filament\Resources\DeliverySlots\Pages\ListDeliverySlots;
use App\Filament\Resources\DeliverySlots\Schemas\DeliverySlotForm;
use App\Filament\Resources\DeliverySlots\Tables\DeliverySlotsTable;
use App\Models\DeliverySlot;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DeliverySlotResource extends Resource
{
    protected static ?string $model = DeliverySlot::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return DeliverySlotForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DeliverySlotsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDeliverySlots::route('/'),
            'create' => CreateDeliverySlot::route('/create'),
            'edit' => EditDeliverySlot::route('/{record}/edit'),
        ];
    }
}
