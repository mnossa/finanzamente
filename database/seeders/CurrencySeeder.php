<?php

namespace Database\Seeders;

use App\Models\Currency;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $currencies = [
            ['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€'],
            ['code' => 'USD', 'name' => 'Dollaro USA', 'symbol' => '$'],
            ['code' => 'GBP', 'name' => 'Sterlina Britannica', 'symbol' => '£'],
            ['code' => 'CHF', 'name' => 'Franco Svizzero', 'symbol' => 'CHF'],
            ['code' => 'JPY', 'name' => 'Yen Giapponese', 'symbol' => '¥'],
            ['code' => 'CNY', 'name' => 'Yuan Cinese', 'symbol' => '¥'],
            ['code' => 'AUD', 'name' => 'Dollaro Australiano', 'symbol' => 'A$'],
            ['code' => 'CAD', 'name' => 'Dollaro Canadese', 'symbol' => 'C$'],
            ['code' => 'SEK', 'name' => 'Corona Svedese', 'symbol' => 'kr'],
            ['code' => 'NOK', 'name' => 'Corona Norvegese', 'symbol' => 'kr'],
            ['code' => 'DKK', 'name' => 'Corona Danese', 'symbol' => 'kr'],
            ['code' => 'PLN', 'name' => 'Zloty Polacco', 'symbol' => 'zł'],
            ['code' => 'CZK', 'name' => 'Corona Ceca', 'symbol' => 'Kč'],
            ['code' => 'HUF', 'name' => 'Fiorino Ungherese', 'symbol' => 'Ft'],
            ['code' => 'RON', 'name' => 'Leu Rumeno', 'symbol' => 'lei'],
            ['code' => 'BGN', 'name' => 'Lev Bulgaro', 'symbol' => 'лв'],
            ['code' => 'HRK', 'name' => 'Kuna Croata', 'symbol' => 'kn'],
            ['code' => 'RUB', 'name' => 'Rublo Russo', 'symbol' => '₽'],
            ['code' => 'TRY', 'name' => 'Lira Turca', 'symbol' => '₺'],
            ['code' => 'BRL', 'name' => 'Real Brasiliano', 'symbol' => 'R$'],
            ['code' => 'MXN', 'name' => 'Peso Messicano', 'symbol' => '$'],
            ['code' => 'INR', 'name' => 'Rupia Indiana', 'symbol' => '₹'],
            ['code' => 'KRW', 'name' => 'Won Sudcoreano', 'symbol' => '₩'],
            ['code' => 'SGD', 'name' => 'Dollaro di Singapore', 'symbol' => 'S$'],
            ['code' => 'HKD', 'name' => 'Dollaro di Hong Kong', 'symbol' => 'HK$'],
            ['code' => 'NZD', 'name' => 'Dollaro Neozelandese', 'symbol' => 'NZ$'],
            ['code' => 'ZAR', 'name' => 'Rand Sudafricano', 'symbol' => 'R'],
            ['code' => 'AED', 'name' => 'Dirham degli Emirati', 'symbol' => 'د.إ'],
            ['code' => 'SAR', 'name' => 'Riyal Saudita', 'symbol' => '﷼'],
            ['code' => 'BTC', 'name' => 'Bitcoin', 'symbol' => '₿'],
            ['code' => 'ETH', 'name' => 'Ethereum', 'symbol' => 'Ξ'],
        ];

        foreach ($currencies as $currency) {
            Currency::updateOrCreate(
                ['code' => $currency['code']],
                $currency
            );
        }
    }
}
