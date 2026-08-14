@props([
    'icon' => 'bi-inbox',
    'title' => 'Tiada rekod',
    'colspan' => null,
])

@if ($colspan)
    {{-- Digunakan di dalam <tbody> jadual --}}
    <tr class="is-empty">
        <td colspan="{{ $colspan }}">
            <div class="empty-state">
                <span class="empty-state__icon" aria-hidden="true"><i class="bi {{ $icon }}"></i></span>
                <p class="empty-state__title">{{ $title }}</p>
                @if (trim($slot) !== '')
                    <p class="empty-state__text">{{ $slot }}</p>
                @endif
                @isset($action)
                    <div class="empty-state__action">{{ $action }}</div>
                @endisset
            </div>
        </td>
    </tr>
@else
    <div {{ $attributes->merge(['class' => 'empty-state']) }}>
        <span class="empty-state__icon" aria-hidden="true"><i class="bi {{ $icon }}"></i></span>
        <p class="empty-state__title">{{ $title }}</p>
        @if (trim($slot) !== '')
            <p class="empty-state__text">{{ $slot }}</p>
        @endif
        @isset($action)
            <div class="empty-state__action">{{ $action }}</div>
        @endisset
    </div>
@endif
