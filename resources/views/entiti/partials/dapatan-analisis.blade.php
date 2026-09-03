    {{-- ── Dapatan Analisis ────────────────────────────────────────── --}}
    <div class="col-lg-6">
        <div class="report-card h-100">

            <h4 class="section-title">Dapatan Analisis</h4>

            @if ($analisis)
                <dl class="entity-facts mb-0">
                    <dt>Status Analisis</dt>
                    <dd>
                        <span class="status-badge {{ $analisis->selesai ? 'status-rendah' : 'status-sederhana' }}">
                            {{ $analisis->selesai ? 'Selesai' : 'Dalam Proses' }}
                        </span>
                    </dd>

                    <dt>Kod Rujukan</dt>
                    <dd>{{ $analisis->kod_rujukan ?? '-' }}</dd>

                    <dt>Status Laporan</dt>
                    <dd>{{ $analisis->status_laporan ?? '-' }}</dd>

                    <dt>Kemas Kini Terakhir</dt>
                    <dd>{{ $analisis->updated_at?->format('d/m/Y H:i') ?? '-' }}</dd>
                </dl>
            @else
                <p class="text-secondary mb-0">
                    Tiada dapatan analisis direkodkan untuk entiti ini.
                </p>
            @endif

        </div>
    </div>
