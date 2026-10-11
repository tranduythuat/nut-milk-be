<?php

namespace App\Filament\Resources\Orders\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('order_number')->disabled(),
                TextInput::make('customer_name')->disabled(),
                TextInput::make('customer_phone')->disabled(),
                Textarea::make('customer_address')->disabled(),
                Textarea::make('note'),
            ]);
    }
}
