<?php

namespace App\Support;

class PassportMrz
{
    public static function parse(string $line1, string $line2): ?array
    {
        $line1 = self::clean($line1);
        $line2 = self::clean($line2);

        if ($line1[0] !== 'P') {
            return null; 
        }

        $number = substr($line2, 0, 9);
        $birth = self::digits(substr($line2, 13, 6));
        $expiry = self::digits(substr($line2, 21, 6));

        [$surname, $given] = array_pad(explode('<<', substr($line1, 5), 2), 2, '');

        $checks = [
            'passport_number' => self::check($number) === self::digits($line2[9]),
            'birthdate' => self::check($birth) === self::digits($line2[19]),
            'expiry' => self::check($expiry) === self::digits($line2[27]),
        ];

        return [
            'passport_number' => rtrim($number, '<'),
            'last_name' => self::name($surname),
            'first_name' => self::name($given),
            'nationality_code' => substr($line2, 10, 3),
            'birthdate' => self::date($birth, future: false),
            'sex' => match ($line2[20]) {
                'M' => 'Male',
                'F' => 'Female',
                default => null,
            },
            'passport_expiry_date' => self::date($expiry, future: true),
            'checks' => $checks,
            'valid' => ! in_array(false, $checks, true),
        ];
    }
    
    public static function check(string $value): string
    {
        $weights = [7, 3, 1];
        $sum = 0;

        foreach (str_split($value) as $i => $char) {
            $number = match (true) {
                ctype_digit($char) => (int) $char,
                ctype_upper($char) => ord($char) - 55,
                default => 0,
            };
            $sum += $number * $weights[$i % 3];
        }

        return (string) ($sum % 10);
    }

    private static function clean(string $line): string
    {
        $line = strtoupper(preg_replace('/\s+/', '', $line));

        return str_pad(substr($line, 0, 44), 44, '<');
    }

    private static function digits(string $value): string
    {
        return strtr($value, [
            'O' => '0', 'Q' => '0', 'D' => '0', 'I' => '1', 'L' => '1',
            'Z' => '2', 'S' => '5', 'B' => '8', 'G' => '6',
        ]);
    }

    private static function name(string $value): string
    {
        $value = trim(preg_replace('/<+/', ' ', $value));

        return ucwords(strtolower($value));
    }

    private static function date(string $yymmdd, bool $future): ?string
    {
        if (! preg_match('/^\d{6}$/', $yymmdd)) {
            return null;
        }

        [$yy, $mm, $dd] = array_map('intval', str_split($yymmdd, 2));
        $century = ($future || $yy <= (int) date('y')) ? 2000 : 1900;

        if (! checkdate($mm, $dd, $century + $yy)) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $century + $yy, $mm, $dd);
    }    
}