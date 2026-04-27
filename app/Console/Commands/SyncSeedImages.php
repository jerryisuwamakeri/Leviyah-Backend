<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class SyncSeedImages extends Command
{
    protected $signature   = 'storage:sync-seeds';
    protected $description = 'Upload seed product images from public disk to the configured storage (S3)';

    public function handle(): int
    {
        $files = glob(storage_path('app/public/products/seeds/*.jpg'));

        if (empty($files)) {
            $this->error('No seed images found in storage/app/public/products/seeds/');
            return 1;
        }

        $disk = Storage::disk(config('filesystems.default') === 'local' ? 'public' : config('filesystems.default'));

        foreach ($files as $localPath) {
            $filename    = basename($localPath);
            $storagePath = 'products/seeds/' . $filename;

            $stream = fopen($localPath, 'rb');
            $disk->put($storagePath, $stream, 'public');
            if (is_resource($stream)) fclose($stream);

            $this->line("Uploaded: {$storagePath}");
        }

        $this->info('All seed images synced.');
        return 0;
    }
}
