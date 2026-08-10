<?php

namespace Tests\Feature;

use App\Models\LspUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LocalAssessorLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Schema::dropIfExists('lsp_user');
        Schema::create('lsp_user', function (Blueprint $table) {
            $table->id('kdlsp_user');
            $table->string('username')->unique();
            $table->string('password')->nullable();
            $table->string('role');
            $table->boolean('isAsesor')->default(false);
            $table->unsignedBigInteger('kdperson')->nullable();
            $table->unsignedBigInteger('kdunit')->nullable();
            $table->timestamps();
        });
    }

    public function test_active_external_assessor_can_login_with_local_password(): void
    {
        $assessor = LspUser::create([
            'username' => 'rochim', 'password' => Hash::make('rahasia123'),
            'role' => 'asesor_luar', 'isAsesor' => true,
        ]);
        $this->assertTrue(Hash::check('rahasia123', $assessor->fresh()->password));
        $this->assertTrue($assessor->fresh()->isAsesor);

        $this->postJson('/api/login', ['username' => 'rochim', 'password' => 'rahasia123'])
            ->assertOk()->assertJsonPath('message', 'Login asesor berhasil');
        $this->getJson('/api/me')->assertOk()
            ->assertJsonPath('role', 'asesor_luar')
            ->assertJsonPath('lsp_user.username', 'rochim');
    }

    public function test_external_assessor_login_rejects_wrong_password_and_inactive_account(): void
    {
        $assessor = LspUser::create([
            'username' => 'rochim', 'password' => Hash::make('rahasia123'),
            'role' => 'asesor_luar', 'isAsesor' => true,
        ]);

        $this->postJson('/api/login', ['username' => 'rochim', 'password' => 'salah'])
            ->assertUnauthorized();
        $assessor->update(['isAsesor' => false]);
        $this->postJson('/api/login', ['username' => 'rochim', 'password' => 'rahasia123'])
            ->assertUnauthorized();
    }
}
