<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Verifies that the `downloads` disk is shared and writable across
 * containers. Run it in BOTH the app and the worker container:
 *
 *   docker exec <app-container>    php artisan app:storage-doctor
 *   docker exec <worker-container> php artisan app:storage-doctor
 *
 * Each run drops a sentinel file tagged with the hostname and lists every
 * sentinel it can see. If the app run shows the worker's sentinel (and vice
 * versa), the volume is shared. If each only sees its own, the worker and
 * web are NOT on the same volume — which is why downloaded files appear
 * missing at serve time.
 */
class StorageDoctorCommand extends Command
{
    protected $signature = 'app:storage-doctor';

    protected $description = 'Diagnose whether the downloads storage volume is shared between containers';

    public function handle(): int
    {
        $disk = Storage::disk('downloads');
        $host = gethostname();
        $absRoot = $disk->path('');

        $this->info('=== Storage Doctor ===');
        $this->line('Hostname:        '.$host);
        $this->line('Downloads root:  '.$absRoot);
        $this->line('Root exists:     '.(is_dir($absRoot) ? 'yes' : 'NO'));
        $this->line('Root writable:   '.(is_writable($absRoot) ? 'yes' : 'NO'));

        // Drop a sentinel tagged with this host + timestamp.
        $sentinel = '_doctor/'.$host.'-'.now()->format('Ymd_His').'.txt';
        try {
            $disk->put($sentinel, "written by {$host} at ".now()->toIso8601String());
            $this->line('Wrote sentinel:  '.$sentinel);
        } catch (\Throwable $e) {
            $this->error('Failed to write sentinel: '.$e->getMessage());
        }

        // List every sentinel currently visible from THIS container.
        $this->newLine();
        $this->info('Sentinels visible from this container:');
        $files = collect($disk->files('_doctor'));
        if ($files->isEmpty()) {
            $this->line('  (none)');
        } else {
            foreach ($files as $f) {
                $this->line('  - '.basename($f).'  ('.$disk->size($f).' bytes)');
            }
        }

        $this->newLine();
        $this->comment('Run this in BOTH app and worker. If each sees the other\'s sentinel, the volume is shared. If not, it is NOT shared.');

        return self::SUCCESS;
    }
}
