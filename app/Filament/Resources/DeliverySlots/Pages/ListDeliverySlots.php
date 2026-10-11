<?php

namespace App\Filament\Resources\DeliverySlots\Pages;

use App\Filament\Resources\DeliverySlots\DeliverySlotResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDeliverySlots extends ListRecords
{
    protected static string $resource = DeliverySlotResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
