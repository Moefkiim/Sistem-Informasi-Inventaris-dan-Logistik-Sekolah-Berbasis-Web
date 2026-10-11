<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Notification;

/**
 * Notifikasi dalam aplikasi (kanal database) untuk peristiwa pengajuan.
 * URL tautan disesuaikan dengan peran penerima agar langsung ke halaman
 * detail yang boleh mereka akses.
 */
class SubmissionNotification extends Notification
{
    public function __construct(
        public int $submissionId,
        public string $submissionNumber,
        public string $kind,
        public string $title,
        public string $message,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'submission_id' => $this->submissionId,
            'submission_number' => $this->submissionNumber,
            'kind' => $this->kind,
            'title' => $this->title,
            'message' => $this->message,
            'url' => $this->targetUrl($notifiable),
        ];
    }

    private function targetUrl(object $notifiable): string
    {
        if ($notifiable instanceof User) {
            if ($notifiable->isSarpras()) {
                return route('sarpras.submissions.show', $this->submissionId);
            }

            if ($notifiable->isKepalaSekolah()) {
                return route('kepala_sekolah.approval.show', $this->submissionId);
            }
        }

        return route('kajur.submissions.show', $this->submissionId);
    }
}
