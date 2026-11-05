<?php
// ------------------------------------------------------------
// GCash / InstaPay (EMVCo) QR payload builder
//
// The school's static GCash QR encodes an EMVCo string of TLV
// (tag-length-value) fields — recipient account, merchant name,
// country, etc. — but no amount. This file injects EMVCo tag 54
// (Transaction Amount) into that payload and recomputes the
// CRC-16 checksum (tag 63 04) that GCash validates on scan, so
// the amount is pre-filled when a student scans the QR.
// ------------------------------------------------------------

// EMVCo TLV: 2-digit tag id + 2-digit length + value
function emv_tlv($id, $value) {
    return sprintf('%02d%02d%s', $id, strlen($value), $value);
}

// Parse a top-level EMVCo payload into tag => value
function emv_parse($s) {
    $tags = [];
    $i = 0; $n = strlen($s);
    while ($i + 4 <= $n) {
        $id  = substr($s, $i, 2);
        $len = (int)substr($s, $i + 2, 2);
        $tags[$id] = substr($s, $i + 4, $len);
        $i += 4 + $len;
    }
    return $tags;
}

// CRC-16/CCITT-FALSE (poly 0x1021, init 0xFFFF) — EMVCo "63 04" checksum
function emv_crc16($data) {
    $crc = 0xFFFF;
    for ($i = 0, $n = strlen($data); $i < $n; $i++) {
        $crc ^= ord($data[$i]) << 8;
        for ($b = 0; $b < 8; $b++) {
            $crc = ($crc & 0x8000) ? (($crc << 1) ^ 0x1021) : ($crc << 1);
            $crc &= 0xFFFF;
        }
    }
    return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
}

// Static QR payload with the given amount pre-filled.
// Returns null when no base payload has been configured yet.
function gcash_emv_payload($amount) {
    if (!defined('GCASH_QR_PAYLOAD') || GCASH_QR_PAYLOAD === '') return null;

    // Drop the old checksum tail, then overwrite tag 54 with our amount
    $base = preg_replace('/6304[0-9A-Fa-f]{4}\s*$/', '', GCASH_QR_PAYLOAD);
    $tags = emv_parse($base);
    if (empty($tags)) return null;

    // An amount-bearing QR must be marked dynamic — wallets reject 01=11 + tag 54
    $tags['01'] = '12';
    $tags['54'] = number_format((float)$amount, 2, '.', '');
    ksort($tags);

    $out = '';
    foreach ($tags as $id => $val) $out .= emv_tlv($id, $val);
    return $out . '6304' . emv_crc16($out . '6304');
}
