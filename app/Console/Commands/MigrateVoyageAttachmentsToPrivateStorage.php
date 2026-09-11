<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class MigrateVoyageAttachmentsToPrivateStorage extends Command
{
    protected $signature = 'voyages:migrate-private-attachments {--keep-public}';

    protected $description = 'Move legacy public voyage edit attachments into private application storage';

    public function handle(): int
    {
        $sourceDirectory = storage_path('app/public/voyage_activity_edits');

        if (! File::isDirectory($sourceDirectory)) {
            $this->info('No legacy public voyage attachment directory exists.');

            return self::SUCCESS;
        }

        $migrated = 0;
        foreach (File::files($sourceDirectory) as $file) {
            $privatePath = 'voyage_activity_edits/'.$file->getFilename();
            $stream = fopen($file->getRealPath(), 'rb');

            if ($stream === false) {
                $this->error("Unable to read {$file->getFilename()}.");

                return self::FAILURE;
            }

            try {
                Storage::disk('local')->put($privatePath, $stream);
            } finally {
                fclose($stream);
            }

            $destination = Storage::disk('local')->path($privatePath);
            if (! is_file($destination) || hash_file('sha256', $file->getRealPath()) !== hash_file('sha256', $destination)) {
                $this->error("Checksum verification failed for {$file->getFilename()}.");

                return self::FAILURE;
            }

            if (! $this->option('keep-public')) {
                File::delete($file->getRealPath());
            }
            $migrated++;
        }

        $this->info("Private voyage attachments verified: {$migrated}.");

        return self::SUCCESS;
    }
}
