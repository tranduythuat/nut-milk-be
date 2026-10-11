<?php

namespace App\Filament\Resources\ProductionPlans\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ProductionPlanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                DatePicker::make('production_date')->disabled(),
                TextInput::make('status')->disabled(),
                Textarea::make('note'),
            ]);
    }
}
