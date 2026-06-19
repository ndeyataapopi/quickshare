<?php

namespace Tests\Unit\Security;

use App\Models\User;
use App\Traits\Encryptable;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EncryptableTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        // Create a temporary table for testing
        Schema::create('test_encryptables', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('secret_data');
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('test_encryptables');
        parent::tearDown();
    }

    public function test_sensitive_field_is_encrypted_on_save(): void
    {
        $model = new class extends Model
        {
            use Encryptable;

            protected $table = 'test_encryptables';
            protected $guarded = [];
            protected array $encryptable = ['secret_data'];
        };

        $model->name = 'Test';
        $model->secret_data = '1234567890123';
        $model->save();

        $this->assertNotEquals('1234567890123', $model->getRawOriginal('secret_data'));
    }

    public function test_sensitive_field_is_decrypted_on_retrieve(): void
    {
        $model = new class extends Model
        {
            use Encryptable;

            protected $table = 'test_encryptables';
            protected $guarded = [];
            protected array $encryptable = ['secret_data'];
        };

        $model->name = 'Test';
        $model->secret_data = '1234567890123';
        $model->save();

        $freshModel = $model->fresh();

        $this->assertEquals('1234567890123', $freshModel->secret_data);
    }

    public function test_non_encrypted_fields_unchanged(): void
    {
        $model = new class extends Model
        {
            use Encryptable;

            protected $table = 'test_encryptables';
            protected $guarded = [];
            protected array $encryptable = ['secret_data'];
        };

        $model->name = 'John';
        $model->secret_data = '1234567890123';
        $model->save();

        $this->assertEquals('John', $model->getRawOriginal('name'));
    }
}
