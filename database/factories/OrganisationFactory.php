<?php

namespace Database\Factories;

use App\Models\Organisation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Organisation>
 */
class OrganisationFactory extends Factory
{
    protected $model = Organisation::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'phone' => fake()->phoneNumber(),
            'country_code' => '+44',
            'turnover' => fake()->randomNumber(7),
            'profile_visibility' => 'public',
            'working_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
            'founded_year' => fake()->year(),
            'url' => fake()->url(),
            'progress_step' => 5,
            'appPaymentVersion' => 'free',
            'status' => 'active',
            'HI_include_saturday' => false,
            'HI_include_sunday' => false,
            'admin_email' => fake()->safeEmail(),
            'admin_first_name' => fake()->firstName(),
            'admin_last_name' => fake()->lastName(),
            'billing_address_line1' => fake()->streetAddress(),
            'billing_city' => fake()->city(),
            'billing_postcode' => fake()->postcode(),
            'billing_country' => 'GB',
            'subscription_tier' => 'spark',
            'user_type' => 'organisation',
        ];
    }
}

