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

    /** LZMA1 raw (lc=3, lp=0, pb=2): cez xz, ak sa dá spustiť, inak čistým PHP. */
    private static function lzmaCompress(string $data): ?string
    {
        $viaXz = self::lzmaViaXz($data);
        return $viaXz ?? self::lzmaPhp($data);
    }

    private static function lzmaViaXz(string $data): ?string
    {
        if (!function_exists('shell_exec')) return null;
        $xzBin = null;
        foreach (['/opt/homebrew/bin/xz', '/usr/bin/xz', '/usr/local/bin/xz'] as $path) {
            if (@is_executable($path)) { $xzBin = $path; break; }
        }
        if (!$xzBin) return null;

        $tmpIn = tempnam(sys_get_temp_dir(), 'pbsq_');
        file_put_contents($tmpIn, $data);
        $cmd = sprintf(
            '%s --format=raw --lzma1=lc=3,lp=0,pb=2,dict=128KiB --stdout %s 2>/dev/null',
            escapeshellarg($xzBin),
            escapeshellarg($tmpIn)
        );
        $compressed = @shell_exec($cmd);
        @unlink($tmpIn);

        // shell_exec vracia null aj false (napr. zakázané spúšťanie na hostingu)
        return (is_string($compressed) && $compressed !== '') ? $compressed : null;
    }

    // ---- Čisté PHP LZMA1 (len literály + značka konca) — platný stream pre každý LZMA dekodér ----

    private static string $out = '';
    private static int $low = 0, $range = 0, $cache = 0, $cacheSize = 0;

    private static function lzmaPhp(string $data): string
    {
        self::$out = '';
        self::$low = 0;
        self::$range = 0xFFFFFFFF;
        self::$cache = 0;
        self::$cacheSize = 1;

        $isMatch  = array_fill(0, 12 * 16, 1024);
        $isRep    = array_fill(0, 12, 1024);
        $literal  = array_fill(0, 0x300 * 8, 1024);   // lc=3, lp=0
        $lenChoice = 1024;
        $lenLow   = array_fill(0, 4 * 8, 1024);       // pb=2 → 4 posStates × 3 bity
        $posSlot  = array_fill(0, 64, 1024);          // lenToPosState(2) = 0
        $align    = array_fill(0, 16, 1024);

        $state = 0;
        $prev = 0;
        $n = strlen($data);
        for ($pos = 0; $pos < $n; $pos++) {
            $byte = ord($data[$pos]);
            self::bit($isMatch[$state * 16 + ($pos & 3)], 0);
            $base = 0x300 * ($prev >> 5);
            $sym = 1;
            for ($i = 7; $i >= 0; $i--) {
                $b = ($byte >> $i) & 1;
                self::bit($literal[$base + $sym], $b);
                $sym = ($sym << 1) | $b;
            }
            $state = $state < 4 ? 0 : ($state < 10 ? $state - 3 : $state - 6);
            $prev = $byte;
        }

        // Značka konca (match so vzdialenosťou 0xFFFFFFFF)
        $posState = $n & 3;
        self::bit($isMatch[$state * 16 + $posState], 1);
        self::bit($isRep[$state], 0);
        self::bit($lenChoice, 0);                                   // dĺžka 2 → low coder, symbol 0
        $m = 1;
        for ($i = 2; $i >= 0; $i--) { self::bit($lenLow[$posState * 8 + $m], 0); $m <<= 1; }
        $m = 1;
        for ($i = 5; $i >= 0; $i--) { self::bit($posSlot[$m], 1); $m = ($m << 1) | 1; }   // posSlot 63
        for ($i = 25; $i >= 0; $i--) {                               // 26 priamych bitov = 1
            self::$range >>= 1;
            self::$low += self::$range;
            if (self::$range < 0x1000000) { self::$range = (self::$range << 8) & 0xFFFFFFFF; self::shiftLow(); }
        }
        $m = 1;
        for ($i = 0; $i < 4; $i++) { self::bit($align[$m], 1); $m = ($m << 1) | 1; }        // align 15

        for ($i = 0; $i < 5; $i++) self::shiftLow();
        return self::$out;
    }

    private static function bit(int &$prob, int $bit): void
    {
        $bound = (self::$range >> 11) * $prob;
        if ($bit === 0) {
            self::$range = $bound;
            $prob += (2048 - $prob) >> 5;
        } else {
            self::$low += $bound;
            self::$range -= $bound;
            $prob -= $prob >> 5;
        }
        while (self::$range < 0x1000000) {
            self::$range = (self::$range << 8) & 0xFFFFFFFF;
            self::shiftLow();
        }
    }

    private static function shiftLow(): void
    {
        $low32 = self::$low & 0xFFFFFFFF;
        $carry = self::$low >> 32;
        if ($low32 < 0xFF000000 || $carry !== 0) {
            $temp = self::$cache;
            do {
                self::$out .= chr(($temp + $carry) & 0xFF);
                $temp = 0xFF;
            } while (--self::$cacheSize !== 0);
            self::$cache = ($low32 >> 24) & 0xFF;
        }
        self::$cacheSize++;
        self::$low = ($low32 << 8) & 0xFFFFFFFF;
    }
}
