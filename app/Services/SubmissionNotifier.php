<?php

namespace App\Services;

use App\Models\Submission;
use App\Models\User;
use App\Notifications\SubmissionNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Mengirim notifikasi dalam aplikasi untuk peristiwa pengajuan.
 *
 * Pemanggil (controller/service) memanggil method di sini SETELAH transaksi
 * basis data berhasil, sehingga notifikasi tidak pernah terkirim bila transisi
 * status gagal.
 */
class SubmissionNotifier
{
    /**
     * Pengajuan diajukan → seluruh Sarpras aktif.
     */
    public function submitted(Submission $submission): void
    {
        $this->send(
            $this->activeUsers('sarpras'),
            $submission,
            'submitted',
            'Pengajuan Baru',
            "Pengajuan {$submission->submission_number} dari {$submission->department} menunggu ditinjau."
        );
    }

    /**
     * Ditinjau Sarpras → seluruh Kepala Sekolah aktif.
     */
    public function reviewed(Submission $submission): void
    {
        $this->send(
            $this->activeUsers('kepala_sekolah'),
            $submission,
            'reviewed',
            'Menunggu Persetujuan',
            "Pengajuan {$submission->submission_number} telah ditinjau Sarpras dan menunggu persetujuan Anda."
        );
    }

    /**
     * Disetujui → pengaju dan seluruh Sarpras aktif (siap direalisasi).
     */
    public function approved(Submission $submission): void
    {
        $this->send(
            $this->activeUsers('sarpras'),
            $submission,
            'approved',
            'Pengajuan Siap Direalisasi',
            "Pengajuan {$submission->submission_number} disetujui dan siap direalisasi."
        );

        if ($author = $submission->user) {
            $this->send(
                collect([$author]),
                $submission,
                'approved',
                'Pengajuan Disetujui',
                "Pengajuan {$submission->submission_number} Anda telah disetujui Kepala Sekolah."
            );
        }
    }

    /**
     * Ditolak → pengaju, sertakan alasan ringkas.
     */
    public function rejected(Submission $submission): void
    {
        if (! $author = $submission->user) {
            return;
        }

        $reason = $submission->principal_notes ?: $submission->sarpras_notes;
        $suffix = $reason ? " Alasan: {$reason}" : '';

        $this->send(
            collect([$author]),
            $submission,
            'rejected',
            'Pengajuan Ditolak',
            "Pengajuan {$submission->submission_number} ditolak.{$suffix}"
        );
    }

    /**
     * Terealisasi → pengaju.
     */
    public function realized(Submission $submission): void
    {
        if (! $author = $submission->user) {
            return;
        }

        $this->send(
            collect([$author]),
            $submission,
            'realized',
            'Pengajuan Terealisasi',
            "Pengajuan {$submission->submission_number} telah direalisasikan dan barang masuk telah tercatat."
        );
    }

    /**
     * @return Collection<int, User>
     */
    private function activeUsers(string $role): Collection
    {
        return User::query()
            ->where('role', $role)
            ->where('is_active', true)
            ->get();
    }

    /**
     * @param  Collection<int, User>  $recipients
     */
    private function send(
        Collection $recipients,
        Submission $submission,
        string $kind,
        string $title,
        string $message,
    ): void {
        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send(
            $recipients,
            new SubmissionNotification(
                $submission->id,
                $submission->submission_number,
                $kind,
                $title,
                $message,
            )
        );
    }
}
