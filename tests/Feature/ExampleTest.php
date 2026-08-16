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

    public function test_halaman_utama_dipaparkan_kepada_peranan_pemantauan(): void
    {
        $penyelaras = User::factory()->create(['role' => User::ROLE_COORDINATOR]);

        $this->actingAs($penyelaras)->get('/')->assertOk();
    }

    public function test_titik_semakan_kesihatan_aplikasi_tersedia(): void
    {
        $this->get('/up')->assertOk();
    }
}
