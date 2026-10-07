<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ujian asap aplikasi.
 *
 * Halaman utama ialah papan pemuka pemantauan dan berada di belakang
 * pengesahan (Fasa 4), jadi tetamu dialihkan ke halaman log masuk.
 */
class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_halaman_utama_memerlukan_pengesahan(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    /**
     * Akar tapak ialah pengalih: peranan pemantauan dibawa ke papan pemuka,
     * yang kini berada pada URL tersendiri.
     */
    public function test_halaman_utama_mengalihkan_peranan_pemantauan_ke_papan_pemuka(): void
    {
        $penyelaras = User::factory()->create(['role' => User::ROLE_COORDINATOR]);

        $this->actingAs($penyelaras)->get('/')->assertRedirect(route('dashboard'));
        $this->actingAs($penyelaras)->get(route('dashboard'))->assertOk();
    }

    public function test_titik_semakan_kesihatan_aplikasi_tersedia(): void
    {
        $this->get('/up')->assertOk();
    }
}
