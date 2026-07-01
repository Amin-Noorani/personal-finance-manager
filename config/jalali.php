<?php
// Jalali (Persian/Solar Hijri) calendar converter - pure PHP

function gregorianToJalali($gy, $gm, $gd) {
    $g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
    $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
    $days = 355666 + (365 * $gy) + intval(($gy2 + 3) / 4) - intval(($gy2 + 99) / 100) + intval(($gy2 + 399) / 400) + $gd + $g_d_m[$gm - 1];
    $jy = -1595 + (33 * intval($days / 12053));
    $days %= 12053;
    $jy += 4 * intval($days / 1461);
    $days %= 1461;
    $jy += intval(($days - 1) / 365);
    $days = ($days - 1) % 365;
    if ($days < 0) {
        $jy += 1;
        $days += 365;
    }
    $jm = ($days < 186) ? 1 + intval($days / 31) : 7 + intval(($days - 186) / 30);
    $jd = ($days < 186) ? ($days % 31) + 1 : ($days - 186) % 30 + 1;
    return [$jy, $jm, $jd];
}

function jalaliToGregorian($jy, $jm, $jd) {
    // Exact port of JDF's jalali_to_gregorian (jalalidate/jdf)
    if ($jy > 979) {
        $gy = 1600;
        $jy -= 979;
    } else {
        $gy = 621;
    }
    $days = (365 * $jy) + (intval($jy / 33) * 8) + intval(($jy % 33 + 3) / 4) + 78 + $jd + (($jm < 7) ? ($jm - 1) * 31 : (($jm - 7) * 30) + 186);
    $gy += 400 * intval($days / 146097);
    $days %= 146097;
    if ($days > 36524) {
        $gy += 100 * intval(--$days / 36524);
        $days %= 36524;
        if ($days >= 365) $days++;
    }
    $gy += 4 * intval($days / 1461);
    $days %= 1461;
    $gy += intval(($days - 1) / 365);
    if ($days > 365) $days = ($days - 1) % 365;
    $gd = $days + 1;
    $monthDays = [0, 31, (($gy % 4 == 0 && $gy % 100 != 0) || $gy % 400 == 0) ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
    $gm = 0;
    foreach ($monthDays as $gm => $v) {
        if ($gd <= $v) break;
        $gd -= $v;
    }
    return [$gy, $gm, $gd];
}

function formatJalali($gregorianDate) {
    if (empty($gregorianDate)) return '';
    $parts = explode('-', $gregorianDate);
    if (count($parts) !== 3) return $gregorianDate;
    list($jy, $jm, $jd) = gregorianToJalali(intval($parts[0]), intval($parts[1]), intval($parts[2]));
    return str_pad($jy, 4, '0', STR_PAD_LEFT) . '/' . str_pad($jm, 2, '0', STR_PAD_LEFT) . '/' . str_pad($jd, 2, '0', STR_PAD_LEFT);
}

function toPersianDigits($str) {
    $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
    $latin = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
    return str_replace($latin, $persian, $str);
}

function jalaliToday() {
    list($y, $m, $d) = gregorianToJalali(date('Y'), date('m'), date('d'));
    return str_pad($y, 4, '0', STR_PAD_LEFT) . '-' . str_pad($m, 2, '0', STR_PAD_LEFT) . '-' . str_pad($d, 2, '0', STR_PAD_LEFT);
}

function jalaliFirstOfMonth() {
    list($y, $m, $d) = gregorianToJalali(date('Y'), date('m'), 1);
    return str_pad($y, 4, '0', STR_PAD_LEFT) . '-' . str_pad($m, 2, '0', STR_PAD_LEFT) . '-' . str_pad($d, 2, '0', STR_PAD_LEFT);
}

function jalaliLastOfMonth() {
    list($y, $m, $d) = gregorianToJalali(date('Y'), date('m'), date('t'));
    return str_pad($y, 4, '0', STR_PAD_LEFT) . '-' . str_pad($m, 2, '0', STR_PAD_LEFT) . '-' . str_pad($d, 2, '0', STR_PAD_LEFT);
}

function currentJalaliMonthGregorian() {
    return date('Y-m');
}

function formatNumber($number) {
    return toPersianDigits(number_format($number, 0));
}
