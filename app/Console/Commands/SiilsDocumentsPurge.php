<?php

namespace App\Console\Commands;

use App\Models\Document;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class SiilsDocumentsPurge extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'siils:documents:purge
                            {--days=30 : Hapus dokumen yang sudah soft-deleted lebih dari N hari}
                            {--dry-run : Simulasi tanpa benar-benar menghapus}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Hapus permanen dokumen (file fisik + record DB) yang sudah diarsipkan lebih dari N hari.';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $dryRun = (bool) $this->option('dry-run');
        $cutoff = now()->subDays($days);

        $this->info("Mencari dokumen yang diarsipkan sebelum: {$cutoff->toDateTimeString()} (lebih dari {$days} hari)");

        $documents = Document::onlyTrashed()
            ->where('deleted_at', '<=', $cutoff)
            ->get();

        if ($documents->isEmpty()) {
            $this->info('Tidak ada dokumen yang memenuhi kriteria purge.');

            return self::SUCCESS;
        }

        $this->info("Ditemukan {$documents->count()} dokumen untuk dihapus permanen.");

        if ($dryRun) {
            $this->warn('[DRY-RUN] Mode simulasi — tidak ada yang benar-benar dihapus.');
        }

        $deletedCount = 0;
        $errorCount = 0;

        foreach ($documents as $document) {
            $this->line("  → [{$document->id}] {$document->title} (deleted_at: {$document->deleted_at})");

            if ($dryRun) {
                $deletedCount++;

                continue;
            }

            try {
                // Hapus file fisik dari semua disk yang mungkin menyimpannya
                foreach (['local', 'public'] as $disk) {
                    if ($document->file_path && Storage::disk($disk)->exists($document->file_path)) {
                        Storage::disk($disk)->delete($document->file_path);
                        $this->line("    File dihapus dari disk '{$disk}': {$document->file_path}");
                        break;
                    }
                }

                // Hapus record secara permanen (force delete)
                $document->forceDelete();
                $deletedCount++;
            } catch (\Throwable $e) {
                $this->error("    GAGAL menghapus dokumen #{$document->id}: {$e->getMessage()}");
                $errorCount++;
            }
        }

        $this->newLine();
        if ($dryRun) {
            $this->warn("[DRY-RUN] {$deletedCount} dokumen akan dihapus bila dijalankan tanpa --dry-run.");
        } else {
            $this->info("{$deletedCount} dokumen berhasil dihapus permanen.");
            if ($errorCount > 0) {
                $this->warn("{$errorCount} dokumen gagal dihapus — periksa log untuk detail.");
            }
        }

        return $errorCount > 0 ? self::FAILURE : self::SUCCESS;
    }
}

