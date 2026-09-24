<?php

namespace App\Services;

use RuntimeException;

class UnitConversionService
{
    public function isValidUnit(string $unit): bool
    {
        return array_key_exists($unit, config('units.definitions', []));
    }

    public function typeOf(string $unit): string
    {
        $definition = config("units.definitions.{$unit}");

        if (! $definition) {
            throw new RuntimeException("Đơn vị \"{$unit}\" không hợp lệ.");
        }

        return $definition['type'];
    }

    public function isBaseUnit(string $unit): bool
    {
        return config('units.base_units.' . $this->typeOf($unit)) === $unit;
    }

    public function baseUnitFor(string $unit): string
    {
        return config('units.base_units.' . $this->typeOf($unit));
    }

    /** Danh sách TẤT CẢ đơn vị hợp lệ (base + không phải base). */
    public function allUnits(): array
    {
        return array_keys(config('units.definitions', []));
    }

    /** Danh sách CHỈ các base unit (g, ml, cai) — dùng khi tạo RawMaterial mới. */
    public function baseUnits(): array
    {
        return array_values(config('units.base_units', []));
    }

    /** Quy đổi $quantity từ $fromUnit sang base unit của chính type đó. */
    public function toBase(float $quantity, string $fromUnit): float
    {
        $definition = config("units.definitions.{$fromUnit}");

        if (! $definition) {
            throw new RuntimeException("Đơn vị \"{$fromUnit}\" không hợp lệ.");
        }

        return $quantity * (float) $definition['factor'];
    }

    /**
     * Quy đổi $quantity từ $fromUnit sang đúng $toUnit đã chỉ định.
     * Chỉ hợp lệ khi 2 đơn vị CÙNG type (không quy đổi kg <-> ml).
     */
    public function convert(float $quantity, string $fromUnit, string $toUnit): float
    {
        $this->assertSameType($fromUnit, $toUnit);

        $fromFactor = (float) config("units.definitions.{$fromUnit}.factor");
        $toFactor = (float) config("units.definitions.{$toUnit}.factor");

        return ($quantity * $fromFactor) / $toFactor;
    }

    public function assertSameType(string $unitA, string $unitB): void
    {
        if ($this->typeOf($unitA) !== $this->typeOf($unitB)) {
            throw new RuntimeException(sprintf(
                'Không thể quy đổi giữa 2 đơn vị khác loại: "%s" (%s) và "%s" (%s).',
                $unitA,
                $this->typeOf($unitA),
                $unitB,
                $this->typeOf($unitB)
            ));
        }
    }
}
