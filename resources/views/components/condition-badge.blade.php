{{--
    Komponen badge kondisi barang yang terpusat.
    Penggunaan: <x-condition-badge :condition="$item->current_condition" />
    Prop opsional `class` untuk ukuran/padding tambahan.
--}}
@props(['condition', 'class' => ''])

@php
    $label = \App\Support\AssetCondition::label($condition) ?? '—';
    $color = \App\Support\AssetCondition::color($condition);
@endphp

<span class="badge badge-{{ $color }} {{ $class }}">{{ $label }}</span>