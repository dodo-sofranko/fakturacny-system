<?php

/**
 * PAY by Square QR kód generátor (Slovak banking standard)
 * Formát podľa oficiálnej PAY by Square špecifikácie SBA.
 */
class PayBySquare
{
    public static function generate(
        string $iban,
        float  $amount,
        string $currency = 'EUR',
        string $variabilnySymbol = '',
        string $dueDate = '',
        string $cisloFaktury = '',
        string $recipient = ''
    ): string {
        // IBAN bez medzier
        $iban = str_replace(' ', '', $iban);

        // Ak variabilný symbol nie je zadaný, použijeme číslo faktúry (len číslice)
        if (empty($variabilnySymbol) && $cisloFaktury) {
            $variabilnySymbol = preg_replace('/\D/', '', $cisloFaktury);
        }

        // Formát dátumu YYYYMMDD
        $dueDateFormatted = $dueDate ? date('Ymd', strtotime($dueDate)) : '';

        $message = $cisloFaktury ? 'Úhrada faktúry: ' . $cisloFaktury : '';
        $recipientAscii = self::removeDiacritics($recipient);

        // PAY by Square payload — správne poradie polí podľa SBA špecifikácie
        $payload = implode("\t", [
            '',                                          // InvoiceID (prázdne)
            '1',                                         // PaymentsCount
            '1',                                         // IsRegularPayment
            number_format($amount, 2, '.', ''),          // Amount
            $currency,                                   // Currency
            $dueDateFormatted,                           // DueDate
            $variabilnySymbol,                           // VariableSymbol
            '',                                          // ConstantSymbol
            '',                                          // SpecificSymbol
            '',                                          // OriginatorRefInfo
            $message,                                    // PaymentNote
            '1',                                         // BankAccountsCount
            $iban,                                       // IBAN
            '',                                          // BIC
            '0',                                         // StandingOrderExt
            '0',                                         // DirectDebitExt
            $recipientAscii,                             // BeneficiaryName (field 16)
        ]);

        // CRC32b — reverzovaný (little-endian)
        $withCrc = strrev(hash('crc32b', $payload, true)) . $payload;

        // LZMA1 kompresia
        $compressed = self::lzmaCompress($withCrc);
        if ($compressed === null) {
            // Fallback: EPC/SEPA QR
            $recipientAscii = self::removeDiacritics($recipient);
            return "BCD\n001\n1\nSCT\n\n{$recipientAscii}\n{$iban}\nEUR{$amount}\n\n{$variabilnySymbol}\n{$message}";
        }

        // Header: 2 bajty verzia (0x00 0x00) + 2 bajty dĺžka pôvodného payloadu (little-endian)
        $header = "\x00\x00" . pack('v', strlen($withCrc));
        $binary = bin2hex($header . $compressed);

        // Konverzia hex → binárne bity
        $bits = '';
        for ($i = 0; $i < strlen($binary); $i++) {
            $bits .= str_pad(base_convert($binary[$i], 16, 2), 4, '0', STR_PAD_LEFT);
        }

        // Pad na násobok 5
        $rest = strlen($bits) % 5;
        if ($rest !== 0) {
            $bits .= str_repeat('0', 5 - $rest);
        }

        // Base32 kódovanie (PAY by Square abeceda)
        $alphabet = '0123456789ABCDEFGHIJKLMNOPQRSTUV';
        $result = '';
        $groups = strlen($bits) / 5;
        for ($i = 0; $i < $groups; $i++) {
            $result .= $alphabet[bindec(substr($bits, $i * 5, 5))];
        }

        return $result;
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
        $xzPaths = ['/opt/homebrew/bin/xz', '/usr/bin/xz', '/usr/local/bin/xz'];
        $xzBin = null;
        foreach ($xzPaths as $path) {
            if (file_exists($path)) {
                $xzBin = $path;
                break;
            }
        }
        if (!$xzBin) return null;

        $tmpIn = tempnam(sys_get_temp_dir(), 'pbsq_');
        file_put_contents($tmpIn, $data);

        $cmd = sprintf(
            '%s --format=raw --lzma1=lc=3,lp=0,pb=2,dict=128KiB --stdout %s 2>/dev/null',
            escapeshellarg($xzBin),
            escapeshellarg($tmpIn)
        );
        $compressed = shell_exec($cmd);
        unlink($tmpIn);

        return ($compressed !== null && $compressed !== '') ? $compressed : null;
    }
}
