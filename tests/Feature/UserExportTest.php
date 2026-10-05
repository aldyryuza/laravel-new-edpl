<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Support\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;

class UserExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_requires_print_access(): void
    {
        $this->actingAs($this->userWithAccess(['users' => ['read']]))->get('/users/export')->assertForbidden();
    }

    public function test_export_follows_search_and_is_logged(): void
    {
        $budi = User::factory()->create(['nik' => '0012345', 'name' => 'Budi Santoso']);
        $budi->roles()->attach(Role::factory()->create(['name' => 'Sales']));
        User::factory()->create(['name' => 'Ani']);

        $sheet = $this->downloadSheet('/users/export?search=budi');

        $this->assertSame(['NIK', 'Nama', 'Email', 'Role', 'Dibuat'], array_slice($sheet->toArray()[0], 0, 5));
        $this->assertSame(2, $sheet->getHighestDataRow(), 'Hanya heading + 1 user yang cocok.');
        $this->assertSame('0012345', $sheet->getCell('A2')->getValue(), 'Nol di depan NIK harus tetap.');
        $this->assertSame('Budi Santoso', $sheet->getCell('B2')->getValue());
        $this->assertSame('Sales', $sheet->getCell('D2')->getValue());

        $log = Activity::query()->where('log_name', AuditLog::DATA)->where('event', 'exported')->sole();
        $this->assertSame('budi', $log->properties['search']);
    }

    public function test_formula_in_data_is_exported_as_plain_text(): void
    {
        User::factory()->create(['name' => '=HYPERLINK("http://evil.example","klik")']);

        $sheet = $this->downloadSheet('/users/export?search=HYPERLINK');

        $cell = $sheet->getCell('B2');
        $this->assertSame(DataType::TYPE_STRING, $cell->getDataType());
        $this->assertStringStartsWith('=HYPERLINK', $cell->getValue());
    }

    private function downloadSheet(string $url): Worksheet
    {
        $response = $this->actingAs($this->userWithAccess(['users' => ['print']]))->get($url)->assertOk();

        $this->assertInstanceOf(BinaryFileResponse::class, $response->baseResponse);
        $this->assertStringContainsString('daftar-user-', $response->headers->get('content-disposition'));

        return IOFactory::load($response->baseResponse->getFile()->getPathname())->getActiveSheet();
    }
}
