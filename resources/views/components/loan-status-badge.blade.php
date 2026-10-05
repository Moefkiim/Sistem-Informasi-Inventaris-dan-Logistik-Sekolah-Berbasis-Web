{{--
    Komponen badge status peminjaman yang terpusat.
    Penggunaan: <x-loan-status-badge :status="$loan->status" />
    Sumber label/warna: App\Models\Loan::STATUS_META
    Prop opsional `class` untuk ukuran/padding tambahan, misal class="fs-7 py-2 px-4"
--}}
@props(['status', 'class' => ''])

@php
    $entry = \App\Models\Loan::STATUS_META[$status]
        ?? ['label' => ucfirst(str_replace('_', ' ', (string) $status)), 'color' => 'secondary'];
@endphp

<span class="badge badge-light-{{ $entry['color'] }} {{ $class }}">{{ $entry['label'] }}</span>
