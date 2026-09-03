    <div class="dashboard-section">
        <div class="report-card">
            <h4 class="section-title">Aktiviti Terkini</h4>

            @forelse ($aktivitiTerkini as $log)
                <div class="d-flex justify-content-between border-bottom py-2">
                    <span>
                        {{ $log->getActionLabel() }} —
                        <strong>{{ $log->agency_code }}</strong>
                        @if ($log->changedBy)
                            <span class="text-secondary">oleh {{ $log->changedBy->name }}</span>
                        @endif
                    </span>
                    <span class="text-secondary">{{ $log->changed_at?->format('d/m/Y H:i') }}</span>
                </div>
            @empty
                <x-empty-state icon="bi-activity" title="Tiada aktiviti">
                    Aktiviti muncul di sini apabila peringkat workflow, penugasan atau status laporan berubah.
                </x-empty-state>
            @endforelse
        </div>
    </div>
