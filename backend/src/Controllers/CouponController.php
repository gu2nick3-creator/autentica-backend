<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use PDO;

class CouponController
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function index(Request $request): void
    {
        $stmt = $this->db->query('SELECT * FROM coupons ORDER BY created_at DESC');
        jsonResponse(['ok' => true, 'data' => $stmt->fetchAll()]);
    }

    public function store(Request $request): void
    {
        $data = $request->body();
        $code = strtoupper(trim((string) ($data['code'] ?? '')));
        if ($code === '') {
            jsonResponse(['ok' => false, 'message' => 'Código do cupom é obrigatório.'], 422);
            return;
        }

        $stmt = $this->db->prepare('INSERT INTO coupons (id, code, type, value, min_value, max_uses, used_count, expires_at, active, created_at, updated_at) VALUES (:id, :code, :type, :value, :min_value, :max_uses, 0, :expires_at, :active, NOW(), NOW())');
        $stmt->execute([
            'id' => uuidv4(),
            'code' => $code,
            'type' => ($data['type'] ?? 'percentage') === 'fixed' ? 'fixed' : 'percentage',
            'value' => (float) ($data['value'] ?? 0),
            'min_value' => (float) ($data['minValue'] ?? 0),
            'max_uses' => (int) ($data['maxUses'] ?? 0),
            'expires_at' => $data['expiresAt'] ?? null,
            'active' => !empty($data['active']) ? 1 : 0,
        ]);

        jsonResponse(['ok' => true, 'message' => 'Cupom criado com sucesso.'], 201);
    }

    public function update(Request $request, array $params): void
    {
        $data = $request->body();
        $stmt = $this->db->prepare('UPDATE coupons SET code=:code, type=:type, value=:value, min_value=:min_value, max_uses=:max_uses, expires_at=:expires_at, active=:active, updated_at=NOW() WHERE id=:id');
        $stmt->execute([
            'id' => $params['id'],
            'code' => strtoupper(trim((string) ($data['code'] ?? ''))),
            'type' => ($data['type'] ?? 'percentage') === 'fixed' ? 'fixed' : 'percentage',
            'value' => (float) ($data['value'] ?? 0),
            'min_value' => (float) ($data['minValue'] ?? 0),
            'max_uses' => (int) ($data['maxUses'] ?? 0),
            'expires_at' => $data['expiresAt'] ?? null,
            'active' => !empty($data['active']) ? 1 : 0,
        ]);
        jsonResponse(['ok' => true, 'message' => 'Cupom atualizado com sucesso.']);
    }

    public function destroy(Request $request, array $params): void
    {
        $stmt = $this->db->prepare('DELETE FROM coupons WHERE id = :id');
        $stmt->execute(['id' => $params['id']]);
        jsonResponse(['ok' => true, 'message' => 'Cupom excluído com sucesso.']);
    }

    public function validateCoupon(Request $request): void
    {
        $code = strtoupper(trim((string) ($_GET['code'] ?? '')));
        $subtotal = (float) ($_GET['subtotal'] ?? 0);

        $stmt = $this->db->prepare('SELECT * FROM coupons WHERE code = :code AND active = 1 LIMIT 1');
        $stmt->execute(['code' => $code]);
        $coupon = $stmt->fetch();

        if (!$coupon) {
            jsonResponse(['ok' => false, 'message' => 'Cupom inválido.'], 404);
            return;
        }
        if ($coupon['expires_at'] && strtotime((string) $coupon['expires_at']) < time()) {
            jsonResponse(['ok' => false, 'message' => 'Cupom expirado.'], 422);
            return;
        }
        if ((int) $coupon['max_uses'] > 0 && (int) $coupon['used_count'] >= (int) $coupon['max_uses']) {
            jsonResponse(['ok' => false, 'message' => 'Cupom esgotado.'], 422);
            return;
        }
        if ($subtotal < (float) $coupon['min_value']) {
            jsonResponse(['ok' => false, 'message' => 'Valor mínimo não atingido.'], 422);
            return;
        }

        $discount = $coupon['type'] === 'fixed' ? (float) $coupon['value'] : round($subtotal * ((float) $coupon['value'] / 100), 2);
        jsonResponse(['ok' => true, 'data' => ['coupon' => $coupon, 'discount' => $discount]]);
    }
}
