@props(['submission'])

@php
    $pipeline = [
        'draft'           => ['icon' => 'bi-pencil-square',  'badge' => 'primary'],
        'submitted'       => ['icon' => 'bi-send',           'badge' => 'info'],
        'reviewed_sarpras'=> ['icon' => 'bi-search',         'badge' => 'warning'],
        'approved'        => ['icon' => 'bi-check-circle',   'badge' => 'success'],
        'rejected'        => ['icon' => 'bi-x-circle',       'badge' => 'danger'],
        'realized'        => ['icon' => 'bi-box-arrow-down', 'badge' => 'dark'],
        'cancelled'       => ['icon' => 'bi-slash-circle',   'badge' => 'secondary'],
    ];
    $histories = ($submission->histories ?? collect())->sortBy('id');
@endphp

<ol class="list-unstyled m-0">
    @forelse($histories as $history)
        @php
            $meta = $pipeline[$history->to_status] ?? ['icon' => 'bi-dot', 'badge' => 'secondary'];
            $label = \App\Models\Submission::statusLabel($history->to_status);
        @endphp
        <li class="d-flex mb-4">
            <div class="d-flex flex-column align-items-center me-4">
                <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-light text-{{ $meta['badge'] }} fw-bolder" style="width:34px;height:34px;font-size:1.05rem;">
                    <i class="bi {{ $meta['icon'] }}"></i>
                </span>
                @if(! $loop->last)
                    <span class="flex-grow-1 w-px mt-1" style="min-height:24px;"></span>
                @endif
            </div>
            <div class="pb-3">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span class="fw-bolder text-dark">{{ $label }}</span>
                    <span class="badge badge-light-{{ $meta['badge'] }}">{{ strtoupper($label) }}</span>
                </div>
                <div class="text-muted fs-7 mt-1">
                    {{ $history->actor?->name ?? 'Sistem' }} — {{ $history->recorded_at?->format('d/m/Y H:i') }}
                </div>
                @if($history->notes && $history->to_status !== 'rejected')
                    <div class="text-gray-600 fs-7 mt-1">{{ $history->notes }}</div>
                @endif
                @if($history->to_status === 'rejected' && $history->notes)
                    <div class="alert alert-danger border-0 mt-2 py-2 px-3 fs-7 mb-0">
                        <i class="bi bi-exclamation-circle me-1"></i> {{ $history->notes }}
                    </div>
                @endif
            </div>
        </li>
    @empty
        <li class="text-muted fs-7">Belum ada riwayat proses untuk pengajuan ini.</li>
    @endforelse
</ol>