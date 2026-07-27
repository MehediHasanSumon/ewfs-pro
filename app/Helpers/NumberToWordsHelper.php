<?php

namespace App\Helpers;

class NumberToWordsHelper
{
    public static function convert($num)
    {
        $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine'];
        $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
        $teens = ['Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];

        if ($num == 0) return 'Zero';

        $convertLessThanThousand = function ($n) use ($ones, $tens, $teens, &$convertLessThanThousand) {
            if ($n == 0) return '';
            if ($n < 10) return $ones[$n];
            if ($n < 20) return $teens[$n - 10];
            if ($n < 100) return $tens[floor($n / 10)] . ($n % 10 != 0 ? ' ' . $ones[$n % 10] : '');
            return $ones[floor($n / 100)] . ' Hundred' . ($n % 100 != 0 ? ' ' . $convertLessThanThousand($n % 100) : '');
        };

        $billion = floor($num / 1000000000);
        $million = floor(($num % 1000000000) / 1000000);
        $thousand = floor(($num % 1000000) / 1000);
        $remainder = floor($num % 1000);

        $result = '';
        if ($billion > 0) $result .= $convertLessThanThousand($billion) . ' Billion ';
        if ($million > 0) $result .= $convertLessThanThousand($million) . ' Million ';
        if ($thousand > 0) $result .= $convertLessThanThousand($thousand) . ' Thousand ';
        if ($remainder > 0) $result .= $convertLessThanThousand($remainder);

        return trim($result);
    }
}