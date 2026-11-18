<?php

/** Odoslanie faktúry cez poštára a uloženie výsledku – spoločné pre web aj API. */
class Odoslanie
{
    /**
     * @return array{status: string, message: string, receiver?: string, provider_document_id?: ?string}
     *         status: ACCEPTED | UNDELIVERABLE | REJECTED | ERROR
     */
    public static function odoslat(int $id): array
    {
        try {
            $u = UblInvoice::build($id);
            $client = new SapiClient($u['sender']);
            // Rovnaký obsah = rovnaký kľúč → opakované odoslanie faktúru nepošle dvakrát; po úprave vznikne nový kľúč
            $key = 'fakturacia-' . $id . '-' . substr(hash('sha256', $u['xml']), 0, 16);
            $res = $client->sendInvoice($u['xml'], $u['cislo'], $u['receiver'], $key);
        } catch (RuntimeException $e) {
            DB::update('faktury', ['peppol_status' => 'ERROR', 'peppol_detail' => mb_substr($e->getMessage(), 0, 2000)], 'id = ?', [$id]);
            return ['status' => 'ERROR', 'message' => 'E-faktúra neodoslaná: ' . $e->getMessage()];
        }

        $status = ($res['status'] ?? '') === 'ACCEPTED' ? (!empty($res['undeliverable']) ? 'UNDELIVERABLE' : 'ACCEPTED') : 'REJECTED';

        DB::update('faktury', [
            'peppol_status'      => $status,
            'peppol_document_id' => $res['providerDocumentId'] ?? null,
            'peppol_sent_at'     => date('Y-m-d H:i:s'),
            'peppol_xml'         => $status === 'REJECTED' ? null : $u['xml'], // originál e-faktúry na archiváciu
            'peppol_detail'      => isset($res['detail']) ? mb_substr((string)$res['detail'], 0, 2000) : null,
        ], 'id = ?', [$id]);

        $msg = match ($status) {
            'ACCEPTED'      => 'E-faktúra ' . $u['cislo'] . ' odoslaná cez Peppol (' . $u['receiver'] . ').',
            'UNDELIVERABLE' => 'Poštár faktúru ' . $u['cislo'] . ' prevzal, ale odberateľ ' . $u['receiver'] . ' ešte nie je v sieti Peppol – nedoručí sa, pošli mu PDF.',
            default         => 'Poštár faktúru odmietol: ' . ($res['detail'] ?? 'bez dôvodu'),
        };
        return ['status' => $status, 'message' => $msg, 'receiver' => $u['receiver'], 'provider_document_id' => $res['providerDocumentId'] ?? null];
    }
}
