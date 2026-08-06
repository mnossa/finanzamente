<?php

namespace Tests\Unit\Services\GoogleSheets;

use App\Services\GoogleSheets\GoogleSheetsApiClient;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

class GoogleSheetsDateSerialTest extends TestCase
{
    public function test_italian_and_iso_dates_become_numeric_serial(): void
    {
        $ref = new ReflectionClass(GoogleSheetsApiClient::class);
        $client = $ref->newInstanceWithoutConstructor();

        $method = new ReflectionMethod(GoogleSheetsApiClient::class, 'toSheetsDateSerial');

        $fromIt = $method->invoke($client, '05/08/2024');
        $fromIso = $method->invoke($client, '2024-08-05');

        // 5 agosto 2024 → serial Sheets (non formula locale-dipendente)
        $this->assertIsFloat($fromIt);
        $this->assertSame(45509.0, $fromIt);
        $this->assertSame($fromIt, $fromIso);
        $this->assertSame('', $method->invoke($client, ''));
        $this->assertSame('=A1', $method->invoke($client, '=A1'));

        // DD/MM: 8 maggio ≠ 5 agosto
        $may8 = $method->invoke($client, '08/05/2024');
        $this->assertNotSame($fromIt, $may8);
        $this->assertSame(45420.0, $may8);
    }
}
