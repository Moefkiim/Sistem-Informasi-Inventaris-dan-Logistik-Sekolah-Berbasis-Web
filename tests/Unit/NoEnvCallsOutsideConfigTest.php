<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class NoEnvCallsOutsideConfigTest extends TestCase
{
    /**
     * Pastikan tidak ada pemanggilan env() di luar folder config/.
     */
    public function test_no_env_calls_outside_config_directory(): void
    {
        $basePath = dirname(__DIR__, 2);
        $directories = [
            $basePath.'/app',
            $basePath.'/routes',
            $basePath.'/database',
            $basePath.'/bootstrap',
        ];

        $violations = [];

        foreach ($directories as $dir) {
            if (! is_dir($dir)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));

            /** @var SplFileInfo $file */
            foreach ($iterator as $file) {
                if (! $file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }

                $content = file_get_contents($file->getRealPath());
                $tokens = token_get_all($content);

                for ($i = 0; $i < count($tokens); $i++) {
                    if (is_array($tokens[$i]) && $tokens[$i][0] === T_STRING && strtolower($tokens[$i][1]) === 'env') {
                        // Cari token berikutnya yang bukan whitespace
                        $j = $i + 1;
                        while ($j < count($tokens) && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
                            $j++;
                        }
                        if ($j < count($tokens) && $tokens[$j] === '(') {
                            $relPath = str_replace('\\', '/', substr($file->getRealPath(), strlen($basePath) + 1));
                            $violations[] = "{$relPath}:{$tokens[$i][2]}";
                        }
                    }
                }
            }
        }

        $this->assertEmpty(
            $violations,
            "Ditemukan pemanggilan env() di luar config/: \n".implode("\n", $violations)
        );
    }
}
