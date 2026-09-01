<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\WorkflowStageStatus;
use App\Support\AliranKerja;
use Tests\TestCase;

/**
 * Struktur aliran kerja rasmi: LIMA peringkat utama, sebahagiannya
 * mengandungi sub-peringkat.
 *
 * Ujian ini mengunci STRUKTUR, bukan sekadar nama. Perbezaannya penting:
 * sistem lapan langkah rata dengan nama yang betul akan lulus ujian nama,
 * tetapi gagal di sini.
 */
class AliranKerjaTest extends TestCase
{
    public function test_lima_peringkat_utama_sahaja(): void
    {
        $this->assertCount(5, AliranKerja::UTAMA);

        $this->assertSame([
            1 => 'Penerimaan & Semakan Awal Data',
            2 => 'Penyediaan & Pengesahan Data',
            3 => 'Analisis Data',
            4 => 'Penjanaan Laporan',
            5 => 'Semakan, Kelulusan & Penyerahan Laporan',
        ], AliranKerja::UTAMA);
    }

    public function test_peringkat_satu_mengandungi_tiga_sub_peringkat(): void
    {
        $this->assertSame(['1.1', '1.2', '1.3'], AliranKerja::subPeringkat(1));

        $this->assertSame('Penerimaan Data', AliranKerja::label('1.1'));
        $this->assertSame('Pendaftaran Data', AliranKerja::label('1.2'));
        $this->assertSame('Semakan Awal Data', AliranKerja::label('1.3'));

        $this->assertTrue(AliranKerja::adaSubPeringkat(1));
    }

    public function test_peringkat_tiga_mengandungi_dua_sub_peringkat(): void
    {
        $this->assertSame(['3.1', '3.2'], AliranKerja::subPeringkat(3));

        $this->assertSame('Analisis Inventori Kriptografi', AliranKerja::label('3.1'));
        $this->assertSame('Analisis Risiko Migrasi PQC', AliranKerja::label('3.2'));

        $this->assertTrue(AliranKerja::adaSubPeringkat(3));
    }

    /**
     * Peringkat 2, 4 dan 5 ialah proses TUNGGAL. Memecahkannya kepada
     * sub-peringkat rekaan akan menyalahi struktur rasmi.
     */
    public function test_peringkat_dua_empat_dan_lima_ialah_proses_tunggal(): void
    {
        foreach ([2, 4, 5] as $utama) {
            $this->assertSame([(string) $utama], AliranKerja::subPeringkat($utama));
            $this->assertFalse(AliranKerja::adaSubPeringkat($utama));
        }
    }

    public function test_turutan_aliran_mengikut_struktur_rasmi(): void
    {
        $this->assertSame(
            ['1.1', '1.2', '1.3', '2', '3.1', '3.2', '4', '5'],
            AliranKerja::kekunci(),
        );
    }

    /**
     * Fasa semasa berakhir pada 3.1. Peringkat 3.2, 4 dan 5 ditakrifkan
     * tetapi belum dibina.
     */
    public function test_fasa_semasa_berakhir_pada_analisis_inventori(): void
    {
        $this->assertSame(['1.1', '1.2', '1.3', '2', '3.1'], AliranKerja::semasa());
        $this->assertSame(['3.2', '4', '5'], AliranKerja::akanDatang());

        $this->assertSame('3.1', AliranKerja::TERAKHIR_SEMASA);

        foreach (['3.2', '4', '5'] as $kunci) {
            $this->assertTrue(AliranKerja::adalahAkanDatang($kunci));
            $this->assertFalse(AliranKerja::adalahSemasa($kunci));
        }
    }

    /**
     * Peringkat fasa akan datang tiada peranan dan tiada gate — tiada
     * tindakan boleh disambungkan kepadanya secara tidak sengaja.
     */
    public function test_peringkat_fasa_akan_datang_tiada_peranan_atau_gate(): void
    {
        foreach (AliranKerja::akanDatang() as $kunci) {
            $this->assertSame([], AliranKerja::peranan($kunci));
            $this->assertNull(AliranKerja::gate($kunci));
            $this->assertSame([], AliranKerja::medan($kunci));
        }
    }

    public function test_peranan_bertanggungjawab_mengikut_jadual_rasmi(): void
    {
        $this->assertSame(
            [User::ROLE_KETUA_BAHAGIAN, User::ROLE_COORDINATOR],
            AliranKerja::peranan('1.1'),
        );

        $this->assertSame([User::ROLE_COORDINATOR], AliranKerja::peranan('1.2'));
        $this->assertSame([User::ROLE_ANALYST], AliranKerja::peranan('1.3'));
        $this->assertSame([User::ROLE_ANALYST], AliranKerja::peranan('2'));
        $this->assertSame([User::ROLE_ANALYST], AliranKerja::peranan('3.1'));
    }

    /**
     * Inilah pemisahan yang paling mudah hilang: pemilik peringkat dan
     * pemasuk No. Rujukan bukan orang yang sama pada peringkat 1.1–1.3.
     */
    public function test_no_rujukan_borang_dimasukkan_oleh_ppr(): void
    {
        foreach (['1.1', '1.2', '1.3'] as $kunci) {
            $this->assertNotNull(AliranKerja::labelRujukan($kunci));
            $this->assertSame(User::ROLE_PENYELARAS_REKOD, AliranKerja::perananRujukan($kunci));
            $this->assertTrue(AliranKerja::rujukanOlehPerananLain($kunci));
        }

        $this->assertSame('No. Rujukan Borang Penerimaan Data', AliranKerja::labelRujukan('1.1'));
        $this->assertSame('No. Rujukan Borang Pendaftaran Data', AliranKerja::labelRujukan('1.2'));
        $this->assertSame('No. Rujukan Borang Semakan Awal Data', AliranKerja::labelRujukan('1.3'));
    }

    /**
     * No. Rujukan Laporan peringkat 3.1 BUKAN tanggungjawab PPR — jadual
     * rasmi menamakan PPR bagi tiga No. Rujukan Borang sahaja.
     */
    public function test_no_rujukan_laporan_bukan_milik_ppr(): void
    {
        $this->assertSame('No. Rujukan Laporan', AliranKerja::labelRujukan('3.1'));
        $this->assertNull(AliranKerja::perananRujukan('3.1'));
        $this->assertFalse(AliranKerja::rujukanOlehPerananLain('3.1'));
    }

    /**
     * Gate No. Rujukan mengikut pemiliknya, bukan mengikut pemilik peringkat.
     */
    public function test_gate_no_rujukan_mengikut_pemiliknya(): void
    {
        // 1.1–1.3: milik PPR, jadi gate-nya BUKAN gate peringkat.
        foreach (['1.1', '1.2', '1.3'] as $kunci) {
            $this->assertSame('record-stage-reference', AliranKerja::gateRujukan($kunci));
            $this->assertNotSame(AliranKerja::gate($kunci), AliranKerja::gateRujukan($kunci));
        }

        // 3.1: milik pegawai peringkat itu sendiri.
        $this->assertSame(AliranKerja::gate('3.1'), AliranKerja::gateRujukan('3.1'));
        $this->assertSame('advance-analysis-stage', AliranKerja::gateRujukan('3.1'));

        // Peringkat tanpa No. Rujukan tiada gate rujukan langsung.
        $this->assertNull(AliranKerja::gateRujukan('2'));
        $this->assertNull(AliranKerja::gateRujukan('4'));
    }

    public function test_medan_tangkapan_setiap_peringkat(): void
    {
        $this->assertSame([
            'tarikh_terima' => 'Tarikh Terima',
            'status_borang' => 'Status Borang Penerimaan Data',
        ], AliranKerja::medan('1.1'));

        $this->assertSame([
            'tarikh_terima' => 'Tarikh Terima',
            'status_borang' => 'Status Borang Pendaftaran Data',
        ], AliranKerja::medan('1.2'));

        $this->assertSame([
            'tarikh_semakan' => 'Tarikh Semakan',
            'status_borang' => 'Status Borang Semakan Awal Data',
        ], AliranKerja::medan('1.3'));

        $this->assertSame([
            'tarikh_mula' => 'Tarikh Mula',
            'tarikh_tamat' => 'Tarikh Tamat',
            'status_borang' => 'Status Mastertable',
            'nama_fail' => 'Nama Fail',
        ], AliranKerja::medan('2'));

        $this->assertSame([
            'tarikh_mula' => 'Tarikh Mula',
            'tarikh_tamat' => 'Tarikh Tamat',
            'status_borang' => 'Status Laporan Inventori Kriptografi',
        ], AliranKerja::medan('3.1'));
    }

    /**
     * Perbendaharaan Status Borang — satu senarai dikongsi oleh kelima-lima
     * peringkat yang menangkapnya.
     */
    public function test_perbendaharaan_status_borang(): void
    {
        $this->assertSame([
            'Belum Mula',
            'Dalam Proses',
            'Dalam Semakan',
            'Selesai',
            'Tidak Boleh Diteruskan',
            'Tidak Berkaitan',
            'Telah Diserah',
        ], AliranKerja::STATUS_BORANG);

        foreach (['1.1', '1.2', '1.3', '2', '3.1'] as $kunci) {
            $this->assertSame(AliranKerja::STATUS_BORANG, AliranKerja::statusBorang($kunci));
        }
    }

    /**
     * Peringkat yang tidak menangkap Status Borang tidak menawarkan senarainya.
     */
    public function test_peringkat_tanpa_status_borang_tiada_pilihan(): void
    {
        foreach (AliranKerja::akanDatang() as $kunci) {
            $this->assertSame([], AliranKerja::statusBorang($kunci));
        }

        $this->assertSame([], AliranKerja::statusBorang('9.9'));
    }

    /**
     * Status BORANG dan status PERINGKAT ialah dua perbendaharaan berasingan.
     * Menggabungkannya akan menghilangkan keadaan yang hanya wujud pada salah
     * satu daripadanya.
     */
    public function test_status_borang_berasingan_daripada_status_peringkat(): void
    {
        $this->assertNotSame(WorkflowStageStatus::STATUSES, AliranKerja::STATUS_BORANG);

        foreach (['Dalam Semakan', 'Tidak Boleh Diteruskan', 'Tidak Berkaitan', 'Telah Diserah'] as $hanyaBorang) {
            $this->assertContains($hanyaBorang, AliranKerja::STATUS_BORANG);
            $this->assertNotContains($hanyaBorang, WorkflowStageStatus::STATUSES);
        }
    }

    public function test_turutan_sebelum_dan_selepas(): void
    {
        $this->assertNull(AliranKerja::sebelum('1.1'));
        $this->assertSame('1.1', AliranKerja::sebelum('1.2'));
        $this->assertSame('1.3', AliranKerja::sebelum('2'));
        $this->assertSame('2', AliranKerja::sebelum('3.1'));

        $this->assertSame('1.2', AliranKerja::selepas('1.1'));
        $this->assertSame('3.2', AliranKerja::selepas('3.1'));
        $this->assertNull(AliranKerja::selepas('5'));
    }

    /**
     * Susunan mesti ikut turutan aliran, bukan abjad kunci — jika tidak
     * '1.10' akan mendahului '1.2' apabila sub-peringkat bertambah.
     */
    public function test_ordinal_mengikut_turutan_aliran(): void
    {
        $this->assertSame(1, AliranKerja::ordinal('1.1'));
        $this->assertSame(4, AliranKerja::ordinal('2'));
        $this->assertSame(5, AliranKerja::ordinal('3.1'));
        $this->assertNull(AliranKerja::ordinal('9.9'));
    }

    public function test_kunci_tidak_dikenali_tidak_menyebabkan_ralat(): void
    {
        $this->assertFalse(AliranKerja::wujud('9.9'));
        $this->assertFalse(AliranKerja::wujud(7));
        $this->assertNull(AliranKerja::def('9.9'));
        $this->assertSame('Peringkat Tidak Dikenali', AliranKerja::label('9.9'));
        $this->assertNull(AliranKerja::utamaBagi('9.9'));
        $this->assertSame([], AliranKerja::peranan('9.9'));
    }

    /**
     * Setiap peringkat lama mesti mempunyai rumah dalam struktur baharu —
     * itulah yang menjamin migrasi tidak menggugurkan kemajuan sedia ada.
     */
    public function test_setiap_peringkat_lama_dipetakan_kepada_kunci_sah(): void
    {
        $this->assertSame([1, 2, 3, 4, 5, 6, 7], array_keys(AliranKerja::PETAAN_LAMA));

        foreach (AliranKerja::PETAAN_LAMA as $lama => $kunci) {
            $this->assertNotEmpty($kunci, "Peringkat lama {$lama} tiada padanan.");

            foreach ($kunci as $satu) {
                $this->assertTrue(AliranKerja::wujud($satu), "Kunci {$satu} tidak wujud.");
            }
        }

        // Peringkat lama 1 menggabungkan dua proses — ia DIPECAHKAN.
        $this->assertSame(['1.1', '1.2'], AliranKerja::PETAAN_LAMA[1]);

        // Peringkat lama 6 dan 7 kini satu — ia DIGABUNGKAN.
        $this->assertSame(['5'], AliranKerja::PETAAN_LAMA[6]);
        $this->assertSame(['5'], AliranKerja::PETAAN_LAMA[7]);
    }
}
