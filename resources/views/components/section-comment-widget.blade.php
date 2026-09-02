@php
    $sectionKey = str_replace(' ', '_', strtolower($section ?? ''));
    $hasComments = !empty($komentar[$sectionKey]);
    $commentCount = $hasComments ? count($komentar[$sectionKey]) : 0;
    $isCommentator = Auth::user()->isCoordinator() || Auth::user()->isKetuaBahagian();
    $commentSections = \App\Models\LaporanKomentar::seksyenLaporan();
    $showCommentForm = $isCommentator && isset($analisis);
@endphp

<div class="section-comments-widget">
    <button class="btn btn-sm btn-outline-secondary section-comments-toggle" type="button" data-bs-toggle="collapse"
        data-bs-target="#comments-{{ $sectionKey }}" title="Tampilkan/sembunyikan komentar untuk seksyen ini">
        <i class="bi bi-chat-dots"></i>
        @if ($hasComments)
            <span class="badge bg-warning text-dark">{{ $commentCount }}</span>
        @else
            <span class="text-muted small">+</span>
        @endif
    </button>

    <div class="collapse section-comments-collapse" id="comments-{{ $sectionKey }}">
        <div class="section-comments-panel mt-2 p-2 border rounded-2 bg-light">
            {{-- Form untuk menambah komentar (KB/PPA saja) --}}
            @if ($showCommentForm)
                <div class="mb-2 pb-2 border-bottom">
                    <form method="POST" action="{{ route('laporan.komentar.store', $analisis) }}"
                        class="d-flex gap-2 align-items-end section-comment-form">
                        @csrf
                        <input type="hidden" name="section" value="{{ $sectionKey }}">
                        <textarea name="content" class="form-control form-control-sm flex-grow-1" rows="1"
                            placeholder="Tambah komentar..." maxlength="500"></textarea>
                        <button type="submit" class="btn btn-sm btn-primary" title="Hantar komentar">
                            <i class="bi bi-send"></i>
                        </button>
                    </form>
                    <small class="text-muted d-block mt-1">Komentar hanya dilihat PA dan tidak dalam PDF</small>
                </div>
            @endif

            {{-- Tampilkan komentar yang ada --}}
            @if ($hasComments)
                <div class="section-comments-list">
                    @foreach ($komentar[$sectionKey] as $comment)
                        <div class="comment-item mb-2 pb-2 @if (!$loop->last) border-bottom @endif">
                            <div class="d-flex justify-content-between align-items-start gap-2">
                                <div class="flex-grow-1">
                                    <strong class="d-block small">{{ $comment->user->name }}</strong>
                                    <small class="text-muted d-block">
                                        {{ implode(', ', $comment->user->assignedRoleShortLabels()) }}
                                        • {{ $comment->created_at->format('d/m H:i') }}
                                    </small>
                                </div>
                                @if (Auth::user()->id === $comment->user_id || Auth::user()->isAdministrator())
                                    <form method="POST" action="{{ route('laporan.komentar.destroy', $comment) }}"
                                        class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-link text-danger p-0 m-0"
                                            title="Padam" onclick="return confirm('Padam?')">
                                            <i class="bi bi-x-circle"></i>
                                        </button>
                                    </form>
                                @endif
                            </div>
                            <p class="mb-0 small mt-1">{{ $comment->content }}</p>
                        </div>
                    @endforeach
                </div>
            @else
                @if ($showCommentForm)
                    <p class="text-muted small mb-0">Tiada komentar lagi.</p>
                @endif
            @endif
        </div>
    </div>
</div>
