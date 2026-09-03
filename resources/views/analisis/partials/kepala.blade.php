    <div class="report-card mb-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h4 class="section-title mb-1">{{ $agensi['code'] }}</h4>
                <span class="text-secondary">Sektor {{ $sectorCode }}</span>
            </div>
            @if ($analisis?->selesai)
                <span class="status-badge status-rendah">Analisis Selesai</span>
            @endif
        </div>
    </div>
