{{--
    Catatan KB/PPA pada satu seksyen Borang Input.

    Maklum balas + pengakuan sahaja: KB/PPA menulis, PA menanda "Tindakan
    Diambil", KB/PPA melihat tandanya. Tiada kelulusan, tiada notifikasi,
    dan tiada kesan ke atas status peringkat 3.1.

    PENGLIHATAN: PA, KB dan PPA melihat KESEMUA catatan. Pemilikan hanya
    menentukan siapa boleh menyunting/memadam — TIDAK PERNAH siapa boleh
    melihat. Setiap butang di bawah mempunyai pasangan semakan di pelayan
    (LaporanCatatanPolicy); menyembunyikannya bukan kawalan keselamatan.

    d-print-none: widget ini tidak boleh muncul apabila skrin laporan
    dicetak. PDF rasmi dijana daripada laporan/pdf/body.blade.php, yang
    tidak memuatkan komponen ini langsung.
--}}
@php
    $sectionKey = $section ?? '';
    $pengguna = Auth::user();
    $bolehLihat = $pengguna?->can('viewAny', \App\Models\LaporanCatatan::class) ?? false;

    $senarai = collect($catatan[$sectionKey] ?? []);
    $jumlah = $senarai->count();
    $terbuka = $senarai->where('status', \App\Models\LaporanCatatan::STATUS_TERBUKA)->count();
    $ditindak = $jumlah - $terbuka;

    $bolehTulis = $bolehLihat && $pengguna->can('create', \App\Models\LaporanCatatan::class) && isset($analisis);
    $label = \App\Support\SeksyenAnalisis::label($sectionKey);
@endphp

@if ($bolehLihat)
    <div class="section-comments-widget d-print-none">
        <button class="btn btn-sm btn-outline-secondary section-comments-toggle" type="button" data-bs-toggle="collapse"
            data-bs-target="#comments-{{ $sectionKey }}"
            title="Catatan bagi {{ $label }} — {{ $jumlah }} catatan, {{ $terbuka }} terbuka">
            <i class="bi bi-chat-dots"></i>
            @if ($jumlah)
                <span class="badge {{ $terbuka ? 'bg-warning text-dark' : 'bg-success' }}">{{ $jumlah }}</span>
            @else
                <span class="text-muted small">+</span>
            @endif
        </button>

        <div class="collapse section-comments-collapse" id="comments-{{ $sectionKey }}">
            <div class="section-comments-panel mt-2 p-2 border rounded-2 bg-light">
                {{-- Kiraan bersifat maklumat semata-mata: ia TIDAK menyekat
                     peringkat 3.1 dan tidak mengubah statusnya. --}}
                <div class="small text-muted mb-2">
                    {{ $label }} — {{ $jumlah }} catatan
                    · {{ $terbuka }} terbuka
                    · {{ $ditindak }} tindakan diambil
                </div>

                @if ($bolehTulis)
                    <div class="mb-2 pb-2 border-bottom">
                        <form method="POST" action="{{ route('laporan.catatan.store', $analisis) }}"
                            class="d-flex gap-2 align-items-end section-comment-form">
                            @csrf
                            <input type="hidden" name="section" value="{{ $sectionKey }}">
                            <textarea name="content" class="form-control form-control-sm flex-grow-1" rows="1"
                                placeholder="Tambah catatan..."
                                maxlength="{{ \App\Models\LaporanCatatan::HAD_KANDUNGAN }}" required></textarea>
                            <button type="submit" class="btn btn-sm btn-primary" title="Hantar catatan">
                                <i class="bi bi-send"></i>
                            </button>
                        </form>
                        <small class="text-muted d-block mt-1">
                            Dilihat oleh PA, KB dan PPA. Tidak disertakan dalam PDF laporan.
                        </small>
                    </div>
                @endif

                @if ($jumlah)
                    <div class="section-comments-list">
                        @foreach ($senarai as $comment)
                            <div class="comment-item mb-2 pb-2 @if (!$loop->last) border-bottom @endif">
                                <div class="d-flex justify-content-between align-items-start gap-2">
                                    <div class="flex-grow-1">
                                        <strong class="d-block small">{{ $comment->user?->name ?? 'Pengguna dipadam' }}</strong>
                                        <small class="text-muted d-block">
                                            {{ implode(', ', $comment->user?->assignedRoleShortLabels() ?? []) }}
                                            · {{ $comment->created_at->format('d/m/Y H:i') }}
                                        </small>
                                    </div>

                                    <div class="d-flex align-items-center gap-1">
                                        {{-- Pengarang sahaja: sunting + padam. --}}
                                        @can('update', $comment)
                                            <button type="button" class="btn btn-sm btn-link text-secondary p-0 m-0"
                                                data-bs-toggle="collapse"
                                                data-bs-target="#catatan-sunting-{{ $comment->id }}" title="Sunting">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                        @endcan

                                        @can('delete', $comment)
                                            <form method="POST"
                                                action="{{ route('laporan.catatan.destroy', $comment) }}"
                                                class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-link text-danger p-0 m-0"
                                                    title="Padam" onclick="return confirm('Padam catatan ini?')">
                                                    <i class="bi bi-x-circle"></i>
                                                </button>
                                            </form>
                                        @endcan
                                    </div>
                                </div>

                                <p class="mb-0 small mt-1">{{ $comment->content }}</p>

                                @can('update', $comment)
                                    <div class="collapse mt-2" id="catatan-sunting-{{ $comment->id }}">
                                        <form method="POST"
                                            action="{{ route('laporan.catatan.update', $comment) }}"
                                            class="d-flex gap-2 align-items-end">
                                            @csrf
                                            @method('PATCH')
                                            <textarea name="content" class="form-control form-control-sm flex-grow-1" rows="2"
                                                maxlength="{{ \App\Models\LaporanCatatan::HAD_KANDUNGAN }}"
                                                required>{{ $comment->content }}</textarea>
                                            <button type="submit" class="btn btn-sm btn-outline-primary"
                                                title="Simpan suntingan">
                                                <i class="bi bi-check2"></i>
                                            </button>
                                        </form>
                                    </div>
                                @endcan

                                {{-- Status catatan: Terbuka / Tindakan Diambil.
                                     SENGAJA berbeza daripada "Belum Selesai /
                                     Selesai" peringkat 3.1 — kedua-duanya bebas. --}}
                                <div class="d-flex align-items-center flex-wrap gap-2 mt-2">
                                    @if ($comment->sudahDitindak())
                                        <span class="badge bg-success">
                                            <i class="bi bi-check2"></i> Tindakan Diambil
                                        </span>
                                        <small class="text-muted">
                                            Tindakan oleh: {{ $comment->tindakanOleh?->name ?? '—' }}
                                            · {{ $comment->tindakan_pada?->format('d/m/Y h:i A') }}
                                        </small>
                                    @else
                                        <span class="badge bg-warning text-dark">Terbuka</span>
                                    @endif

                                    {{-- Pegawai Analisis SAHAJA. --}}
                                    @can('tandakanTindakan', $comment)
                                        <form method="POST"
                                            action="{{ route('laporan.catatan.tindakan', $comment) }}"
                                            class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-success py-0"
                                                title="Tanda bahawa tindakan telah diambil">
                                                <i class="bi bi-check2"></i> Tindakan Diambil
                                            </button>
                                        </form>
                                    @endcan

                                    @can('batalkanTindakan', $comment)
                                        <form method="POST"
                                            action="{{ route('laporan.catatan.tindakan.batal', $comment) }}"
                                            class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-link text-secondary p-0 m-0"
                                                title="Batalkan tanda ini jika tersilap tanda">
                                                Batal tanda
                                            </button>
                                        </form>
                                    @endcan
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-muted small mb-0">Tiada catatan lagi.</p>
                @endif
            </div>
        </div>
    </div>
@endif
