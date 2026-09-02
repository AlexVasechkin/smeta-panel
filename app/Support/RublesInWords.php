<?php

namespace App\Support;

/**
 * Сумма прописью на русском языке (рубли и копейки).
 */
class RublesInWords
{
    private const ONES = [
        '', 'один', 'два', 'три', 'четыре', 'пять', 'шесть', 'семь', 'восемь', 'девять',
        'десять', 'одиннадцать', 'двенадцать', 'тринадцать', 'четырнадцать', 'пятнадцать',
        'шестнадцать', 'семнадцать', 'восемнадцать', 'девятнадцать',
    ];

    private const TENS = ['', '', 'двадцать', 'тридцать', 'сорок', 'пятьдесят', 'шестьдесят', 'семьдесят', 'восемьдесят', 'девяносто'];

    private const HUNDREDS = ['', 'сто', 'двести', 'триста', 'четыреста', 'пятьсот', 'шестьсот', 'семьсот', 'восемьсот', 'девятьсот'];

    /**
     * Например: 135268.50 => «сто тридцать пять тысяч двести шестьдесят восемь рублей 50 копеек».
     */
    public static function format(float $amount): string
    {
        $amount = round(abs($amount), 2);
        $rubles = (int) floor($amount);
        $kopecks = (int) round(($amount - $rubles) * 100);

        $words = self::intToWords($rubles, feminine: false);

        return sprintf(
            '%s %s %02d %s',
            $words,
            self::plural($rubles, 'рубль', 'рубля', 'рублей'),
            $kopecks,
            self::plural($kopecks, 'копейка', 'копейки', 'копеек'),
        );
    }

    /**
     * Только целая часть суммы прописью, с заглавной буквы (без «рублей»/«копеек»).
     * Например: 340000 => «Триста сорок тысяч».
     */
    public static function words(float $amount): string
    {
        $words = self::intToWords((int) round(abs($amount)), feminine: false);

        return mb_strtoupper(mb_substr($words, 0, 1, 'UTF-8'), 'UTF-8').mb_substr($words, 1, null, 'UTF-8');
    }

    private static function intToWords(int $number, bool $feminine): string
    {
        if ($number === 0) {
            return 'ноль';
        }

        // [делитель, форма ед., форма 2-4, форма мн., женский род?]
        $scales = [
            [1_000_000_000, 'миллиард', 'миллиарда', 'миллиардов', false],
            [1_000_000, 'миллион', 'миллиона', 'миллионов', false],
            [1_000, 'тысяча', 'тысячи', 'тысяч', true],
            [1, '', '', '', $feminine],
        ];

        $parts = [];

        foreach ($scales as [$base, $one, $few, $many, $isFeminine]) {
            if ($number < $base) {
                continue;
            }

            $count = intdiv($number, $base);
            $number %= $base;

            $triplet = self::tripletToWords($count, $isFeminine);

            if ($base > 1) {
                $triplet .= ' '.self::plural($count, $one, $few, $many);
            }

            $parts[] = trim($triplet);
        }

        return implode(' ', array_filter($parts));
    }

    private static function tripletToWords(int $number, bool $feminine): string
    {
        $words = [];

        $words[] = self::HUNDREDS[intdiv($number, 100)];
        $number %= 100;

        if ($number >= 20) {
            $words[] = self::TENS[intdiv($number, 10)];
            $number %= 10;
        }

        if ($number > 0 && $number < 20) {
            if ($feminine && $number === 1) {
                $words[] = 'одна';
            } elseif ($feminine && $number === 2) {
                $words[] = 'две';
            } else {
                $words[] = self::ONES[$number];
            }
        }

        return trim(implode(' ', array_filter($words)));
    }

    private static function plural(int $number, string $one, string $few, string $many): string
    {
        $mod100 = abs($number) % 100;
        $mod10 = $mod100 % 10;

        if ($mod100 > 10 && $mod100 < 20) {
            return $many;
        }
        if ($mod10 === 1) {
            return $one;
        }
        if ($mod10 >= 2 && $mod10 <= 4) {
            return $few;
        }

        return $many;
    }
}
