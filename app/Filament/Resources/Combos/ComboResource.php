<?php

namespace App\Filament\Resources\Combos;

use App\Filament\Resources\Combos\Pages\CreateCombo;
use App\Filament\Resources\Combos\Pages\EditCombo;
use App\Filament\Resources\Combos\Pages\ListCombos;
use App\Filament\Resources\Combos\Schemas\ComboForm;
use App\Filament\Resources\Combos\Tables\CombosTable;
use App\Models\Combo;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ComboResource extends Resource
{
    protected static ?string $model = Combo::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGift;

    protected static string|UnitEnum|null $navigationGroup = 'Shop';


    public static function form(Schema $schema): Schema
    {
        return ComboForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CombosTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCombos::route('/'),
            'create' => CreateCombo::route('/create'),
            'edit' => EditCombo::route('/{record}/edit'),
        ];
    }
}
