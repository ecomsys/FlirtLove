<?php
namespace Database\Factories;

use App\Models\UserProfile;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

class UserProfileFactory extends Factory
{
    protected $model = UserProfile::class;

    public function definition(): array
    {
        $gender = fake()->randomElement(['male', 'female']);
        
        return [
            'gender' => $gender,
            'birth_date' => fake()->dateTimeBetween('-55 years', '-18 years')->format('Y-m-d'),
            'dating_goal' => fake()->randomElement(['friends', 'romantic', 'family', 'casual']),
            'city' => fake()->randomElement(['Москва', 'Санкт-Петербург', 'Казань', 'Новосибирск', 'Екатеринбург']),
            'country' => 'Россия',
            'headline' => fake()->boolean(70) ? fake()->sentence(3) : null,
            'bio' => fake()->boolean(80) ? fake()->realTextBetween(100, 500) : null,
            'looking_for' => fake()->boolean(60) ? fake()->sentence(5) : null,
            'interests' => fake()->randomElements(['Кино', 'Музыка', 'Путешествия', 'Спорт', 'IT', 'Книги', 'Танцы', 'Фитнес'], fake()->numberBetween(2, 5)),
            'body_type' => fake()->numberBetween(1, 4),
            'height' => $gender === 'male' ? fake()->numberBetween(165, 200) : fake()->numberBetween(155, 180),
            'weight' => $gender === 'male' ? fake()->numberBetween(65, 110) : fake()->numberBetween(45, 80),
            'relationship_status' => fake()->numberBetween(1, 4),
            'smoking' => fake()->numberBetween(1, 3),
            'alcohol' => fake()->numberBetween(1, 3),
            'education' => fake()->randomElement(['Высшее', 'Среднее', 'Неполное высшее']),
            'occupation' => fake()->jobTitle(),
            'location' => DB::raw(sprintf(
                "ST_SetSRID(ST_MakePoint(%s, %s), 4326)::geography",
                fake()->longitude(37.45, 37.85), fake()->latitude(55.55, 55.95)
            )),
        ];
    }
}