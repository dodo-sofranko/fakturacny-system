<?php

/**
 * PAY by Square QR kód generátor (Slovak banking standard)
 * Generuje string pre QR kód podľa PAY by Square štandardu.
 */
class PayBySquare
{
    /**
     * Vygeneruje PAY by Square string pre QR kód.
     * Formát: https://www.sbaonline.sk/projekt/pay-by-square/
     */
    public static function generate(
        string $iban,
        float  $amount,
        string $currency = 'EUR',
        string $variabilnySymbol = '',
        string $dueDate = '',
        string $cisloFaktury = '',
        string $recipient = ''
    ): string {
        // Zostavíme payload podľa PAY by Square spec
        // \t je oddeľovač polí, \n je oddeľovač záznamov
        $invoiceId = '';
        $constantSymbol = '';
        $specificSymbol = '';
        $recipientAscii = self::removeDiacritics($recipient);

        // Formát dátumu YYYYMMDD
        $dueDateFormatted = '';
        if ($dueDate) {
            $dueDateFormatted = date('Ymd', strtotime($dueDate));
        }

        // PAY by Square payload (tab-separated)
        // Verzia, platba, suma, mena, dátum, typ platby, VS, SS, KS, referencia platby, IBAN, BIC, správa
        $payload = implode("\t", [
            '0000000001',           // InvoiceID (prázdne = generuje sa)
            '1',                    // IsRegularPayment
            number_format($amount, 2, '.', ''),
            $currency,
            $dueDateFormatted,
            '0',                    // PaymentType: 0 = Platba
            $variabilnySymbol,      // VariableSymbol
            $constantSymbol,        // ConstantSymbol
            $specificSymbol,        // SpecificSymbol
            '',                     // OriginatorRefInfo
            $cisloFaktury,          // PaymentNote (číslo faktúry)
            '1',                    // BankAccountsCount
            $iban,                  // IBAN
            '',                     // BIC (prázdne = bank si doplní)
        ]);

        // Komprimujeme pomocou xz (LZMA) — PAY by Square vyžaduje LZMA2
        $compressed = self::lzmaCompress($payload);
        if ($compressed === null) {
            // Fallback: jednoduchý IBAN QR bez kompresie (nie PAY by Square)
            return "BCD\n001\n1\nSCT\n\n{$recipientAscii}\n{$iban}\nEUR{$amount}\n\n{$variabilnySymbol}\n{$cisloFaktury}";
        }

        // Kontrolný CRC8
        $crc = self::crc8($payload);
        $withCrc = chr($crc) . $compressed;

        return self::base32hex($withCrc);
    }

    private static function removeDiacritics(string $s): string
    {
        $from = ['á','ä','č','ď','é','í','ľ','ĺ','ň','ó','ô','ŕ','š','ť','ú','ý','ž',
                 'Á','Ä','Č','Ď','É','Í','Ľ','Ĺ','Ň','Ó','Ô','Ŕ','Š','Ť','Ú','Ý','Ž'];
        $to   = ['a','a','c','d','e','i','l','l','n','o','o','r','s','t','u','y','z',
                 'A','A','C','D','E','I','L','L','N','O','O','R','S','T','U','Y','Z'];
        return str_replace($from, $to, $s);
    }

    private static function lzmaCompress(string $data): ?string
    {
        // Pokúsime sa použiť xz binary
        $tmpIn = tempnam(sys_get_temp_dir(), 'pbsq_');
        $tmpOut = $tmpIn . '.xz';
        file_put_contents($tmpIn, $data);

        $cmd = sprintf(
            'xz --format=raw --lzma2=lc=3,lp=0,pb=2,dict=4096 --stdout %s 2>/dev/null',
            escapeshellarg($tmpIn)
        );
        $compressed = shell_exec($cmd);
        unlink($tmpIn);
        if (file_exists($tmpOut)) unlink($tmpOut);

        return $compressed ?: null;
    }

    private static function crc8(string $data): int
    {
        $crc = 0;
        for ($i = 0; $i < strlen($data); $i++) {
            $crc ^= ord($data[$i]);
            for ($j = 0; $j < 8; $j++) {
                if ($crc & 0x80) {
                    $crc = (($crc << 1) ^ 0x07) & 0xFF;
                } else {
                    $crc = ($crc << 1) & 0xFF;
                }
            }
        }
        return $crc;
    }

    private static function base32hex(string $data): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $result = '';
        $buffer = 0;
        $bits = 0;

        for ($i = 0; $i < strlen($data); $i++) {
            $buffer = ($buffer << 8) | ord($data[$i]);
            $bits += 8;
            while ($bits >= 5) {
                $bits -= 5;
                $result .= $alphabet[($buffer >> $bits) & 0x1F];
            }
        }

        if ($bits > 0) {
            $result .= $alphabet[($buffer << (5 - $bits)) & 0x1F];
        }

        // Padding
        while (strlen($result) % 8 !== 0) {
            $result .= '=';
        }

        return $result;
    }
}
