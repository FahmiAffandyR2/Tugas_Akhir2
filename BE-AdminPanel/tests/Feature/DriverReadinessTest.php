<?php
namespace Tests\Feature;
use App\Models\User;
use App\Models\DriverReadiness;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DriverReadinessTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'readiness_testing', 'database.connections.readiness_testing' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::purge('readiness_testing');
        Schema::create('users', function (Blueprint $table) { $table->increments('id'); $table->integer('role'); $table->integer('status_id')->default(1); });
        (require database_path('migrations/2026_09_14_000001_create_driver_readiness_table.php'))->up();
        DB::table('users')->insert([['id' => 1, 'role' => 2], ['id' => 2, 'role' => 2], ['id' => 3, 'role' => 0], ['id' => 4, 'role' => 1]]);
        Storage::fake('local');
        $this->loginAs(1);
    }
    private function loginAs(int $id): void
    {
        Sanctum::actingAs(User::findOrFail($id));
        $this->withHeader('Authorization', 'Bearer 1|test-token');
    }
    private function payload(): array
    {
        $data = ['sim_number' => 'SIM-123', 'health_status' => 'healthy'];
        foreach (['sim', 'health', 'skck'] as $kind) {
            $data[$kind . '_valid_until'] = now()->addMonth()->toDateString();
            $data[$kind . '_file'] = UploadedFile::fake()->create($kind . '.pdf', 10, 'application/pdf');
        }
        return $data;
    }
    public function test_readiness_requires_current_documents_and_healthy_report(): void
    {
        $this->getJson('/api/drivers/readiness')->assertOk()->assertJsonPath('readiness.ready', false);
        $this->postJson('/api/drivers/readiness', $this->payload())->assertOk()->assertJsonPath('readiness.ready', true)->assertJsonMissingPath('readiness.sim_path');
        $this->postJson('/api/drivers/readiness', ['health_status' => 'sick'])->assertUnprocessable()->assertJsonValidationErrors('health_notes');
        $this->postJson('/api/drivers/readiness', ['health_status' => 'sick', 'health_notes' => 'Demam sejak kemarin'])->assertOk()->assertJsonPath('readiness.ready', false);
        $this->loginAs(3);
        $this->getJson('/api/drivers/1/readiness')->assertOk()->assertJsonPath('readiness.health_notes', 'Demam sejak kemarin');
        $record = DriverReadiness::firstOrFail();
        $record->update(['health_status' => 'healthy', 'sim_valid_until' => now()->subDay()->toDateString()]);
        $this->assertFalse($record->fresh()->summary()['ready']);
    }
    public function test_documents_are_private_and_only_owner_or_admin_can_download(): void
    {
        $this->postJson('/api/drivers/readiness', $this->payload())->assertOk();
        $this->get('/api/drivers/1/readiness/documents/sim')->assertOk();
        $this->loginAs(2);
        $this->getJson('/api/drivers/1/readiness/documents/sim')->assertForbidden();
        $this->getJson('/api/drivers/1/readiness')->assertForbidden();
        $this->loginAs(4);
        $this->postJson('/api/drivers/readiness', ['health_status' => 'healthy'])->assertForbidden();
        $this->getJson('/api/drivers/1/readiness/documents/sim')->assertForbidden();
        $this->loginAs(3);
        $this->get('/api/drivers/1/readiness/documents/sim')->assertOk();
    }
    public function test_invalid_upload_is_rejected_and_replacement_removes_old_file(): void
    {
        $data = $this->payload();
        $data['sim_file'] = UploadedFile::fake()->create('bad.exe', 5, 'application/octet-stream');
        $this->postJson('/api/drivers/readiness', $data)->assertUnprocessable()->assertJsonValidationErrors('sim_file');
        $this->postJson('/api/drivers/readiness', $this->payload())->assertOk();
        $old = DriverReadiness::firstOrFail()->sim_path;
        $this->postJson('/api/drivers/readiness', ['health_status' => 'healthy', 'sim_file' => UploadedFile::fake()->create('new.pdf', 10, 'application/pdf')])->assertOk();
        Storage::disk('local')->assertMissing($old);
        Storage::disk('local')->assertExists(DriverReadiness::firstOrFail()->sim_path);
    }
}
