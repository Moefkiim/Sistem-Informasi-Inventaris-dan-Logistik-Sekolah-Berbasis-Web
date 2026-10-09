{{--
    Empty state seragam untuk tabel/kartu kosong.
    Penggunaan:
    <x-empty-state icon="bi-inbox" title="Belum ada data" description="...">
        <a class="btn btn-primary" href="...">Aksi</a>
    </x-empty-state>
--}}
@props(['icon' => 'bi-inbox', 'title', 'description' => null])

<div class="text-center py-10 px-4">
    <i class="bi {{ $icon }} fs-1 text-muted mb-3 d-block"></i>
    <h4 class="fw-bold mb-2">{{ $title }}</h4>
    @if($description)
        <p class="text-muted mb-4">{{ $description }}</p>
    @endif
    {{ $slot }}
</div>
