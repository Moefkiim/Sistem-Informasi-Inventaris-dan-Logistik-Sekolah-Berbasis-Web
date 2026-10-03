{{--
    Komponen badge status pengajuan yang terpusat.
    Penggunaan: <x-submission-status-badge :status="$submission->status" />
    Prop opsional `class` untuk ukuran/padding tambahan, misal class="fs-7 py-2 px-4"
--}}
@props(['status', 'class' => ''])

@php
    $map = [
        'draft'           => ['color' => 'secondary', 'label' => 'Draft'],
        'submitted'       => ['color' => 'warning',   'label' => 'Menunggu Review Sarpras'],
        'reviewed_sarpras'=> ['color' => 'primary',   'label' => 'Menunggu Approval Kepala Sekolah'],
        'approved'        => ['color' => 'success',   'label' => 'Disetujui'],
        'rejected'        => ['color' => 'danger',    'label' => 'Ditolak'],
        'cancelled'       => ['color' => 'dark',      'label' => 'Dibatalkan'],
    ];
    $entry = $map[$status] ?? ['color' => 'secondary', 'label' => ucfirst(str_replace('_', ' ', $status))];
@endphp

<span class="badge badge-light-{{ $entry['color'] }} {{ $class }}">{{ $entry['label'] }}</span>
