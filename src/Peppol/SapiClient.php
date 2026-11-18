<?php

/**
 * Klient pre SAPI-SK – jednotné API slovenských digitálnych poštárov (Peppol).
 * Rovnaké endpointy má každý poštár, mení sa len základná URL a prístupové údaje v .env:
 *
 *   PEPPOL_API_URL=https://test.peppol.verteco.digital/sapi   (s /sapi alebo /sapi/v1 podľa poštára)
 *   PEPPOL_CLIENT_ID=…
 *   PEPPOL_CLIENT_SECRET=…
 */
class SapiClient
{
    private string $base;
    private string $tokenFile;

    public function __construct(
        private string $participantId,
        ?string $baseUrl = null,
        private ?string $clientId = null,
        private ?string $clientSecret = null,
    ) {
        $this->base = rtrim($baseUrl ?? (string)env('PEPPOL_API_URL', ''), '/');
        $this->clientId ??= (string)env('PEPPOL_CLIENT_ID', '');
        $this->clientSecret ??= (string)env('PEPPOL_CLIENT_SECRET', '');
        if ($this->base === '' || $this->clientId === '' || $this->clientSecret === '') {
            throw new RuntimeException('Peppol nie je nastavený (PEPPOL_API_URL, PEPPOL_CLIENT_ID, PEPPOL_CLIENT_SECRET v .env).');
        }
        $this->tokenFile = dirname(__DIR__, 2) . '/storage/peppol-token-' . md5($this->base . $this->clientId) . '.json';
    }

    public static function isConfigured(): bool
    {
        return env('PEPPOL_API_URL') && env('PEPPOL_CLIENT_ID') && env('PEPPOL_CLIENT_SECRET');
    }

    /** Odošle UBL faktúru. Vráti odpoveď poštára (providerDocumentId, status ACCEPTED|REJECTED, detail…). */
    public function sendInvoice(string $xml, string $documentId, string $receiver, string $idempotencyKey): array
    {
        return $this->request('POST', '/document/send', [
            'metadata' => [
                'documentId'            => $documentId,
                'documentTypeId'        => UblInvoice::DOCUMENT_TYPE_ID,
                'processId'             => UblInvoice::PROFILE_ID,
                'senderParticipantId'   => $this->participantId,
                'receiverParticipantId' => $receiver,
                'creationDateTime'      => gmdate('Y-m-d\TH:i:s\Z'),
            ],
            'payload'       => $xml,
            'payloadFormat' => 'XML',
        ], ['Idempotency-Key: ' . $idempotencyKey]);
    }

    /** Jedna stránka prijatých dokladov (status: RECEIVED | ACKNOWLEDGED). */
    public function received(?string $status = null, int $limit = 100, ?string $pageToken = null): array
    {
        // ePošťák stránkuje cez pageToken, Verteco cez cursor – pošleme oboje
        $q = http_build_query(array_filter(['status' => $status, 'limit' => $limit, 'pageToken' => $pageToken, 'cursor' => $pageToken]));
        return $this->request('GET', '/document/receive' . ($q ? "?$q" : ''));
    }

    /** Všetky čakajúce (nepotvrdené) prijaté doklady – zoznam metadát. */
    public function pendingDocuments(int $maxPages = 20): array
    {
        $all = [];
        $token = null;
        for ($i = 0; $i < $maxPages; $i++) {
            $res = $this->received('RECEIVED', 100, $token);
            array_push($all, ...($res['documents'] ?? []));
            $token = $res['nextPageToken'] ?? $res['nextCursor'] ?? $res['cursor'] ?? null;
            if (!$token || (isset($res['hasMore']) && !$res['hasMore'])) break;
        }
        return $all;
    }

    /** Detail prijatého dokladu; vráti ['metadata' => …, 'xml' => string]. */
    public function receivedDocument(string $documentId): array
    {
        $res = $this->request('GET', '/document/receive/' . rawurlencode($documentId));
        $xml = (string)($res['payload'] ?? '');
        if ($xml !== '' && ltrim($xml)[0] !== '<') { // niektorí poštári posielajú base64
            $dec = base64_decode($xml, true);
            if ($dec !== false && ltrim($dec)[0] === '<') $xml = $dec;
        }
        return ['metadata' => $res['metadata'] ?? [], 'xml' => $xml];
    }

    /** iso6523-actorid-upis::0245:123 → 0245:123 */
    public static function shortParticipantId(?string $id): string
    {
        return preg_replace('/^iso6523-actorid-upis::/', '', (string)$id);
    }

    public function acknowledge(string $documentId): array
    {
        return $this->request('POST', '/document/receive/' . rawurlencode($documentId) . '/acknowledge');
    }

    private function token(bool $fresh = false): string
    {
        if (!$fresh && is_file($this->tokenFile)) {
            $t = json_decode((string)file_get_contents($this->tokenFile), true);
            if (($t['expires'] ?? 0) > time() + 60) return $t['access_token'];
        }
        [$code, $body] = $this->http('POST', $this->base . '/auth/token', [
            'grant_type' => 'client_credentials', 'client_id' => $this->clientId, 'client_secret' => $this->clientSecret,
        ]);
        if ($code !== 200 || empty($body['access_token'])) {
            throw new RuntimeException('Prihlásenie k poštárovi zlyhalo (HTTP ' . $code . '): ' . self::errorText($body));
        }
        @file_put_contents($this->tokenFile, json_encode([
            'access_token' => $body['access_token'],
            'expires'      => time() + (int)($body['expires_in'] ?? 900),
        ]));
        @chmod($this->tokenFile, 0600);
        return $body['access_token'];
    }

    private function request(string $method, string $path, ?array $json = null, array $headers = []): array
    {
        $h = array_merge($headers, ['X-Peppol-Participant-Id: ' . $this->participantId]);
        [$code, $body] = $this->http($method, $this->base . $path, $json, array_merge($h, ['Authorization: Bearer ' . $this->token()]));
        if ($code === 401) { // token zrušený na strane poštára → skúsiť raz nový
            [$code, $body] = $this->http($method, $this->base . $path, $json, array_merge($h, ['Authorization: Bearer ' . $this->token(true)]));
        }
        if ($code >= 400) {
            throw new RuntimeException('Poštár vrátil HTTP ' . $code . ': ' . self::errorText($body));
        }
        return $body;
    }

    /** @return array{0:int,1:array} */
    private function http(string $method, string $url, ?array $json = null, array $headers = []): array
    {
        $ch = curl_init($url);
        $headers[] = 'Accept: application/json';
        if ($json !== null) {
            $headers[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);
        $raw = curl_exec($ch);
        if ($raw === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('Spojenie s poštárom zlyhalo: ' . $err);
        }
        $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        $body = json_decode($raw, true);
        return [$code, is_array($body) ? $body : ['raw' => mb_substr($raw, 0, 500)]];
    }

    private static function errorText(array $body): string
    {
        foreach (['detail', 'error_description', 'message', 'title', 'error', 'raw'] as $k) {
            if (!empty($body[$k])) return is_string($body[$k]) ? $body[$k] : json_encode($body[$k], JSON_UNESCAPED_UNICODE);
        }
        return json_encode($body, JSON_UNESCAPED_UNICODE);
    }
}
