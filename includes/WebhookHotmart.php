<?php
if (!defined('ABSPATH')) exit;

/**
 * Endpoint Webhook Hotmart
 * - Valida token de origem (X-HOTMART-HOTTOK)
 * - Mapeia status e salva licença local
 * - Idempotente via transaction_code (quando enviado)
 */
class AlphaSuite_WebhookHotmart {

    const ROUTE = 'pga/v1/hotmart';

    public static function register_routes(){
        register_rest_route('pga/v1', '/hotmart', [
            'methods'  => 'POST',
            'permission_callback' => '__return_true', // Hotmart precisa POST público
            'callback' => [__CLASS__, 'handle'],
        ]);
    }

    public static function handle(WP_REST_Request $req){
        // 1) valida origem (token fixo configurado nas opções)
        $lic   = AlphaSuite_License::get();
        $tok   = trim((string)($lic['webhook_token'] ?? ''));
        $htTok = trim((string)$req->get_header('X-HOTMART-HOTTOK'));
        if (!$tok || !$htTok || !hash_equals($tok, $htTok)) {
            return new WP_Error('pga_hotmart_token', 'Token inválido.', ['status'=>401]);
        }

        // 2) pega corpo (Hotmart pode mandar form-encoded ou JSON dependendo do tipo/evento)
        $raw = $req->get_body();
        $json = json_decode($raw, true);
        $data = is_array($json) ? $json : $req->get_params(); // fallback form

        // 3) extrai campos importantes (ajuste conforme payload do seu webhook Hotmart)
        $purchaseStatus     = strtoupper(trim((string)($data['purchase_status'] ?? $data['status'] ?? '')));
        $subscriptionStatus = strtoupper(trim((string)($data['subscription_status'] ?? '')));
        $buyerEmail         = trim((string)($data['buyer']['email'] ?? $data['buyer_email'] ?? ''));
        $productId          = (string)($data['product']['id'] ?? $data['product_id'] ?? '');
        $validUntil         = ''; // se usar assinatura com expiração, você pode preencher aqui
        $lastEvent          = trim((string)($data['event'] ?? $data['event_type'] ?? ''));

        // 4) mapeia status para nossa licença local
        $mapped = AlphaSuite_License::map_hotmart_status($purchaseStatus, $subscriptionStatus);

        // 5) persiste
        AlphaSuite_License::set([
            'buyer_email' => $buyerEmail ?: ($lic['buyer_email'] ?? ''),
            'product_id'  => $productId ?: ($lic['product_id'] ?? ''),
            'status'      => $mapped,
            'valid_until' => $validUntil,
            'last_event'  => $lastEvent,
        ]);

        // 6) responda 200 rapidamente (Hotmart reprocessa em caso de 4xx/5xx)
        return [
            'ok' => true,
            'mapped_status' => $mapped,
        ];
    }
}
