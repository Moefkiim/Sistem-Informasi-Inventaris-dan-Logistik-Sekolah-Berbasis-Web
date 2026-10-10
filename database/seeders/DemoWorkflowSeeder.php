<?php

namespace Database\Seeders;

use App\Models\ActivityLog;
use App\Models\AssetUnit;
use App\Models\Item;
use App\Models\Loan;
use App\Models\Submission;
use App\Models\User;
use Database\Seeders\Concerns\GuardsDemoEnvironment;
use Illuminate\Database\Seeder;

/**
 * Alur kerja demo: pengajuan pada setiap status beserta riwayat, peminjaman
 * pada setiap status, dan riwayat aktivitas yang dicatat lewat jalur yang sama
 * dengan aplikasi (ActivityLog::log).
 */
class DemoWorkflowSeeder extends Seeder
{
    use GuardsDemoEnvironment;

    private User $kajurRpl;

    private User $kajurTkj;

    private User $sarpras;

    private User $kepsek;

    private int $submissionSeq = 0;

    private int $loanSeq = 0;

    public function run(): void
    {
        if (! $this->demoDataAllowed()) {
            $this->command?->warn('DemoWorkflowSeeder dilewati: APP_ENV=production.');

            return;
        }

        if (Submission::exists() || Loan::exists()) {
            $this->command?->info('Pengajuan/peminjaman sudah ada, DemoWorkflowSeeder dilewati.');

            return;
        }

        $this->kajurRpl = User::where('username', 'kajur_rpl')->firstOrFail();
        $this->kajurTkj = User::where('username', 'kajur_tkj')->firstOrFail();
        $this->sarpras = User::where('username', 'sarpras')->firstOrFail();
        $this->kepsek = User::where('username', 'kepsek')->firstOrFail();

        $this->seedSubmissions();
        $this->seedLoans();
    }

    private function seedSubmissions(): void
    {
        // 1. Draft (masih dapat diedit Kajur).
        $draft = $this->makeSubmission($this->kajurRpl, 'Pengadaan Laptop Praktik RPL', 'Menambah komputer praktik siswa kelas X.', [
            ['item_name' => 'Laptop Lenovo ThinkPad', 'quantity' => 5, 'unit' => 'Unit', 'estimated_price' => 12000000],
        ]);

        // 2. Submitted (menunggu diproses Sarpras).
        $submitted = $this->makeSubmission($this->kajurRpl, 'Pengadaan Proyektor Ruang Kelas', 'Proyektor tambahan untuk ruang kelas RPL.', [
            ['item_name' => 'Proyektor Epson EB-X06', 'quantity' => 2, 'unit' => 'Unit', 'estimated_price' => 5500000],
        ]);
        $this->submitToSarpras($submitted, $this->kajurRpl);

        // 3. Reviewed Sarpras (menunggu keputusan Kepala Sekolah).
        $reviewed = $this->makeSubmission($this->kajurRpl, 'Pengadaan Kamera Dokumentasi', 'Kamera untuk dokumentasi kegiatan jurusan.', [
            ['item_name' => 'Kamera Canon EOS 250D', 'quantity' => 1, 'unit' => 'Unit', 'estimated_price' => 8200000],
            ['item_name' => 'Lensa Kit 18-55mm', 'quantity' => 1, 'unit' => 'Unit', 'estimated_price' => 1500000],
        ]);
        $this->submitToSarpras($reviewed, $this->kajurRpl);
        $this->reviewBySarpras($reviewed, 'Stok belum tersedia, disetujui secara prinsip. Diteruskan ke Kepala Sekolah.');

        // 4. Approved oleh Kepala Sekolah.
        $approved = $this->makeSubmission($this->kajurRpl, 'Pengadaan Perangkat Jaringan Lab', 'Switch dan kabel untuk penataan lab RPL.', [
            ['item_name' => 'Switch Gigabit 24 Port', 'quantity' => 2, 'unit' => 'Unit', 'estimated_price' => 2500000],
        ]);
        $this->submitToSarpras($approved, $this->kajurRpl);
        $this->reviewBySarpras($approved, 'Sesuai kebutuhan pengembangan jaringan lab.');
        $this->decideByPrincipal($approved, 'Disetujui, ajukan anggaran ke bagian keuangan.', true);

        // 5. Rejected oleh Kepala Sekolah.
        $rejected = $this->makeSubmission($this->kajurRpl, 'Pengadaan Meja Lab Komputer', 'Penggantian meja lab yang sudah tua.', [
            ['item_name' => 'Meja Komputer', 'quantity' => 20, 'unit' => 'Unit', 'estimated_price' => 750000],
        ]);
        $this->submitToSarpras($rejected, $this->kajurRpl);
        $this->reviewBySarpras($rejected, 'Menunggu peninjauan lanjutan.');
        $this->decideByPrincipal($rejected, 'Ditolak sementara hingga anggaran tersedia tahun depan.', false);

        // 6. Submisi jurusan lain (memperlihatkan isolasi antar jurusan).
        $tkj = $this->makeSubmission($this->kajurTkj, 'Pengadaan Router Tambahan', 'Penambahan router untuk praktik TKJ.', [
            ['item_name' => 'Router Mikrotik RB750Gr3', 'quantity' => 3, 'unit' => 'Unit', 'estimated_price' => 950000],
        ]);
        $this->submitToSarpras($tkj, $this->kajurTkj);
    }

    private function seedLoans(): void
    {
        $proyektor = Item::where('code', 'BRG-RPL-0002')->firstOrFail();
        $laptop = Item::where('code', 'BRG-RPL-0001')->firstOrFail();
        $kamera = Item::where('code', 'BRG-TKJ-0001')->firstOrFail();
        $router = Item::where('code', 'BRG-TKJ-0002')->firstOrFail();
        $tinta = Item::where('code', 'BRG-UMM-0002')->firstOrFail();

        // 1. Menunggu persetujuan Sarpras.
        $this->makeLoan($proyektor, $proyektor->assetUnits()->orderBy('id')->first(), 'menunggu', [
            'quantity' => 1,
            'borrower_user_id' => $this->kajurRpl->id,
            'borrower_name' => $this->kajurRpl->name,
            'borrower_department' => 'RPL',
            'purpose' => 'Presentasi tugas akhir siswa',
            'loan_date' => now()->subDays(2)->toDateString(),
            'due_date' => now()->addDays(5)->toDateString(),
        ]);

        // 2. Disetujui, belum diambil (barang habis pakai).
        $this->makeLoan($tinta, null, 'disetujui', [
            'quantity' => 2,
            'borrower_name' => 'Staf Tata Usaha',
            'borrower_department' => null,
            'purpose' => 'Kepercayaan satu paket',
            'loan_date' => now()->subDays(1)->toDateString(),
            'due_date' => now()->addDays(6)->toDateString(),
            'approved_by' => $this->sarpras->id,
            'approved_at' => now()->subHours(20),
        ], $this->sarpras);

        // 3. Sedang dipinjam (unit laptop fisik).
        $this->makeLoan($laptop, $laptop->assetUnits()->orderBy('id')->first(), 'dipinjam', [
            'quantity' => 1,
            'borrower_user_id' => $this->kajurRpl->id,
            'borrower_name' => $this->kajurRpl->name,
            'borrower_department' => 'RPL',
            'purpose' => 'Penyusunan laporan jurusan',
            'loan_date' => now()->subDays(10)->toDateString(),
            'due_date' => now()->addDays(4)->toDateString(),
            'approved_by' => $this->sarpras->id,
            'approved_at' => now()->subDays(10),
        ], $this->sarpras);

        // 4. Sudah dikembalikan (dengan kondisi baik).
        $this->makeLoan($kamera, $kamera->assetUnits()->orderBy('id')->first(), 'dikembalikan', [
            'quantity' => 1,
            'borrower_user_id' => $this->kajurRpl->id,
            'borrower_name' => $this->kajurRpl->name,
            'borrower_department' => 'RPL',
            'purpose' => 'Dokumentasi kegiatan sekolah',
            'loan_date' => now()->subDays(20)->toDateString(),
            'due_date' => now()->subDays(13)->toDateString(),
            'return_date' => now()->subDays(14)->toDateString(),
            'condition_on_return' => 'baik',
            'approved_by' => $this->sarpras->id,
            'approved_at' => now()->subDays(20),
            'returned_to' => $this->sarpras->id,
            'returned_at' => now()->subDays(14),
        ], $this->sarpras);

        // 5. Terlambat (melewati tenggat, belum dikembalikan).
        $this->makeLoan($router, $router->assetUnits()->orderBy('id')->first(), 'terlambat', [
            'quantity' => 1,
            'borrower_user_id' => $this->kajurTkj->id,
            'borrower_name' => $this->kajurTkj->name,
            'borrower_department' => 'TKJ',
            'purpose' => 'Kegiatan praktik jaringan',
            'loan_date' => now()->subDays(25)->toDateString(),
            'due_date' => now()->subDays(5)->toDateString(),
            'approved_by' => $this->sarpras->id,
            'approved_at' => now()->subDays(25),
        ], $this->sarpras);
    }

    /**
     * @param  array<int,array{item_name:string,quantity:int,unit:string,estimated_price?:float}>  $items
     */
    private function makeSubmission(User $actor, string $title, string $purpose, array $items): Submission
    {
        $this->submissionSeq++;
        $number = 'REQ-'.now()->format('Ymd').'-'.str_pad((string) $this->submissionSeq, 4, '0', STR_PAD_LEFT);

        $submission = new Submission;
        $submission->submission_number = $number;
        $submission->user_id = $actor->id;
        $submission->department = $actor->department ?? 'Umum';
        $submission->title = $title;
        $submission->purpose = $purpose;
        $submission->status = 'draft';
        $submission->save();

        foreach ($items as $item) {
            $submission->items()->create([
                'item_name' => $item['item_name'],
                'quantity' => $item['quantity'],
                'unit' => $item['unit'],
                'estimated_price' => $item['estimated_price'] ?? 0,
                'specification' => null,
            ]);
        }

        $submission->recordStatusChange(null, 'draft', $actor);

        $this->runAsUser($actor, fn () => ActivityLog::log(
            'submission_created',
            "Pengajuan {$number} dibuat oleh {$actor->name} (status: draft)",
            $submission,
            [],
            ['status' => 'draft'],
            $number
        ));

        return $submission;
    }

    private function submitToSarpras(Submission $submission, User $actor): void
    {
        $submission->status = 'submitted';
        $submission->save();
        $submission->recordStatusChange('draft', 'submitted', $actor);

        $this->runAsUser($actor, fn () => ActivityLog::log(
            'submission_submitted',
            "Pengajuan {$submission->submission_number} dikirimkan ke Sarpras oleh {$actor->name}",
            $submission,
            ['status' => 'draft'],
            ['status' => 'submitted'],
            $submission->submission_number
        ));
    }

    private function reviewBySarpras(Submission $submission, string $notes): void
    {
        $submission->status = 'reviewed_sarpras';
        $submission->sarpras_notes = $notes;
        $submission->sarpras_user_id = $this->sarpras->id;
        $submission->reviewed_at = now()->subDays(4);
        $submission->save();
        $submission->recordStatusChange('submitted', 'reviewed_sarpras', $this->sarpras, $notes);

        $this->runAsUser($this->sarpras, fn () => ActivityLog::log(
            'submission_reviewed',
            "Pengajuan {$submission->submission_number} diverifikasi oleh Sarpras: {$notes}",
            $submission,
            ['status' => 'submitted'],
            ['status' => 'reviewed_sarpras', 'sarpras_notes' => $notes],
            $submission->submission_number
        ));
    }

    private function decideByPrincipal(Submission $submission, string $notes, bool $approve): void
    {
        $status = $approve ? 'approved' : 'rejected';
        $submission->status = $status;
        $submission->principal_notes = $notes;
        $submission->principal_user_id = $this->kepsek->id;
        $submission->decided_at = now()->subDays(2);
        $submission->save();
        $submission->recordStatusChange('reviewed_sarpras', $status, $this->kepsek, $notes);

        $this->runAsUser($this->kepsek, fn () => ActivityLog::log(
            $approve ? 'submission_approved' : 'submission_rejected',
            "Pengajuan {$submission->submission_number} ".($approve ? 'DISETUJUI' : 'DITOLAK')." oleh Kepala Sekolah: {$notes}",
            $submission,
            ['status' => 'reviewed_sarpras'],
            ['status' => $status, 'principal_notes' => $notes],
            $submission->submission_number
        ));
    }

    /**
     * @param  array<string,mixed>  $attributes
     */
    private function makeLoan(Item $item, ?AssetUnit $unit, string $status, array $attributes, ?User $actor = null): Loan
    {
        $this->loanSeq++;
        $number = 'LN-'.now()->format('Ymd').'-'.str_pad((string) $this->loanSeq, 4, '0', STR_PAD_LEFT);

        $loan = new Loan;
        $loan->loan_number = $number;
        $loan->item_id = $item->id;
        $loan->asset_unit_id = $unit?->id;
        $loan->recorded_by = $this->sarpras->id;
        $loan->status = $status;
        $loan->quantity = $attributes['quantity'] ?? 1;
        $loan->borrower_user_id = $attributes['borrower_user_id'] ?? null;
        $loan->borrower_name = $attributes['borrower_name'];
        $loan->borrower_department = $attributes['borrower_department'] ?? null;
        $loan->purpose = $attributes['purpose'];
        $loan->loan_date = $attributes['loan_date'];
        $loan->due_date = $attributes['due_date'];
        $loan->return_date = $attributes['return_date'] ?? null;
        $loan->condition_on_loan = $unit?->current_condition ?? $item->current_condition;
        $loan->condition_on_return = $attributes['condition_on_return'] ?? null;
        $loan->approved_by = $attributes['approved_by'] ?? null;
        $loan->approved_at = $attributes['approved_at'] ?? null;
        $loan->returned_to = $attributes['returned_to'] ?? null;
        $loan->returned_at = $attributes['returned_at'] ?? null;
        $loan->notes = $attributes['notes'] ?? null;
        $loan->save();

        // Unit fisik berstatus dipinjam saat barang berada di tangan peminjam.
        if ($unit && in_array($status, Loan::STATUS_BORROWED, true)) {
            $unit->current_status = 'dipinjam';
            $unit->save();
            $item->current_status = $item->recalculateAggregateStatus();
            $item->save();
        }

        $logActor = $actor ?? $this->sarpras;

        $this->runAsUser($logActor, function () use ($loan, $item, $number, $status) {
            ActivityLog::log(
                'loan_created',
                "Peminjaman baru: {$loan->borrower_name} meminjam {$item->name} (x{$loan->quantity})",
                $loan,
                [],
                ['status' => 'menunggu', 'item' => $item->code],
                $number
            );

            if ($status !== 'menunggu') {
                ActivityLog::log(
                    'loan_approved',
                    "Peminjaman {$number} disetujui dan barang diserahkan ke {$loan->borrower_name}",
                    $loan,
                    ['status' => 'menunggu'],
                    ['status' => 'dipinjam'],
                    $number
                );
            }

            if ($status === 'dikembalikan') {
                ActivityLog::log(
                    'loan_returned',
                    "Barang {$item->name} dikembalikan dari {$loan->borrower_name} (Kondisi: baik)",
                    $loan,
                    ['status' => 'dipinjam'],
                    ['status' => 'dikembalikan', 'condition_on_return' => 'baik'],
                    $number
                );
            }
        });

        return $loan;
    }
}
