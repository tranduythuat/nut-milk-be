<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\ActsAsStaff;
use Tests\Feature\Concerns\BuildsProductionTestData;
use Tests\TestCase;

class ProductionCapacityTest extends TestCase
{
    use RefreshDatabase;
    use ActsAsStaff;
    use BuildsProductionTestData;

    public function test_generate_fails_when_demand_exceeds_daily_capacity(): void
    {
        $this->actingAsStaff();

        config(['production.daily_capacity' => 10]);

        [, $variant] = $this->createVariantWithRecipe();
        $date = now()->addDays(2)->toDateString();
        $this->createConfirmedOrder($variant, 15, $date); // vượt 10

        $response = $this->postJson('/api/v1/production-plans/generate', [
            'production_date' => $date,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJson(fn($json) => str_contains(
                $json->toArray()['message'],
                'vượt quá công suất tối đa'
            ));
    }

    public function test_generate_succeeds_when_demand_within_daily_capacity(): void
    {
        $this->actingAsStaff();

        config(['production.daily_capacity' => 20]);

        [, $variant] = $this->createVariantWithRecipe();
        $date = now()->addDays(2)->toDateString();
        $this->createConfirmedOrder($variant, 15, $date);

        $response = $this->postJson('/api/v1/production-plans/generate', [
            'production_date' => $date,
        ]);

        $response->assertOk()->assertJsonPath('data.status', 'draft');
    }

    public function test_null_capacity_means_unlimited(): void
    {
        $this->actingAsStaff();

        config(['production.daily_capacity' => null]);

        [, $variant] = $this->createVariantWithRecipe();
        $date = now()->addDays(2)->toDateString();
        $this->createConfirmedOrder($variant, 100000, $date);

        $response = $this->postJson('/api/v1/production-plans/generate', [
            'production_date' => $date,
        ]);

        $response->assertOk();
    }
}
