@extends('layouts.app')

@section('title', 'Jejak Audit')

@section('page-title', 'Jejak Audit')

@section('content')

    <div class="report-card mb-4">

        <h4 class="section-title">Penapis</h4>
        <p class="text-secondary">
            Rekod jejak audit hanya boleh ditambah. Ia tidak boleh diubah atau
            dipadam melalui mana-mana antara muka sistem.
        </p>

        <form action="{{ route('audit.index') }}" method="GET" class="row g-2 align-items-end">

            <div class="col-md-6 col-lg-3">
                <label class="form-label" for="agency_code">Entiti</label>
                <select id="agency_code" name="agency_code" class="form-select">
                    <option value="">Semua entiti</option>
                    @foreach ($entiti as $e)
                        <option value="{{ $e['agency_code'] }}" @selected($penapis['agency_code'] === $e['agency_code'])>
                            {{ $e['agency_code'] }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-6 col-lg-3">
                <label class="form-label" for="action">Tindakan</label>
                <select id="action" name="action" class="form-select">
                    <option value="">Semua tindakan</option>
                    @foreach ($tindakan as $kunci => $label)
                        <option value="{{ $kunci }}" @selected($penapis['action'] === $kunci)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-6 col-lg-2">
                <label class="form-label" for="user_id">Pengguna</label>
                <select id="user_id" name="user_id" class="form-select">
                    <option value="">Semua</option>
                    @foreach ($pengguna as $p)
                        <option value="{{ $p->id }}" @selected((string) $penapis['user_id'] === (string) $p->id)>
                            {{ $p->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-6 col-md-3 col-lg-2">
                <label class="form-label" for="dari">Dari</label>
                <input type="date" id="dari" name="dari" class="form-control" value="{{ $penapis['dari'] }}">
            </div>

            <div class="col-6 col-md-3 col-lg-2">
                <label class="form-label" for="hingga">Hingga</label>
                <input type="date" id="hingga" name="hingga" class="form-control" value="{{ $penapis['hingga'] }}">
            </div>

            <div class="col-12 mt-3">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-funnel"></i> Papar
                </button>
                <a href="{{ route('audit.index') }}" class="btn btn-outline-light">Set Semula</a>
            </div>

        </form>

    </div>

    <div class="report-card">

        <h4 class="section-title">Rekod Perubahan</h4>
        <p class="text-secondary">{{ $rekod->total() }} rekod ditemui.</p>

        <div class="table-responsive-custom">
            <table class="table-modern">
                <thead>
                    <tr>
                        <th scope="col">Tarikh &amp; Masa</th>
                        <th scope="col">Entiti</th>
                        <th scope="col">Tindakan</th>
                        <th scope="col">Nilai Lama</th>
                        <th scope="col">Nilai Baharu</th>
                        <th scope="col">Oleh</th>
                        <th scope="col">Maklumat Tambahan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rekod as $log)
                        <tr>
                            <td class="text-nowrap">{{ $log->changed_at?->format('d/m/Y H:i') }}</td>
                            <td>
                                <strong>{{ $log->agency_code ?? '-' }}</strong><br>
                                <span class="text-secondary">{{ $log->agency_code }}</span>
                            </td>
                            <td>{{ $log->getActionLabel() }}</td>
                            <td>{{ $log->old_value ?? '-' }}</td>
                            <td>{{ $log->new_value ?? '-' }}</td>
                            <td>{{ $log->changedBy?->name ?? '-' }}</td>
                            <td>
                                @php
                                    $meta = collect($log->metadata ?? [])
                                        ->filter(fn($v) => $v !== null && $v !== '' && !is_array($v));
                                @endphp
                                @if ($meta->isEmpty())
                                    <span class="text-secondary">-</span>
                                @else
                                    <ul class="audit-meta">
                                        @foreach ($meta as $kunci => $nilai)
                                            <li>
                                                <span class="audit-meta__kunci">{{ str_replace('_', ' ', $kunci) }}:</span>
                                                {{ is_bool($nilai) ? ($nilai ? 'ya' : 'tidak') : $nilai }}
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <x-empty-state colspan="7" icon="bi-shield-check" title="Tiada rekod jejak audit">
                            Tiada perubahan sepadan dengan penapis semasa. Longgarkan penapis untuk melihat lebih banyak rekod.
                        </x-empty-state>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-3">{{ $rekod->links() }}</div>

    </div>

@endsection
