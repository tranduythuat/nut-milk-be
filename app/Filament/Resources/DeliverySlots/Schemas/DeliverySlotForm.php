<?php

namespace App\Filament\Resources\DeliverySlots\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class DeliverySlotForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->required()->placeholder('08:00 - 10:00'),
                TimePicker::make('start_time')->seconds(false)->required(),
                TimePicker::make('end_time')->seconds(false)->required()->after('start_time'),
                TextInput::make('max_orders')->numeric()->minValue(1)
                    ->helperText('Số đợt giao tối đa mỗi ngày trong khung giờ này. Để trống = không giới hạn.'),
                Toggle::make('is_active')->default(true),
                TextInput::make('sort_order')->numeric()->default(0),
            ]);
    }
}
