{{--
    Komponen badge kondisi barang yang terpusat.
    Penggunaan: <x-condition-badge :condition="$item->current_condition" />
    Prop opsional `class` untuk ukuran/padding tambahan.
--}}
@props(['condition', 'class' => ''])

@php
    $map = [
        'baik'         => ['color' => 'success', 'label' => 'Baik'],
        'rusak_ringan' => ['color' => 'warning', 'label' => 'Rusak Ringan'],
        'rusak_berat'  => ['color' => 'danger',  'label' => 'Rusak Berat'],
    ];
    $entry = $map[$condition] ?? ['color' => 'secondary', 'label' => ucfirst(str_replace('_', ' ', $condition))];
@endphp

<span class="badge badge-light-{{ $entry['color'] }} {{ $class }}">{{ $entry['label'] }}</span>
