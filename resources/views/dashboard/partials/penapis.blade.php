    {{-- Penapis: sektor + julat tarikh status workflow (Fasa 7). --}}
    <div class="report-card mb-4">
        <form action="{{ route('dashboard') }}" method="GET" class="row g-2 align-items-end">

            <div class="col-md-4">
                <label class="form-label" for="sector_code">Sektor</label>
                <select id="sector_code" name="sector_code" class="form-select">
                    <option value="">Semua sektor</option>
                    @foreach (config('sektor') as $kod => $sektor)
                        <option value="{{ $kod }}" @selected($penapis['sector_code'] === $kod)>{{ $kod }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label" for="dari">Tarikh Status Dari</label>
                <input type="date" id="dari" name="dari" class="form-control" value="{{ $penapis['dari'] }}">
            </div>

            <div class="col-md-3">
                <label class="form-label" for="hingga">Hingga</label>
                <input type="date" id="hingga" name="hingga" class="form-control" value="{{ $penapis['hingga'] }}">
            </div>

            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-funnel"></i> Papar
                </button>
            </div>

            @if ($penapis['aktif'])
                <div class="col-12">
                    <span class="text-secondary">
                        Penapis aktif:
                        {{ $penapis['sector_code'] ?? 'Semua sektor' }}
                        @if ($penapis['dari'] || $penapis['hingga'])
                            · Tarikh status workflow
                            {{ $penapis['dari'] ?? 'awal' }} – {{ $penapis['hingga'] ?? 'kini' }}
                        @endif
                    </span>
                    <a href="{{ route('dashboard') }}" class="btn btn-sm btn-outline-light ms-2">Set Semula</a>
                </div>
            @endif

        </form>
    </div>
