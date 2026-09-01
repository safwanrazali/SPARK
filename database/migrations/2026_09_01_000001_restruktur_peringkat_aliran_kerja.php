<?php

use App\Support\AliranKerja;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Restruktur aliran kerja: TUJUH peringkat rata → LIMA peringkat utama
 * dengan sub-peringkat.
 *
 * Ini BUKAN penomboran semula. Dua perubahan struktur sebenar berlaku:
 *
 * - Peringkat lama 01 ("Penerimaan & Pendaftaran Data") menggabungkan dua
 *   proses berasingan. Ia DIPECAHKAN kepada 1.1 dan 1.2; kedua-dua baris
 *   baharu mewarisi status dan tarikh baris lama, kerana kerja yang telah
 *   direkodkan sebagai selesai memang meliputi kedua-duanya.
 * - Peringkat lama 06 dan 07 ("Semakan & Kelulusan", "Penyerahan &
 *   Penutupan") kini satu peringkat. Ia DIGABUNGKAN kepada 5, mengambil
 *   status yang paling jauh antara kedua-duanya supaya kemajuan sedia ada
 *   tidak mundur.
 *
 * Lajur `stage` bertukar daripada integer kepada string kerana sub-peringkat
 * ialah sebahagian daripada identiti peringkat ('1.1', bukan 1). Jadual
 * dibina semula dan barisnya disalin, bukan diubah pada tempat — itulah cara
 * paling selamat merentas SQLite dan MySQL apabila lajur berindeks bertukar
 * jenis DAN bilangan baris turut berubah.
 *
 * Data sedia ada TIDAK dibuang: setiap baris lama menghasilkan sekurang-
 * kurangnya satu baris baharu, dan jejak audit (activity_log) tidak disentuh
 * langsung.
 */
return new class extends Migration
{
    /**
     * Susunan status, daripada paling awal kepada paling jauh. Digunakan
     * semasa menggabungkan peringkat 06 dan 07.
     *
     * @var array<string, int>
     */
    private const KEDALAMAN_STATUS = [
        'Belum Mula' => 0,
        'Dalam Proses' => 1,
        'Selesai' => 2,
    ];

    public function up(): void
    {
        $lama = DB::table('workflow_stage_status')->orderBy('id')->get();

        Schema::rename('workflow_stage_status', 'workflow_stage_status_lama');

        // SQLite mengekalkan NAMA indeks apabila jadual dinamakan semula, dan
        // nama indeks bersifat global di dalamnya — jadi indeks jadual lama
        // akan berlanggar dengan indeks jadual baharu yang sama namanya.
        // Menggugurkannya dahulu menyelesaikannya, dan tidak memudaratkan
        // pemacu lain kerana jadual lama digugurkan sebentar lagi.
        $this->gugurkanIndeks('workflow_stage_status_lama');

        $this->binaJadual();

        $this->salinBaris($lama);

        Schema::drop('workflow_stage_status_lama');

        $this->selaraskanKedudukanEntiti();
    }

    public function down(): void
    {
        $baharu = DB::table('workflow_stage_status')->orderBy('id')->get();

        Schema::rename('workflow_stage_status', 'workflow_stage_status_baharu');

        $this->gugurkanIndeks('workflow_stage_status_baharu');

        Schema::create('workflow_stage_status', function (Blueprint $table) {
            $table->id();
            $table->string('agency_code');
            $table->string('agency_name');
            $table->string('sector_code');
            $table->string('sector_name');
            $table->unsignedTinyInteger('stage');
            $table->string('status')->default('Belum Mula');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['agency_code', 'stage']);
            $table->index(['sector_code', 'stage']);
            $table->index(['stage', 'status']);
        });

        // Kunci baharu → nombor lama. 1.2 dan 3.2 tiada padanan lama:
        // 1.2 bergabung semula ke dalam 01, dan 3.2 tidak pernah wujud.
        $kepadaLama = [
            AliranKerja::PENERIMAAN_DATA => 1,
            AliranKerja::SEMAKAN_AWAL_DATA => 2,
            AliranKerja::PENYEDIAAN_DATA => 3,
            AliranKerja::ANALISIS_INVENTORI => 4,
            AliranKerja::PENJANAAN_LAPORAN => 5,
            AliranKerja::SEMAKAN_KELULUSAN => 6,
        ];

        foreach ($baharu as $baris) {
            $stage = $kepadaLama[$baris->stage] ?? null;

            if ($stage === null) {
                continue;
            }

            DB::table('workflow_stage_status')->insert([
                'agency_code' => $baris->agency_code,
                'agency_name' => $baris->agency_name,
                'sector_code' => $baris->sector_code,
                'sector_name' => $baris->sector_name,
                'stage' => $stage,
                'status' => $baris->status,
                'started_at' => $baris->started_at,
                'completed_at' => $baris->completed_at,
                'updated_by_user_id' => $baris->updated_by_user_id,
                'notes' => $baris->notes,
                'created_at' => $baris->created_at,
                'updated_at' => $baris->updated_at,
            ]);
        }

        Schema::drop('workflow_stage_status_baharu');

        Schema::table('workflow_status', function (Blueprint $table) {
            $table->dropColumn('current_stage_key');
        });
    }

    /**
     * Gugurkan indeks bernama pada jadual yang akan dibuang.
     *
     * Setiap gugurannya dilindungi try/catch: pemacu berbeza menamakan dan
     * menyimpan indeks secara berbeza, dan kegagalan menggugurkan indeks pada
     * jadual yang memang akan dibuang tidak boleh menghentikan migrasi.
     */
    private function gugurkanIndeks(string $jadual): void
    {
        $indeks = [
            'workflow_stage_status_agency_code_stage_unique',
            'workflow_stage_status_sector_code_stage_index',
            'workflow_stage_status_stage_status_index',
        ];

        foreach ($indeks as $nama) {
            try {
                Schema::table($jadual, function (Blueprint $table) use ($nama) {
                    $table->dropIndex($nama);
                });
            } catch (Throwable) {
                // Indeks tiada pada pemacu ini — tiada apa untuk digugurkan.
            }
        }
    }

    private function binaJadual(): void
    {
        Schema::create('workflow_stage_status', function (Blueprint $table) {
            $table->id();

            $table->string('agency_code');
            $table->string('agency_name');
            $table->string('sector_code');
            $table->string('sector_name');

            // Kunci peringkat: '1.1', '1.2', '1.3', '2', '3.1', '3.2', '4', '5'
            // (lihat App\Support\AliranKerja).
            $table->string('stage', 10);

            // Belum Mula | Dalam Proses | Selesai
            $table->string('status')->default('Belum Mula');

            /*
             * Data tangkapan setiap peringkat.
             *
             * Satu set lajur dikongsi oleh semua peringkat, dan AliranKerja
             * menentukan lajur mana berkenaan bagi peringkat mana — jadi
             * peringkat baharu (3.2, 4, 5) boleh ditambah tanpa migrasi
             * skema lagi selagi ia menangkap medan yang sama bentuknya.
             *
             * `status_borang` disimpan sebagai string dan bukan enum pangkalan
             * data: perbendaharaannya (AliranKerja::STATUS_BORANG) dikuatkuasakan
             * pada lapisan pengesahan, supaya menambah satu nilai baharu tidak
             * memerlukan migrasi skema.
             */
            $table->date('tarikh_terima')->nullable();
            $table->date('tarikh_semakan')->nullable();
            $table->date('tarikh_mula')->nullable();
            $table->date('tarikh_tamat')->nullable();
            $table->string('status_borang')->nullable();
            $table->string('nama_fail')->nullable();

            // No. Rujukan dan SIAPA memasukkannya. Kedua-duanya diasingkan
            // daripada `updated_by_user_id` kerana pada peringkat 1.1–1.3
            // nombor rujukan dimasukkan oleh PPR, bukan oleh pegawai yang
            // melaksanakan peringkat itu.
            $table->string('no_rujukan')->nullable();
            $table->foreignId('no_rujukan_oleh_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('no_rujukan_pada')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(['agency_code', 'stage']);
            $table->index(['sector_code', 'stage']);
            $table->index(['stage', 'status']);
        });
    }

    /**
     * Salin baris lama ke dalam struktur baharu, memecahkan peringkat 01 dan
     * menggabungkan peringkat 06 dengan 07.
     *
     * @param  \Illuminate\Support\Collection<int, object>  $lama
     */
    private function salinBaris($lama): void
    {
        $mengikutEntiti = $lama->groupBy('agency_code');

        foreach ($mengikutEntiti as $agencyCode => $baris) {
            $baharu = [];

            foreach ($baris as $rekod) {
                foreach (AliranKerja::PETAAN_LAMA[(int) $rekod->stage] ?? [] as $kunci) {
                    $baharu[$kunci] = isset($baharu[$kunci])
                        ? $this->gabung($baharu[$kunci], $rekod)
                        : $this->kepadaBaris($rekod, $kunci);
                }
            }

            // Peringkat 3.2 tiada padanan lama — ia peringkat baharu. Setiap
            // entiti yang berada dalam aliran kerja mendapat barisnya supaya
            // ketiadaan baris terus bermakna "belum didaftarkan".
            $contoh = $baris->first();

            foreach (AliranKerja::kekunci() as $kunci) {
                $baharu[$kunci] ??= [
                    'agency_code' => $agencyCode,
                    'agency_name' => $contoh->agency_name,
                    'sector_code' => $contoh->sector_code,
                    'sector_name' => $contoh->sector_name,
                    'stage' => $kunci,
                    'status' => 'Belum Mula',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            // insert() berkelompok menuntut SETIAP baris mempunyai set lajur
            // yang sama; baris peringkat baharu tidak membawa lajur tarikh.
            $baharu = array_map($this->lengkapkanLajur(...), $baharu);

            // Disusun mengikut turutan aliran, bukan mengikut abjad kunci.
            uksort($baharu, fn (string $a, string $b): int => AliranKerja::ordinal($a) <=> AliranKerja::ordinal($b));

            DB::table('workflow_stage_status')->insert(array_values($baharu));
        }
    }

    /**
     * Isi setiap lajur yang tidak dibekalkan dengan null, supaya baris yang
     * dibina daripada sumber berbeza kekal seragam.
     *
     * @param  array<string, mixed>  $baris
     * @return array<string, mixed>
     */
    private function lengkapkanLajur(array $baris): array
    {
        $lajur = [
            'agency_code' => null,
            'agency_name' => null,
            'sector_code' => null,
            'sector_name' => null,
            'stage' => null,
            'status' => 'Belum Mula',
            'tarikh_terima' => null,
            'tarikh_semakan' => null,
            'tarikh_mula' => null,
            'tarikh_tamat' => null,
            'status_borang' => null,
            'nama_fail' => null,
            'no_rujukan' => null,
            'no_rujukan_oleh_user_id' => null,
            'no_rujukan_pada' => null,
            'started_at' => null,
            'completed_at' => null,
            'updated_by_user_id' => null,
            'notes' => null,
            'created_at' => null,
            'updated_at' => null,
        ];

        return array_merge($lajur, array_intersect_key($baris, $lajur));
    }

    /**
     * @return array<string, mixed>
     */
    private function kepadaBaris(object $rekod, string $kunci): array
    {
        return [
            'agency_code' => $rekod->agency_code,
            'agency_name' => $rekod->agency_name,
            'sector_code' => $rekod->sector_code,
            'sector_name' => $rekod->sector_name,
            'stage' => $kunci,
            'status' => $rekod->status,
            'started_at' => $rekod->started_at,
            'completed_at' => $rekod->completed_at,
            'updated_by_user_id' => $rekod->updated_by_user_id,
            'notes' => $rekod->notes,
            'created_at' => $rekod->created_at,
            'updated_at' => $rekod->updated_at,
        ];
    }

    /**
     * Gabungkan dua peringkat lama yang kini satu (06 dan 07).
     *
     * Status yang paling jauh menang, supaya penggabungan tidak boleh
     * mengundurkan kemajuan yang telah direkodkan.
     *
     * @param  array<string, mixed>  $sedia
     * @return array<string, mixed>
     */
    private function gabung(array $sedia, object $rekod): array
    {
        $kedalamanSedia = self::KEDALAMAN_STATUS[$sedia['status']] ?? 0;
        $kedalamanBaharu = self::KEDALAMAN_STATUS[$rekod->status] ?? 0;

        if ($kedalamanBaharu > $kedalamanSedia) {
            $sedia['status'] = $rekod->status;
            $sedia['updated_by_user_id'] = $rekod->updated_by_user_id;
            $sedia['notes'] = $rekod->notes ?? $sedia['notes'];
        }

        $sedia['started_at'] = $this->paling($sedia['started_at'], $rekod->started_at, awal: true);
        $sedia['completed_at'] = $this->paling($sedia['completed_at'], $rekod->completed_at, awal: false);
        $sedia['updated_at'] = $this->paling($sedia['updated_at'], $rekod->updated_at, awal: false);

        return $sedia;
    }

    private function paling(mixed $a, mixed $b, bool $awal): mixed
    {
        if ($a === null || $b === null) {
            return $a ?? $b;
        }

        return $awal
            ? min((string) $a, (string) $b)
            : max((string) $a, (string) $b);
    }

    /**
     * `workflow_status` memegang kedudukan SEMASA entiti. Dengan struktur
     * bersarang, kedudukan itu ada dua bahagian: peringkat utama (1–5) dan
     * kunci sub-peringkat ('1.2'). Lajur `current_stage` dikekalkan sebagai
     * NOMBOR UTAMA — jenisnya tidak berubah, hanya julatnya (kini 1–5) —
     * dan kunci penuh disimpan dalam lajur baharu di sebelahnya.
     *
     * Kedudukan DIKIRA SEMULA daripada baris peringkat yang baru disalin,
     * bukan dipetakan daripada nombor peringkat lama. Pemetaan langsung akan
     * meletakkan entiti yang telah menyelesaikan aliran lama pada peringkat 5
     * — peringkat yang belum dibina — sedangkan kedudukan sebenarnya ialah
     * hujung fasa semasa. Peraturan di sini sama dengan
     * KemajuanAnalisisService::peringkatSemasa().
     */
    private function selaraskanKedudukanEntiti(): void
    {
        Schema::table('workflow_status', function (Blueprint $table) {
            $table->string('current_stage_key', 10)
                ->default(AliranKerja::PERTAMA)
                ->after('current_stage');
        });

        $peringkat = DB::table('workflow_stage_status')
            ->get(['agency_code', 'stage', 'status'])
            ->groupBy('agency_code');

        foreach (DB::table('workflow_status')->get(['id', 'agency_code']) as $entiti) {
            $milik = $peringkat->get($entiti->agency_code);

            $kunci = $milik === null
                ? AliranKerja::PERTAMA
                : $this->kedudukanSemasa($milik);

            $utama = AliranKerja::utamaBagi($kunci) ?? AliranKerja::UTAMA_PERTAMA;

            DB::table('workflow_status')
                ->where('id', $entiti->id)
                ->update([
                    'current_stage' => $utama,
                    'current_stage_key' => $kunci,
                    'stage_name' => AliranKerja::labelUtama($utama),
                ]);
        }
    }

    /**
     * Peringkat fasa semasa pertama yang belum Selesai, atau peringkat
     * terakhir fasa ini jika kesemuanya telah Selesai.
     *
     * @param  \Illuminate\Support\Collection<int, object>  $peringkat
     */
    private function kedudukanSemasa($peringkat): string
    {
        $status = $peringkat->pluck('status', 'stage');

        foreach (AliranKerja::semasa() as $kunci) {
            if (($status[$kunci] ?? null) !== 'Selesai') {
                return $kunci;
            }
        }

        return AliranKerja::TERAKHIR_SEMASA;
    }
};
