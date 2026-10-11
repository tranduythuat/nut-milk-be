<?php

namespace App\Filament\Resources\DeliverySlots\Pages;

use App\Filament\Resources\DeliverySlots\DeliverySlotResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDeliverySlot extends EditRecord
{
    protected static string $resource = DeliverySlotResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
