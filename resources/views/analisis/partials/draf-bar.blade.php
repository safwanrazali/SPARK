    {{-- FASA 6 — keadaan draf: sambung semula, versi dan masa simpanan terakhir. --}}
    <div class="report-card mb-4 draft-bar" id="draft-bar">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">

            <div>
                <h4 class="section-title mb-1">Draf Laporan</h4>
                <p class="text-secondary mb-0" id="draft-status">
                    @if ($draf['ada_draf'])
                        Draf versi {{ $draf['versi'] }} disambung semula — disimpan
                        {{ $draf['disimpan_pada']?->format('d/m/Y H:i') }}
                        @if ($draf['disimpan_oleh'])
                            oleh {{ $draf['disimpan_oleh'] }}
                        @endif
                    @elseif ($draf['ada_rekod'])
                        {{-- Tiada draf terbuka kerana dapatan telah dimuktamadkan;
                             borang dimuatkan daripada rekod tersimpan. --}}
                        Dapatan tersimpan dimuatkan — dikemas kini
                        {{ $draf['dikemas_kini_pada']?->format('d/m/Y H:i') }}.
                        Sebarang perubahan boleh disimpan sebagai draf sebelum dimuktamadkan.
                    @else
                        Belum ada draf disimpan. Kerja anda boleh disimpan pada bila-bila masa
                        dan disambung semula kemudian.
                    @endif
                </p>
            </div>

            <div class="draft-bar__meta">
                @php
                    $semuaSeksyenDiisi = $draf['seksyen_selesai'] === $draf['jumlah_seksyen'];
                    $badgeSeksyen = match (true) {
                        $semuaSeksyenDiisi => 'status-rendah',
                        $draf['seksyen_selesai'] > 0 => 'status-sederhana',
                        default => 'status-tinggi',
                    };
                @endphp
                <span class="status-badge {{ $badgeSeksyen }}">
                    {{ $draf['seksyen_selesai'] }} / {{ $draf['jumlah_seksyen'] }} seksyen diisi
                </span>
                <button type="submit" form="borang-analisis" formaction="{{ route('analisis.draf') }}"
                    class="btn btn-sm btn-outline-light" id="btn-simpan-draf">
                    <i class="bi bi-journal-arrow-down"></i> Simpan Draf
                </button>
            </div>

        </div>

        <div class="draft-sections mt-3">
            @foreach ($draf['seksyen'] as $kunci => $seksyen)
                <span class="draft-chip {{ $seksyen['selesai'] ? 'is-selesai' : ($seksyen['ada_draf'] ? 'is-draf' : '') }}"
                    title="{{ $seksyen['disimpan_pada'] ? 'Draf v' . $seksyen['versi'] . ' — ' . $seksyen['disimpan_pada']->format('d/m/Y H:i') : 'Belum disimpan' }}">
                    @if ($seksyen['selesai'])
                        <i class="bi bi-check-circle-fill"></i>
                    @elseif ($seksyen['ada_draf'])
                        <i class="bi bi-pencil"></i>
                    @else
                        <i class="bi bi-circle"></i>
                    @endif
                    {{ $seksyen['label'] }}
                </span>
            @endforeach
        </div>

    </div>
