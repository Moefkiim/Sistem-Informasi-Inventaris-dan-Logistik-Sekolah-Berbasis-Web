{{--
    Chip filter aktif yang bisa dilepas.
    Penggunaan:
    <x-filter-chip label="Jurusan" value="RPL" :remove-url="route(...)" />
--}}
@props(['label', 'value', 'removeUrl' => null])

<span class="badge badge-light-primary fw-semibold d-inline-flex align-items-center gap-2 py-2 px-3">
    <span><span class="text-muted fw-bold">{{ $label }}:</span> {{ $value }}</span>
    @if($removeUrl)
        <a href="{{ $removeUrl }}" class="text-primary lh-1 text-decoration-none" aria-label="Hapus filter {{ $label }}" title="Hapus filter">
            <i class="bi bi-x-lg fs-8"></i>
        </a>
    @endif
</span>
