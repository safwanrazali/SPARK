@extends('layouts.app')

@section('title', 'Kemajuan Analisis Entiti — ' . $entiti['agency_code'])

@section('page-title', 'Kemajuan Analisis Entiti')

@section('content')

    @php
        use App\Models\WorkflowStageStatus;
        use App\Services\KemajuanAnalisisService;
        use App\Support\AliranKerja;

        $pengguna = auth()->user();

        /*
        | "Berada dalam aliran kerja" bermakna peringkat 1.1 telah DIMULAKAN —
        | bukan Selesai.
        |
        | Peringkat 1.1 berderivasi: ia hanya Selesai setelah No. Rujukan
        | (milik PPR) direkod. Menuntut Selesai di sini akan menyembunyikan
        | keseluruhan halaman daripada PPA yang baru sahaja merekod Tarikh
        | Terima — sedangkan peringkat 1.2 mereka sudah pun terbuka.
        |
        | Baris peringkat sahaja tidak memadai: "Set Semula" mengekalkan baris
        | dan hanya mengosongkan datanya.
        */
        $dalamAliran = app(KemajuanAnalisisService::class)->telahMemasukiAliran($peringkat);

        $status = fn(string $kunci): string => $peringkat->get($kunci)?->status ?? WorkflowStageStatus::BELUM_MULA;
        $selesai = fn(string $kunci): bool => $status($kunci) === WorkflowStageStatus::SELESAI;

        /*
        | Satu peringkat "terbuka" apabila pendahulunya membenarkannya. DUA
        | peraturan, mencerminkan KemajuanAnalisisService::ralatPendahulu():
        |
        | - Pendahulu dengan `syarat_lanjut`: cukup medan tersebut ADA. Ia
        |   tidak semestinya Selesai — itulah yang membenarkan peringkat 1.2
        |   bermula sementara No. Rujukan peringkat 1.1 masih menunggu PPR.
        | - Pendahulu lain: mesti benar-benar Selesai.
        */
        $terbuka = function (string $kunci) use ($peringkat, $selesai, $penugasan): bool {
            $sebelum = AliranKerja::sebelum($kunci);

            if ($sebelum === null) {
                return true;
            }

            // Penugasan Pegawai Analisis boleh menjadi syarat lanjut — ia
            // BUKAN medan peringkat, jadi ia disemak berasingan.
            if (AliranKerja::perluPenugasanUntukLanjut($sebelum) && $penugasan === null) {
                return false;
            }

            $syarat = AliranKerja::syaratLanjut($sebelum);

            if ($syarat === []) {
                return $selesai($sebelum);
            }

            $rekod = $peringkat->get($sebelum);

            if ($rekod === null) {
                return false;
            }

            foreach ($syarat as $lajur) {
                if (blank($rekod->{$lajur})) {
                    return false;
                }
            }

            return true;
        };

        // Bolehkah pengguna ini melaksanakan peringkat berkenaan SEKARANG?
        // Tiga syarat: peranan, giliran, dan peringkat belum ditutup.
        $bolehKendali = function (string $kunci) use ($pengguna, $terbuka, $selesai): bool {
            $gate = AliranKerja::gate($kunci);

            return $gate !== null
                && $pengguna->can($gate)
                && $terbuka($kunci)
                && ! $selesai($kunci);
        };

        /*
        | Bolehkah pengguna ini memasukkan No. Rujukan peringkat berkenaan?
        |
        | Setiap No. Rujukan milik PPR, tanpa mengira siapa memiliki
        | peringkatnya — jadi gate diambil daripada takrifan aliran kerja dan
        | bukan daripada gate peringkat.
        |
        | Gilirannya TIDAK terikat kepada status peringkat: nombor rujukan
        | boleh direkodkan sepanjang peringkat itu berjalan.
        */
        $bolehRujukan = function (string $kunci) use ($pengguna, $peringkat): bool {
            $gate = \App\Support\AliranKerja::gateRujukan($kunci);

            if ($gate === null || ! $pengguna->can($gate)) {
                return false;
            }

            // Borang fizikal mesti direkod dahulu oleh pegawai peringkat itu:
            // PPR merekod nombor rujukan borang yang SUDAH wujud. Sebelum itu
            // entiti ini langsung tidak muncul kepadanya bagi peringkat ini.
            return app(KemajuanAnalisisService::class)
                ->rujukanTersedia($peringkat->get($kunci), $kunci);
        };

        /*
        | Penugasan Pegawai Analisis ialah kerja peringkat 1.2: PPA
        | mendaftarkan data DAN menetapkan pegawai yang menjalankan peringkat
        | seterusnya.
        |
        | Ia kekal tersedia walaupun peringkat 1.2 telah Selesai — pegawai
        | boleh bertukar selepas pendaftaran, dan menutupnya bersama peringkat
        | itu akan meninggalkan entiti tanpa cara menggantikan pegawainya.
        */
        $bolehTugaskan = fn(string $kunci): bool => $kunci === AliranKerja::PENDAFTARAN_DATA
            && $terbuka(AliranKerja::PENDAFTARAN_DATA)
            && $pengguna->can('manage-assignment');

        $jumlahPeringkat = app(KemajuanAnalisisService::class)->jumlahPeringkatSemasa();

        $badgeKeseluruhan = match ($keseluruhan) {
            KemajuanAnalisisService::KESELURUHAN_SIAP => 'status-rendah',
            KemajuanAnalisisService::KESELURUHAN_DALAM_PROSES => 'status-sederhana',
            default => 'status-tinggi',
        };

        /*
        | Peringkat fasa semasa yang mempunyai tindakan terbuka kepada
        | pengguna ini — sama ada tindakan peringkat atau No. Rujukan.
        |
        | No. Rujukan direkodkan PADA baris peringkat, jadi ia hanya berkenaan
        | setelah entiti berada dalam aliran kerja. Tindakan peringkat 1.1
        | pula BERKENAAN sebelum itu: ia yang memasukkan entiti ke dalam
        | aliran.
        */
        $peringkatBertindak = collect(AliranKerja::semasa())->filter(
            fn(string $kunci) => $bolehKendali($kunci)
                || ($dalamAliran && $bolehRujukan($kunci))
                || $bolehTugaskan($kunci),
        );

        $adaTindakan = $peringkatBertindak->isNotEmpty();

        $analisisLengkap = (bool) $analisis?->selesai;

        $borangUrl = route('analisis.borang', [
            'sector_code' => $entiti['sector_code'],
            'agency_code' => $entiti['agency_code'],
        ]);
    @endphp

    @include('workflow.partials.kepala-entiti')

    @include('workflow.partials.peringkat-kemajuan')

    @if (!$dalamAliran)
        @include('workflow.partials.belum-aliran')
    @else
        @include('workflow.partials.ringkasan-kemajuan')

        @include('workflow.partials.maklumat-peringkat')
    @endif


    {{--
        Kad "Sejarah Peringkat" telah dibuang: ia mengulang "Maklumat
        Peringkat" di atas.

        Setiap perubahan TERUS direkodkan dalam activity_log — tiada jejak
        audit yang hilang. Ia dibaca melalui modul Log Audit, yang memang
        wujud untuk soalan itu; halaman ini menjawab "di mana entiti ini
        sekarang", bukan "apa yang berlaku kepadanya".
    --}}

@endsection
