<?php
 namespace Database\Seeders;

use App\Models\GeoIPLocation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

// Выполни чтобы создать все страны мира и рускоязычные регионы
// php artisan world:install
// php artisan db:seed --class=GeoIPLocationsSeeder

class GeoIPLocationsSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🌍 Синхронизируем гео-данные из таблиц пакета nnjeim/world...');

        // Очищаем нашу таблицу   
        DB::table('geoip_locations')->truncate();

        // Проверяем, существуют ли таблицы пакета
        if (!DB::getSchemaBuilder()->hasTable('countries')) {
            $this->command->error('❌ Таблицы пакета не найдены! Сначала выполни: php artisan world:install');
            return;
        }

        // 1. ЗАЛИВАЕМ ВСЕ СТРАНЫ МИРА
        $countries = DB::table('countries')->get();
        $countryMap = []; // Для связи ID пакета с нашими ID
        $blockedIso = ['IN' => 'Индия', 'NG' => 'Нигерия', 'PH' => 'Филиппины'];

        foreach ($countries as $country) {
            $isBlocked = in_array($country->iso2, array_keys($blockedIso));
            
            $geoCountry = GeoIPLocation::create([
                'parent_id' => null,
                'type' => 'country',
                'name' => $country->name,
                'iso_code' => $country->iso2,
                'is_registration_blocked' => $isBlocked,
                'is_feed_blocked' => $isBlocked,
            ]);
            
            // Запоминаем привязку ID пакета к нашему ID
            $countryMap[$country->id] = $geoCountry->id;
        }
        $this->command->info("✅ Залито стран: {$countries->count()}");

        // 2. ЗАЛИВАЕМ РЕГИОНЫ ТОЛЬКО ДЛЯ СТРАН СНГ
        $cisIsoCodes = ['RU', 'BY', 'KZ', 'UA', 'UZ', 'KG', 'TJ', 'TM', 'MD', 'AZ', 'AM', 'GE'];
        
        // Находим ID стран СНГ в базе пакета
        $cisCountries = DB::table('countries')->whereIn('iso2', $cisIsoCodes)->get();
        $regionsCount = 0;

        foreach ($cisCountries as $cisCountry) {
            if (!isset($countryMap[$cisCountry->id])) continue;

            $ourCountryId = $countryMap[$cisCountry->id];
            
            // Достаем регионы (states) для этой страны
            $states = DB::table('states')->where('country_id', $cisCountry->id)->get();

            foreach ($states as $state) {
                GeoIPLocation::create([
                    'parent_id' => $ourCountryId,
                    'type' => 'region',
                    'name' => $state->name,
                    'iso_code' => $state->iso2 ?? $state->state_code ?? null, // На случай разных версий пакета
                    'is_registration_blocked' => false,
                    'is_feed_blocked' => false,
                ]);
                $regionsCount++;
            }
        }
        
        $this->command->info("✅ Залито регионов СНГ: {$regionsCount}");
        $this->command->warn('🚨 Заблокированы по умолчанию: Индия, Нигерия, Филиппины.');
    }
}