@extends('layouts.app')

@section('title', 'Kemajuan Analisis Entiti — ' . $entiti['agency_code'])

@section('page-title', 'Kemajuan Analisis Entiti')

@section('content')

    @php
        use App\Services\KemajuanAnalisisCapaian;
        use App\Services\KemajuanAnalisisService;
        use App\Support\AliranKerja;

        $pengguna = auth()->user();

        /*
        | Soalan "bolehkah pengguna ini melihat / menyunting peringkat ini"
        | dijawab oleh SATU tempat, dan tempat itu bukan paparan ini.
        |
        | KemajuanAnalisisCapaian menggabungkan gate peranan AliranKerja
        | dengan syarat pendahulu yang sama seperti yang dikuatkuasakan oleh
        | KemajuanAnalisisController dan KemajuanAnalisisGating — jadi butang
        | yang dipaparkan di sini tidak boleh terpesong daripada apa yang
        | benar-benar dibenarkan oleh pelayan.
        */
        $capaian = app(KemajuanAnalisisCapaian::class);

        /*
        | "Berada dalam aliran kerja" bermakna peringkat 1.1 telah DIMULAKAN —
        | bukan Selesai.
        |
        | Peringkat 1.1 berderivasi: ia hanya Selesai setelah No. Rujukan
        | (milik PKD) direkod. Menuntut Selesai di sini akan menyembunyikan
        | keseluruhan halaman daripada PPA yang baru sahaja merekod Tarikh
        | Terima — sedangkan peringkat 1.2 mereka sudah pun terbuka.
        |
        | Baris peringkat sahaja tidak memadai: "Set Semula" mengekalkan baris
        | dan hanya mengosongkan datanya.
        */
        $dalamAliran = app(KemajuanAnalisisService::class)->telahMemasukiAliran($peringkat);

        /*
        | Satu peringkat "terbuka" apabila pendahulunya membenarkannya —
        | peraturannya milik KemajuanAnalisisCapaian::terbuka(), yang
        | mencerminkan KemajuanAnalisisGating::ralatPendahulu().
        */
        $terbuka = fn(string $kunci): bool => $capaian->terbuka($peringkat, $penugasan, $kunci);

        /*
        | Bolehkah pengguna ini melaksanakan peringkat berkenaan? Dua syarat:
        | peranan dan giliran.
        |
        | Peringkat yang telah SELESAI tidak lagi dikecualikan: pemiliknya
        | boleh kembali membetulkan maklumat yang tersalah rekod, dan itulah
        | semakan yang sama yang dikuatkuasakan oleh KemajuanAnalisisController
        | — jadi menyembunyikan borangnya hanya menyembunyikan tindakan yang
        | memang dibenarkan oleh pelayan.
        |
        | Peranan yang BUKAN pemilik peringkat tidak mendapat borang, sama
        | seperti sebelum ini.
        */
        $bolehKendali = fn(string $kunci): bool => $capaian->bolehSunting(
            $pengguna,
            $peringkat,
            $penugasan,
            $kunci,
        );

        /*
        | Bolehkah pengguna ini memasukkan No. Rujukan peringkat berkenaan?
        |
        | Setiap No. Rujukan milik PKD, tanpa mengira siapa memiliki
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
            // PKD merekod nombor rujukan borang yang SUDAH wujud. Sebelum itu
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
