<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class ConfigCacheSafetyTest extends TestCase
{
    public function test_runtime_php_does_not_read_env_directly(): void
    {
        $root = dirname(__DIR__, 2);
        foreach (['app', 'routes'] as $directory) {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$directory));
            foreach ($files as $file) {
                if (!$file->isFile() || $file->getExtension() !== 'php') continue;
                $tokens = token_get_all(file_get_contents($file->getPathname()));
                foreach ($tokens as $i => $token) {
                    if (!is_array($token) || !in_array($token[0], [T_STRING, T_NAME_FULLY_QUALIFIED], true) || strtolower(ltrim($token[1], '\\')) !== 'env') continue;
                    $next = $i + 1;
                    while (isset($tokens[$next]) && is_array($tokens[$next]) && in_array($tokens[$next][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) $next++;
                    $this->assertNotSame('(', $tokens[$next] ?? null, $file->getPathname().':'.$token[2].' reads env() outside config.');
                }
            }
        }
        $this->assertTrue(true);
    }
}
