<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

/**
 * FASA 13 — pengesahan proses sandaran dan pemulihan.
 *
 * Prosedur pemulihan bencana hanya bernilai jika ia benar-benar pernah
 * dijalankan. Ujian ini menjalankan skrip sebenar terhadap fail pangkalan
 * data sementara: sandar → sahkan → pulihkan → sahkan.
 *
 * Ujian ini tidak menyentuh pangkalan data aplikasi.
 */
class Phase13BackupRestoreTest extends TestCase
{
    private string $direktori;

    protected function setUp(): void
    {
        parent::setUp();

        $this->direktori = sys_get_temp_dir().'/pqc-dr-'.bin2hex(random_bytes(6));

        mkdir($this->direktori, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->direktori.'/*') ?: [] as $fail) {
            @unlink($fail);
        }

        @rmdir($this->direktori);

        parent::tearDown();
    }

    private function asas(): string
    {
        return dirname(__DIR__, 2);
    }

    /**
     * @return array{0: int, 1: string}
     */
    private function jalankan(string $skrip, array $pilihan): array
    {
        $arahan = escapeshellarg(PHP_BINARY).' '.escapeshellarg($this->asas().'/scripts/'.$skrip);

        foreach ($pilihan as $kunci => $nilai) {
            $arahan .= ' '.escapeshellarg($nilai === true ? "--{$kunci}" : "--{$kunci}={$nilai}");
        }

        $output = [];
        $kod = 0;

        exec($arahan.' 2>&1', $output, $kod);

        return [$kod, implode(PHP_EOL, $output)];
    }

    private function pangkalanDataUjian(string $nama, int $bilanganRekod = 3): string
    {
        $fail = $this->direktori.'/'.$nama;

        $pdo = new \PDO('sqlite:'.$fail);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE entiti (id INTEGER PRIMARY KEY, agency_code TEXT)');

        for ($i = 1; $i <= $bilanganRekod; $i++) {
            $pdo->exec("INSERT INTO entiti (agency_code) VALUES ('A01010{$i}')");
        }

        return $fail;
    }

    private function bilanganRekod(string $fail): int
    {
        return (int) (new \PDO('sqlite:'.$fail))
            ->query('SELECT COUNT(*) FROM entiti')
            ->fetchColumn();
    }

    public function test_sandaran_menghasilkan_salinan_yang_sah(): void
    {
        $sumber = $this->pangkalanDataUjian('sumber.sqlite', 5);
        $sandaran = $this->direktori.'/sandaran.sqlite';

        [$kod, $output] = $this->jalankan('backup-database.php', [
            'database' => $sumber,
            'target' => $sandaran,
        ]);

        $this->assertSame(0, $kod, $output);
        $this->assertFileExists($sandaran);
        $this->assertSame(5, $this->bilanganRekod($sandaran));
        $this->assertStringContainsString('SANDARAN BERJAYA', $output);

        // Sumber tidak diubah oleh proses sandaran.
        $this->assertSame(5, $this->bilanganRekod($sumber));
    }

    public function test_sandaran_ke_direktori_menggunakan_nama_bercap_masa(): void
    {
        $sumber = $this->pangkalanDataUjian('sumber.sqlite');

        [$kod, $output] = $this->jalankan('backup-database.php', [
            'database' => $sumber,
            'target' => $this->direktori,
        ]);

        $this->assertSame(0, $kod, $output);

        $dijana = glob($this->direktori.'/backup-*.sqlite') ?: [];

        $this->assertCount(1, $dijana);
        $this->assertMatchesRegularExpression('/backup-\d{8}-\d{6}\.sqlite$/', $dijana[0]);
    }

    public function test_sandaran_tidak_menimpa_fail_sedia_ada(): void
    {
        $sumber = $this->pangkalanDataUjian('sumber.sqlite');
        $sandaran = $this->direktori.'/sandaran.sqlite';

        $this->jalankan('backup-database.php', ['database' => $sumber, 'target' => $sandaran]);

        [$kod, $output] = $this->jalankan('backup-database.php', [
            'database' => $sumber,
            'target' => $sandaran,
        ]);

        $this->assertSame(1, $kod);
        $this->assertStringContainsString('telah wujud', $output);
    }

    public function test_pemulihan_mengembalikan_data_dan_menyimpan_salinan_keselamatan(): void
    {
        $sumber = $this->pangkalanDataUjian('sumber.sqlite', 4);
        $sandaran = $this->direktori.'/sandaran.sqlite';

        $this->jalankan('backup-database.php', ['database' => $sumber, 'target' => $sandaran]);

        // Simulasi kehilangan data selepas sandaran dibuat.
        (new \PDO('sqlite:'.$sumber))->exec('DELETE FROM entiti');
        $this->assertSame(0, $this->bilanganRekod($sumber));

        [$kod, $output] = $this->jalankan('restore-database.php', [
            'from' => $sandaran,
            'database' => $sumber,
            'yes' => true,
        ]);

        $this->assertSame(0, $kod, $output);
        $this->assertSame(4, $this->bilanganRekod($sumber));
        $this->assertStringContainsString('PEMULIHAN BERJAYA', $output);

        // Keadaan sebelum pemulihan masih boleh diambil semula.
        $salinan = glob($this->direktori.'/pra-pemulihan-*.sqlite') ?: [];
        $this->assertCount(1, $salinan);
        $this->assertSame(0, $this->bilanganRekod($salinan[0]));
    }

    public function test_pemulihan_menolak_fail_sandaran_yang_rosak(): void
    {
        $sumber = $this->pangkalanDataUjian('sumber.sqlite', 2);
        $rosak = $this->direktori.'/rosak.sqlite';

        file_put_contents($rosak, 'ini bukan pangkalan data');

        [$kod, $output] = $this->jalankan('restore-database.php', [
            'from' => $rosak,
            'database' => $sumber,
            'yes' => true,
        ]);

        $this->assertSame(1, $kod);
        $this->assertStringContainsString('PEMULIHAN GAGAL', $output);

        // Pangkalan data destinasi tidak disentuh.
        $this->assertSame(2, $this->bilanganRekod($sumber));
    }

    public function test_pemulihan_memerlukan_fail_sandaran(): void
    {
        [$kod, $output] = $this->jalankan('restore-database.php', ['yes' => true]);

        $this->assertSame(1, $kod);
        $this->assertStringContainsString('--from', $output);
    }

    public function test_simpanan_berkala_mengekalkan_sandaran_terbaharu_sahaja(): void
    {
        $sumber = $this->pangkalanDataUjian('sumber.sqlite');

        foreach (['20260101-000000', '20260102-000000', '20260103-000000'] as $cap) {
            copy($sumber, $this->direktori.'/backup-'.$cap.'.sqlite');
        }

        [$kod] = $this->jalankan('backup-database.php', [
            'database' => $sumber,
            'target' => $this->direktori,
            'keep' => '2',
        ]);

        $this->assertSame(0, $kod);

        $tinggal = glob($this->direktori.'/backup-*.sqlite') ?: [];

        $this->assertCount(2, $tinggal);
        $this->assertFileDoesNotExist($this->direktori.'/backup-20260101-000000.sqlite');
    }
}
