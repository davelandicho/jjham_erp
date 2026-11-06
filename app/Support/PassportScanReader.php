<?php

namespace App\Support;

use Illuminate\Support\Facades\Process;

class PassportScanReader
{
    // Returns the parsed MRZ array, or null if no MRZ could be found.
    public static function read(string $imagePath): ?array
    {
        $result = Process::timeout(30)->run([
            config('services.tesseract.path'),
            $imagePath,
            'stdout',
            '--psm', '6',
            '-c', 'tessedit_char_whitelist=ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789<',
        ]);

        if ($result->failed()) {
            return null;
        }

        // Keep only long lines that contain '<' (the MRZ), with spaces removed.
        $lines = collect(preg_split('/\R/', $result->output()))
            ->map(fn (string $line) => preg_replace('/\s+/', '', $line))
            ->filter(fn (string $line) => strlen($line) >= 30 && str_contains($line, '<'))
            ->values();

        // Line 1 = "P" + type + 3-letter country, e.g. "P<PHL".
        $index = $lines->search(fn (string $line) => preg_match('/^P[A-Z<][A-Z<]{3}/', $line) === 1);

        if ($index === false || ! isset($lines[$index + 1])) {
            return null;
        }

        return PassportMrz::parse($lines[$index], $lines[$index + 1]);
    }
}