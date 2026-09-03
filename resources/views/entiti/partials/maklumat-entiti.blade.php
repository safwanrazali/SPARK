    {{-- ── Entiti ──────────────────────────────────────────────────────── --}}
    <div class="report-card mb-4">

        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">

            <div>
                <h4 class="section-title mb-1">{{ $entiti['agency_code'] }}</h4>
                <p class="text-secondary mb-0">
                    {{ $entiti['agency_name'] }} · Sektor {{ $entiti['sector_code'] }}
                    @if (! empty($entiti['sector_name']))
                        {{ $entiti['sector_name'] }}
                    @endif
                </p>
            </div>

            {{-- Tindakan yang tersedia — hanya yang dibenarkan bagi peranan semasa. --}}
            <div class="entity-actions">
                <a href="{{ route('workflow.show', $entiti['agency_code']) }}" class="btn btn-sm btn-primary">
                    <i class="bi bi-diagram-3"></i> Kemajuan
                </a>

                @can('manage-analysis')
                    <a href="{{ route('analisis.borang', [
                        'sector_code' => $entiti['sector_code'],
                        'agency_code' => $entiti['agency_code'],
                    ]) }}"
                        class="btn btn-sm btn-outline-light">
                        <i class="bi bi-pencil-square"></i> Borang Analisis
                    </a>
                @endcan

                @if ($analisis)
                    <a href="{{ route('laporan.inventori', $analisis) }}" class="btn btn-sm btn-outline-light">
                        <i class="bi bi-file-earmark-text"></i> Laporan
                    </a>
                @endif

                @can('view-audit-trail')
                    <a href="{{ route('audit.index', ['agency_code' => $entiti['agency_code']]) }}"
                        class="btn btn-sm btn-outline-light">
                        <i class="bi bi-shield-check"></i> Jejak Audit
                    </a>
                @endcan
            </div>

        </div>

    </div>
