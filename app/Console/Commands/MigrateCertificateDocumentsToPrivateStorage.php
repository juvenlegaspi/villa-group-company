<?php

namespace App\Console\Commands;

use App\Models\VesselCertificate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class MigrateCertificateDocumentsToPrivateStorage extends Command
{
    protected $signature = 'certificates:migrate-private {--keep-public : Keep verified public source files}';

    protected $description = 'Move legacy public certificate documents into private application storage';

    public function handle(): int
    {
        $sourceDirectory = public_path('uploads/certificates');

        if (! File::isDirectory($sourceDirectory)) {
            $this->info('No legacy public certificate directory exists.');

            return self::SUCCESS;
        }

        $migrated = 0;
        $updatedRecords = 0;

        foreach (File::files($sourceDirectory) as $file) {
            $filename = $file->getFilename();
            $privatePath = 'certificates/legacy/'.$filename;
            $stream = fopen($file->getRealPath(), 'rb');

            if ($stream === false) {
                $this->error("Unable to read {$filename}.");

                return self::FAILURE;
            }

            try {
                Storage::disk('local')->put($privatePath, $stream);
            } finally {
                fclose($stream);
            }

            $destination = Storage::disk('local')->path($privatePath);
            if (! is_file($destination) || hash_file('sha256', $file->getRealPath()) !== hash_file('sha256', $destination)) {
                $this->error("Checksum verification failed for {$filename}.");

                return self::FAILURE;
            }

            $updatedRecords += VesselCertificate::query()
                ->where('document', $filename)
                ->update(['document' => $privatePath]);

            if (! $this->option('keep-public')) {
                File::delete($file->getRealPath());
            }

            $migrated++;
        }

        $this->info("Private files verified: {$migrated}; certificate records updated: {$updatedRecords}.");

        return self::SUCCESS;
    }
}
