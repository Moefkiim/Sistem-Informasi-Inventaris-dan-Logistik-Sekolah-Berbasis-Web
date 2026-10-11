@extends('layouts.app')

@section('title', 'Semua Notifikasi')
@section('header-title', 'Notifikasi')

@section('content')
<x-page-header title="Semua Notifikasi" description="Riwayat pemberitahuan pengajuan untuk akun Anda.">
    @slot('actions')
        @if($notifications->contains(fn ($n) => $n->read_at === null))
            <form action="{{ route('notifications.read_all') }}" method="POST" data-disable-on-submit>
                @csrf
                <button type="submit" class="btn btn-sm btn-light-primary fw-bolder">
                    <i class="bi bi-check2-all me-1"></i> Tandai semua dibaca
                </button>
            </form>
        @endif
    @endslot
</x-page-header>

<div class="card border-0 shadow-sm">
    <div class="card-body pt-6">
        <div class="table-responsive">
            <table class="table align-middle table-row-dashed fs-6 gy-5">
                <thead>
                    <tr class="text-start text-muted fw-bolder fs-7 text-uppercase gs-0">
                        <th>Pemberitahuan</th>
                        <th>Waktu</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody class="text-gray-600 fw-bold">
                    @forelse($notifications as $notification)
                        <tr class="{{ $notification->read_at === null ? 'bg-light-primary' : '' }}">
                            <td>
                                <div class="d-flex align-items-start gap-3">
                                    @if($notification->read_at === null)
                                        <span class="badge badge-primary badge-circle mt-1" style="width:10px;height:10px;">&nbsp;</span>
                                    @else
                                        <i class="bi bi-check2-circle text-muted mt-1"></i>
                                    @endif
                                    <div>
                                        <div class="text-dark fw-bolder">{{ $notification->data['title'] ?? 'Pemberitahuan' }}</div>
                                        <div class="text-muted fw-normal">{{ $notification->data['message'] ?? '' }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="text-nowrap">{{ $notification->created_at?->diffForHumans() }}</td>
                            <td class="text-end">
                                <a href="{{ route('notifications.open', $notification->id) }}" class="btn btn-sm btn-light btn-active-light-primary">
                                    Buka
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3">
                                <x-empty-state icon="bi-bell-slash" title="Belum ada notifikasi" description="Pemberitahuan pengajuan akan muncul di sini." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">
            {{ $notifications->withQueryString()->links() }}
        </div>
    </div>
</div>
@endsection
