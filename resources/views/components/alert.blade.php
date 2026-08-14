@props([
    'type' => 'info',
    'title' => null,
    'icon' => null,
])

@php
    $ikon = $icon ?? [
        'success' => 'bi-check-circle-fill',
        'danger' => 'bi-exclamation-octagon-fill',
        'warning' => 'bi-exclamation-triangle-fill',
        'info' => 'bi-info-circle-fill',
    ][$type] ?? 'bi-info-circle-fill';
@endphp

<div {{ $attributes->merge(['class' => 'app-alert app-alert--' . $type]) }} role="alert"
    aria-live="{{ $type === 'danger' ? 'assertive' : 'polite' }}">

    <i class="bi {{ $ikon }} app-alert__icon" aria-hidden="true"></i>

    <div class="app-alert__body">
        @if ($title)
            <p class="app-alert__title">{{ $title }}</p>
        @endif

        {{ $slot }}
    </div>

</div>
