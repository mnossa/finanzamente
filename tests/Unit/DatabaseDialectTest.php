<?php

namespace Tests\Unit;

use App\Support\DatabaseDialect;
use Illuminate\Database\Connection;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class DatabaseDialectTest extends TestCase
{
    #[Test]
    #[DataProvider('yearMonthProvider')]
    public function year_month_expr_matches_driver(string $driver, string $column, string $expected): void
    {
        $connection = $this->connectionWithDriver($driver);

        $this->assertSame($expected, DatabaseDialect::yearMonthExpr($column, $connection));
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function yearMonthProvider(): array
    {
        return [
            'sqlite' => ['sqlite', 'date', "strftime('%Y-%m', date)"],
            'mysql' => ['mysql', 'date', "DATE_FORMAT(date, '%Y-%m')"],
        ];
    }

    #[Test]
    #[DataProvider('yearProvider')]
    public function year_expr_matches_driver(string $driver, string $expected): void
    {
        $connection = $this->connectionWithDriver($driver);

        $this->assertSame($expected, DatabaseDialect::yearExpr('date', $connection));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function yearProvider(): array
    {
        return [
            'sqlite' => ['sqlite', "CAST(strftime('%Y', date) AS INTEGER)"],
            'mysql' => ['mysql', 'YEAR(date)'],
        ];
    }

    #[Test]
    #[DataProvider('regexProvider')]
    public function column_regex_match_matches_driver(string $driver, string $expected): void
    {
        $connection = $this->connectionWithDriver($driver);

        $this->assertSame($expected, DatabaseDialect::columnRegexMatch('description', '?', $connection));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function regexProvider(): array
    {
        return [
            'sqlite' => ['sqlite', 'regexp(?, description) = 1'],
            'mysql' => ['mysql', 'description REGEXP ?'],
        ];
    }

    #[Test]
    #[DataProvider('likeEscapeProvider')]
    public function supports_like_escape_matches_driver(string $driver, bool $expected): void
    {
        $connection = $this->connectionWithDriver($driver);

        $this->assertSame($expected, DatabaseDialect::supportsLikeEscape($connection));
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function likeEscapeProvider(): array
    {
        return [
            'sqlite' => ['sqlite', false],
            'mysql' => ['mysql', true],
        ];
    }

    private function connectionWithDriver(string $driver): Connection
    {
        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('getDriverName')->andReturn($driver);

        return $connection;
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
