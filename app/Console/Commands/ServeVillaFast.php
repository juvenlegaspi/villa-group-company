<?php

namespace App\Console\Commands;

use Illuminate\Foundation\Console\ServeCommand;

class ServeVillaFast extends ServeCommand
{
    protected $name = 'villa:serve';

    protected $description = 'Serve Villa locally with PHP bytecode caching and immediate code-change detection';

    protected function serverCommand(): array
    {
        $command = parent::serverCommand();
        $flags = [];
        if (! extension_loaded('Zend OPcache')) {
            $directory = rtrim((string) ini_get('extension_dir'), '/\\');
            $extension = $directory.DIRECTORY_SEPARATOR.(PHP_OS_FAMILY === 'Windows' ? 'php_opcache.dll' : 'opcache.so');
            if (! is_file($extension)) {
                $this->components->warn('OPcache extension unavailable; using the standard development server.');

                return $command;
            }
            $flags = ['-d', 'zend_extension='.$extension];
        }
        array_push($flags, '-d', 'opcache.enable=1', '-d', 'opcache.enable_cli=1', '-d', 'opcache.validate_timestamps=1', '-d', 'opcache.revalidate_freq=0', '-d', 'opcache.memory_consumption=64');
        array_splice($command, 1, 0, $flags);

        return $command;
    }
}
