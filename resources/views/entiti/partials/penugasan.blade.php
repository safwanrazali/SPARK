    {{-- ── Penugasan ───────────────────────────────────────────────── --}}
    <div class="col-lg-6">
        <div class="report-card h-100">

            <h4 class="section-title">Penugasan</h4>

            @if ($penugasan)
                <dl class="entity-facts mb-0">
                    <dt>Pegawai Analisis</dt>
                    <dd>{{ $penugasan->assignedTo?->name ?? '-' }}</dd>

                    <dt>Ditugaskan Oleh</dt>
                    <dd>{{ $penugasan->assignedBy?->name ?? '-' }}</dd>

                    <dt>Tarikh Penugasan</dt>
                    <dd>{{ $penugasan->assigned_at?->format('d/m/Y H:i') ?? '-' }}</dd>

                    <dt>Status Penugasan</dt>
                    <dd>
                        <span class="status-badge {{ $penugasan->statusBadgeClass() }}">
                            {{ $penugasan->statusLabel() }}
                        </span>
                    </dd>
                </dl>
            @else
                <p class="text-secondary mb-0">
                    Entiti ini belum ditugaskan kepada mana-mana Pegawai Analisis.
                </p>
            @endif

        </div>
    </div>
