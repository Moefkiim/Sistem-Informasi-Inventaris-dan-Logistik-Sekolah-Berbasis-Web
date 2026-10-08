{{--
    Komponen badge status operasional unit aset.
    Penggunaan: <x-unit-status-badge :status="$unit->current_status" />
    Prop opsional `class` untuk ukuran/padding tambahan.
--}}
@props(['status', 'class' => ''])

@php
    $map = \App\Models\AssetUnit::STATUS_LABELS;
    $colors = \App\Models\AssetUnit::STATUS_BADGE_COLORS;
    $label = $map[$status] ?? ucfirst(str_replace('_', ' ', $status));
    $color = $colors[$status] ?? 'secondary';
@endphp

<span class="badge badge-light-{{ $color }} {{ $class }}">{{ $label }}</span>
