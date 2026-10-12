<?php

namespace App\Filament\Resources\ProductionPlans\Pages;

use App\Filament\Resources\ProductionPlans\ProductionPlanResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewProductionPlan extends ViewRecord
{
    protected static string $resource = ProductionPlanResource::class;

    // protected function getHeaderActions(): array
    // {
    //     return [
    //         EditAction::make(),
    //     ];
    // }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return $data;
    }

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->record->load([
            'rawMaterialRequirements.rawMaterial',
            'rawMaterialRequirements.productionPlan',
            'items.productVariant.product',
            'items.productVariant.ingredients',
        ]);
    }
}
