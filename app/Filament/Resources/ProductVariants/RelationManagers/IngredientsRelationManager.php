<?php

namespace App\Filament\Resources\ProductVariants\RelationManagers;

use App\Models\ProductVariantIngredient;
use App\Models\RawMaterial;
use App\Services\UnitConversionService;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rules\Unique;
use RuntimeException;

class IngredientsRelationManager extends RelationManager
{
    protected static string $relationship = 'ingredients';

    protected static ?string $title = 'Công thức (BOM) cho 1 đơn vị';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('raw_material_id')
                ->label('Nguyên liệu')
                ->relationship('rawMaterial', 'name', fn($query) => $query->where('is_active', true))
                ->searchable()
                ->preload()
                ->required()
                // Mỗi nguyên liệu chỉ xuất hiện 1 lần trong công thức của 1 variant
                // (khớp unique index variant_ingredient_unique ở DB).
                ->unique(
                    ignoreRecord: true,
                    modifyRuleUsing: fn(Unique $rule) => $rule->where('product_variant_id', $this->getOwnerRecord()->id)
                ),

            TextInput::make('quantity')
                ->label('Định lượng')
                ->numeric()
                ->required()
                ->minValue(0.0001),

            Select::make('unit')
                ->label('Đơn vị nhập')
                ->options(fn() => collect(app(UnitConversionService::class)->allUnits())
                    ->mapWithKeys(fn($u) => [$u => $u]))
                ->helperText('Để trống = đơn vị gốc của nguyên liệu. Chọn đơn vị khác (vd "l") thì hệ thống tự quy đổi về đơn vị gốc trước khi lưu. Khi sửa, ô Định lượng đang hiển thị theo đơn vị gốc.'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('rawMaterial.name')->label('Nguyên liệu'),
                TextColumn::make('quantity')
                    ->label('Định lượng / 1 đơn vị')
                    ->suffix(fn(ProductVariantIngredient $record) => ' ' . $record->rawMaterial?->unit),
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(fn(array $data): Model => $this->saveIngredient($data)),
            ])
            ->recordActions([
                EditAction::make()
                    ->using(fn(Model $record, array $data): Model => $this->saveIngredient($data, $record)),
                DeleteAction::make(),
            ]);
    }

    /**
     * Quy đổi quantity về đơn vị gốc của nguyên liệu rồi lưu.
     * Cùng nghiệp vụ với ProductVariantIngredientController::sync() ở REST API.
     */
    protected function saveIngredient(array $data, ?Model $record = null): Model
    {
        $material = RawMaterial::findOrFail($data['raw_material_id']);
        $inputUnit = $data['unit'] ?? null;
        $quantity = (float) $data['quantity'];

        if ($inputUnit && $inputUnit !== $material->unit) {
            try {
                $quantity = app(UnitConversionService::class)->convert($quantity, $inputUnit, $material->unit);
            } catch (RuntimeException $e) {
                Notification::make()->title('Lỗi quy đổi đơn vị')->body($e->getMessage())->danger()->send();

                // Hủy lưu: ném lại để Filament dừng action, không ghi gì xuống DB.
                throw new \Filament\Support\Exceptions\Halt();
            }
        }

        $payload = [
            'raw_material_id' => $material->id,
            'quantity' => $quantity,
        ];

        if ($record) {
            $record->update($payload);

            return $record;
        }

        return $this->getOwnerRecord()->ingredients()->create($payload);
    }
}
