@extends('layouts.app')

@section('title', 'Penetapan Entiti')

@section('page-title', 'Penetapan Entiti')

@section('content')

    <div class="report-card mb-4">

        <h4 class="section-title">Pilih Sektor</h4>
        <p class="text-secondary">
            @if ($bolehDaftar && $bolehTugas)
                Pilih sektor untuk memaparkan semua entiti di bawahnya. Tandakan entiti yang
                telah menerima dan mendaftarkan data, kemudian tugaskan entiti yang telah
                dikunci kepada Pegawai Analisis.
            @elseif ($bolehDaftar)
                Pilih sektor untuk memaparkan semua entiti di bawahnya, kemudian tandakan
                entiti yang telah menyelesaikan Penerimaan &amp; Pendaftaran Data.
            @else
                Entiti muncul di sini setelah Penerimaan &amp; Pendaftaran Data selesai.
                Setiap entiti hanya boleh mempunyai satu penugasan aktif pada satu masa.
            @endif
        </p>

        <form action="{{ route('penugasan.index') }}" method="GET" class="row g-2 align-items-end">
            <div class="col-md-6">
                <label class="form-label" for="sector_code">Sektor</label>
                <select id="sector_code" name="sector_code" class="form-select">
                    <option value="">-- Entiti yang telah didaftarkan sahaja --</option>
                    @foreach (config('sektor') as $kod => $sektor)
                        <option value="{{ $kod }}" @selected($sectorCode === $kod)>{{ $kod }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-funnel"></i> Papar Entiti
                </button>
                @if ($sectorCode)
                    <a href="{{ route('penugasan.index') }}" class="btn btn-outline-light">Set Semula Penapis</a>
                @endif
            </div>
        </form>

    </div>

    {{--
        Panel 1 — Penerimaan & Pendaftaran Data (peringkat 01).
        Milik Pegawai Penyelaras Rekod; Ketua Bahagian membuka semula entiti
        yang telah dikunci melalui "Set Semula".
    --}}
    @if ($bolehDaftar)
        <div class="report-card mb-4">

            <h4 class="section-title">Penerimaan &amp; Pendaftaran Data</h4>
            <p class="text-secondary">
                {{ $jumlahDidaftar }} entiti telah dikunci dan tersedia kepada Pegawai Penyelaras Analisis.
                Entiti yang telah dikunci tidak boleh diubah lagi di sini.
            </p>

            @php
                // Ketua Bahagian melihat panel ini untuk "Set Semula" sahaja;
                // hanya PPR (dan Pentadbir) boleh menanda entiti.
                $bolehTanda = auth()->user()->can('register-entity-data');

                // Tanpa penapis sektor, senarai ini hanya mengandungi entiti
                // yang telah dikunci — tiada apa yang boleh dikemas kini, jadi
                // borangnya disembunyikan sepenuhnya. Perkara sama berlaku pada
                // mana-mana halaman sektor yang kebetulan terkunci semuanya.
                $adaUntukDitanda = $bolehTanda && $pendaftaran->contains(
                    fn (array $e) => ! ($e['pendaftaran']?->isSelesai() ?? false)
                );

                $badgeKeseluruhan = fn (string $keseluruhan): string => match ($keseluruhan) {
                    \App\Services\KemajuanAnalisisService::KESELURUHAN_SIAP => 'status-rendah',
                    \App\Services\KemajuanAnalisisService::KESELURUHAN_DALAM_PROSES => 'status-sederhana',
                    default => 'status-tinggi',
                };
            @endphp

            <form action="{{ route('penugasan.pendaftaran.kemas-kini') }}" method="POST">
                @csrf

                @if ($adaUntukDitanda)
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check2-square"></i> Kemas Kini
                        </button>
                        <span class="text-secondary">
                            Entiti yang ditanda akan bertukar kepada Selesai dan dikunci.
                        </span>
                    </div>
                @endif

                <div class="table-responsive-custom">
                    <table class="table-modern">
                        <thead>
                            <tr>
                                <th scope="col" class="text-nowrap">Selesai</th>
                                <th scope="col">Entiti</th>
                                <th scope="col">Penerimaan &amp; Pendaftaran Data</th>
                                <th scope="col">Kemajuan Keseluruhan</th>
                                <th scope="col">Tindakan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($pendaftaran as $e)
                                @php
                                    $daftar = $e['pendaftaran'];
                                    $dikunci = $daftar?->isSelesai() ?? false;
                                @endphp
                                <tr>
                                    <td>
                                        @if ($dikunci)
                                            <i class="bi bi-lock-fill text-secondary"
                                                title="Dikunci — Penerimaan &amp; Pendaftaran Data telah selesai"
                                                aria-label="{{ $e['agency_code'] }} telah dikunci"></i>
                                        @else
                                            <input type="checkbox" class="form-check-input"
                                                name="agency_codes[]" value="{{ $e['agency_code'] }}"
                                                id="daftar-{{ $e['agency_code'] }}" @disabled(! $bolehTanda)
                                                aria-label="Tandakan {{ $e['agency_code'] }} selesai">
                                        @endif
                                    </td>
                                    <td>
                                        {{-- Entiti terkunci tiada kotak semak, jadi tiada label untuk diikat. --}}
                                        @if ($dikunci)
                                            <strong>{{ $e['agency_code'] }}</strong><br>
                                            <span class="text-secondary text-nowrap">Sektor {{ $e['sector_code'] }}</span>
                                        @else
                                            <label class="mb-0" for="daftar-{{ $e['agency_code'] }}">
                                                <strong>{{ $e['agency_code'] }}</strong><br>
                                                <span class="text-secondary text-nowrap">Sektor {{ $e['sector_code'] }}</span>
                                            </label>
                                        @endif
                                    </td>
                                    <td>
                                        <span
                                            class="status-badge {{ $daftar?->statusBadgeClass() ?? 'status-tinggi' }}">
                                            {{ $daftar?->status ?? \App\Models\WorkflowStageStatus::BELUM_MULA }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge {{ $badgeKeseluruhan($e['keseluruhan']) }}">
                                            {{ $e['keseluruhan'] }}
                                        </span>
                                    </td>
                                    <td class="text-nowrap">
                                        @if ($dikunci)
                                            @can('reset-entity-registration')
                                                <button type="button" class="btn btn-sm btn-outline-light"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#setSemula-{{ $e['agency_code'] }}">
                                                    <i class="bi bi-arrow-counterclockwise"></i> Set Semula
                                                </button>
                                            @else
                                                <span class="text-secondary">-</span>
                                            @endcan
                                        @else
                                            <span class="text-secondary">-</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <x-empty-state colspan="5" icon="bi-inbox" title="Tiada entiti dipaparkan">
                                    Pilih sektor di atas untuk memaparkan entiti dan menandakan
                                    Penerimaan &amp; Pendaftaran Data.
                                </x-empty-state>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($adaUntukDitanda)
                    <div class="d-flex flex-wrap align-items-center gap-2 mt-3">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check2-square"></i> Kemas Kini
                        </button>
                        <span class="text-secondary">
                            Entiti yang ditanda akan bertukar kepada Selesai dan dikunci.
                        </span>
                    </div>
                @endif

            </form>

            <div class="mt-3">{{ $pendaftaran->links() }}</div>

        </div>

        {{-- Borang "Set Semula" diasingkan daripada borang Kemas Kini di atas
             kerana borang HTML tidak boleh bersarang. --}}
        @can('reset-entity-registration')
            @foreach ($pendaftaran as $e)
                @continue(! ($e['pendaftaran']?->isSelesai() ?? false))
                <div class="modal fade" id="setSemula-{{ $e['agency_code'] }}" tabindex="-1"
                    aria-labelledby="setSemulaLabel-{{ $e['agency_code'] }}" aria-hidden="true">
                    <div class="modal-dialog">
                        <form class="modal-content"
                            action="{{ route('penugasan.pendaftaran.set-semula', $e['agency_code']) }}"
                            method="POST">
                            @csrf
                            <div class="modal-header">
                                <h5 class="modal-title" id="setSemulaLabel-{{ $e['agency_code'] }}">
                                    Set Semula {{ $e['agency_code'] }}
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"
                                    aria-label="Tutup"></button>
                            </div>
                            <div class="modal-body">
                                <p class="text-secondary">
                                    Penerimaan &amp; Pendaftaran Data akan kembali kepada Belum Mula,
                                    kemajuan analisis entiti ini dikosongkan, dan penugasan aktif
                                    (jika ada) ditarik balik. Entiti tidak lagi kelihatan kepada
                                    Pegawai Penyelaras Analisis.
                                </p>
                                <label class="form-label" for="reason-{{ $e['agency_code'] }}">Sebab</label>
                                <textarea id="reason-{{ $e['agency_code'] }}" name="reason" class="form-control"
                                    rows="2" maxlength="1000"
                                    placeholder="Nyatakan sebab entiti ditetapkan semula"></textarea>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">
                                    Batal
                                </button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-arrow-counterclockwise"></i> Set Semula
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            @endforeach
        @endcan
    @endif

    {{--
        Panel 2 — Pemantauan Entiti: penugasan Pegawai Analisis.
        Hanya entiti yang telah menyelesaikan pendaftaran muncul di sini.
    --}}
    @if ($bolehTugas)
        @if ($analysts->isEmpty())
            <x-alert type="warning" title="Tiada Pegawai Analisis berdaftar">
                Tambah pengguna dengan peranan Pegawai Analisis melalui modul Pentadbiran
                sebelum membuat penugasan.
            </x-alert>
        @endif

        <div class="report-card">

            <h4 class="section-title">Penugasan Pegawai Analisis</h4>
            <p class="text-secondary">{{ $jumlahAktif }} penugasan aktif dalam sistem.</p>

            <div class="table-responsive-custom">
                <table class="table-modern">
                    <thead>
                        <tr>
                            <th scope="col">Entiti</th>
                            <th scope="col">Pegawai Analisis (PA)</th>
                            <th scope="col">Ditugaskan Oleh</th>
                            <th scope="col">Tarikh Penugasan</th>
                            <th scope="col">Tugaskan / Tukar PA</th>
                            <th scope="col">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($entiti as $e)
                            @php $p = $e['penugasan']; @endphp
                            <tr>
                                <td>
                                    <strong>{{ $e['agency_code'] }}</strong><br>
                                    <span class="text-secondary text-nowrap">Sektor {{ $e['sector_code'] }}</span>
                                </td>
                                <td>
                                    @if ($p)
                                        <span class="status-badge status-rendah">{{ $p->assignedTo?->name }}</span>
                                    @else
                                        <span class="status-badge status-tinggi">Belum Ditugaskan</span>
                                    @endif
                                </td>
                                <td>{{ $p?->assignedBy?->name ?? '-' }}</td>
                                <td>{{ $p?->assigned_at?->format('d/m/Y H:i') ?? '-' }}</td>
                                <td>
                                    <form action="{{ route('penugasan.simpan', $e['agency_code']) }}" method="POST"
                                        class="assignment-form">
                                        @csrf
                                        <select name="assigned_to_user_id" class="form-select form-select-sm" required
                                            @disabled($analysts->isEmpty())>
                                            <option value="">-- Pilih Pegawai --</option>
                                            @foreach ($analysts as $analyst)
                                                <option value="{{ $analyst->id }}"
                                                    @disabled($p && $p->assigned_to_user_id === $analyst->id)>
                                                    {{ $analyst->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <button type="submit" class="btn btn-sm btn-primary"
                                            @disabled($analysts->isEmpty())>
                                            <i class="bi bi-person-check"></i>
                                            {{ $p ? 'Tukar PA' : 'Tugaskan' }}
                                        </button>
                                    </form>
                                </td>
                                <td class="text-nowrap">
                                    <a class="btn btn-sm btn-outline-light"
                                        href="{{ route('entiti.show', $e['agency_code']) }}"
                                        title="Maklumat entiti {{ $e['agency_code'] }}"
                                        aria-label="Maklumat entiti {{ $e['agency_code'] }}">
                                        <i class="bi bi-building" aria-hidden="true"></i>
                                    </a>
                                    <a class="btn btn-sm btn-outline-light"
                                        href="{{ route('penugasan.show', $e['agency_code']) }}">
                                        <i class="bi bi-clock-history"></i> Sejarah
                                    </a>
                                    @if ($p)
                                        <form action="{{ route('penugasan.tarik', $e['agency_code']) }}" method="POST"
                                            class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-light"
                                                title="Tarik balik penugasan {{ $e['agency_code'] }}"
                                                aria-label="Tarik balik penugasan {{ $e['agency_code'] }}">
                                                <i class="bi bi-person-dash" aria-hidden="true"></i>
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <x-empty-state colspan="6" icon="bi-person-check" title="Tiada entiti tersedia">
                                Entiti muncul di sini setelah Pegawai Penyelaras Rekod menandakan
                                Penerimaan &amp; Pendaftaran Data sebagai Selesai.
                            </x-empty-state>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">{{ $entiti->links() }}</div>

        </div>
    @endif

@endsection
