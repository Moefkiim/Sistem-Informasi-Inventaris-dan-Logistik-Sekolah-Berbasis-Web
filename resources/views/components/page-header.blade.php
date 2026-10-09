{{--
    Header halaman seragam: judul + satu kalimat deskripsi + slot aksi opsional.
    Penggunaan:
    <x-page-header title="Laporan" description="...">
        <x-slot:actions> ...tombol... </x-slot:actions>
    </x-page-header>
--}}
@props(['title', 'description' => null])

<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-5">
    <div>
        <h1 class="fw-bolder fs-2 mb-1">{{ $title }}</h1>
        @if($description)
            <p class="text-muted mb-0">{{ $description }}</p>
        @endif
    </div>
    @isset($actions)
        <div class="d-flex flex-wrap gap-2">{{ $actions }}</div>
    @endisset
</div>
