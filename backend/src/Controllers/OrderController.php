<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Services\AuthService;
use PDO;

class OrderController
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function index(Request $request): void
    {
        $user = AuthService::guard($request);
        if (!$user) {
            jsonResponse(['ok' => false, 'message' => 'Não autorizado.'], 401);
            return;
        }

        if (($user['role'] ?? '') === 'admin') {
            $stmt = $this->db->query('SELECT * FROM orders ORDER BY created_at DESC');
            $rows = array_map([$this, 'mapOrder'], $stmt->fetchAll());
            jsonResponse(['ok' => true, 'data' => $rows]);
            return;
        }

        $stmt = $this->db->prepare('SELECT * FROM orders WHERE customer_id = :customer_id OR JSON_UNQUOTE(JSON_EXTRACT(customer_json, "$.email")) = :email ORDER BY created_at DESC');
        $stmt->execute(['customer_id' => (string) $user['sub'], 'email' => (string) ($user['email'] ?? '')]);
        $rows = array_map([$this, 'mapOrder'], $stmt->fetchAll());
        jsonResponse(['ok' => true, 'data' => $rows]);
    }

    public function store(Request $request): void
    {
        $data = $request->body();
        if (empty($data['customer']['name']) || empty($data['customer']['email'])) {
            jsonResponse(['ok' => false, 'message' => 'Dados do cliente são obrigatórios.'], 422);
            return;
        }

        $orderId = 'AF' . date('YmdHis') . random_int(10, 99);
        $auth = AuthService::guard($request);
        $customerId = (($auth['role'] ?? '') === 'customer') ? (string) ($auth['sub'] ?? '') : null;
        $customerPayload = $data['customer'] ?? [];
        if ($customerId) {
            $customerStmt = $this->db->prepare('SELECT id, name, email, phone, cpf FROM customers WHERE id = :id LIMIT 1');
            $customerStmt->execute(['id' => $customerId]);
            $dbCustomer = $customerStmt->fetch();
            if ($dbCustomer) {
                $customerPayload = array_merge([
                    'id' => $dbCustomer['id'],
                    'name' => $dbCustomer['name'],
                    'email' => $dbCustomer['email'],
                    'phone' => $dbCustomer['phone'],
                    'cpf' => $dbCustomer['cpf'],
                ], $customerPayload);
            }
        }

        $stmt = $this->db->prepare('INSERT INTO orders (id, status, subtotal, shipping, discount, total, coupon_code, payment_method, payment_tag, customer_id, customer_json, address_json, items_json, tracking_code, created_at, updated_at) VALUES (:id, :status, :subtotal, :shipping, :discount, :total, :coupon_code, :payment_method, :payment_tag, :customer_id, :customer_json, :address_json, :items_json, :tracking_code, NOW(), NOW())');
        $stmt->execute([
            'id' => $orderId,
            'status' => $data['status'] ?? 'pending',
            'subtotal' => (float) ($data['subtotal'] ?? 0),
            'shipping' => (float) ($data['shipping'] ?? 0),
            'discount' => (float) ($data['discount'] ?? 0),
            'total' => (float) ($data['total'] ?? 0),
            'coupon_code' => $data['couponCode'] ?? null,
            'payment_method' => $data['paymentMethod'] ?? 'infinitepay',
            'payment_tag' => $data['paymentTag'] ?? '$autentica_fashion',
            'customer_id' => $customerId,
            'customer_json' => json_encode($customerPayload, JSON_UNESCAPED_UNICODE),
            'address_json' => json_encode($data['address'] ?? [], JSON_UNESCAPED_UNICODE),
            'items_json' => json_encode($data['items'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'tracking_code' => $data['trackingCode'] ?? null,
        ]);

        if ($customerId) {
            CustomerController::syncTotalsForCustomer($this->db, $customerId, $data['address'] ?? []);
        }

        jsonResponse(['ok' => true, 'message' => 'Pedido criado com sucesso.', 'data' => ['id' => $orderId]], 201);
    }

    public function updateStatus(Request $request, array $params): void
    {
        $data = $request->body();
        $stmt = $this->db->prepare('UPDATE orders SET status = :status, updated_at = NOW() WHERE id = :id');
        $stmt->execute(['id' => $params['id'], 'status' => $data['status'] ?? 'pending']);
        jsonResponse(['ok' => true, 'message' => 'Status atualizado com sucesso.']);
    }

    public function updateTracking(Request $request, array $params): void
    {
        $data = $request->body();
        $stmt = $this->db->prepare('UPDATE orders SET tracking_code = :tracking_code, updated_at = NOW() WHERE id = :id');
        $stmt->execute(['id' => $params['id'], 'tracking_code' => $data['trackingCode'] ?? null]);
        jsonResponse(['ok' => true, 'message' => 'Rastreio atualizado com sucesso.']);
    }

    private function mapOrder(array $row): array
    {
        return [
            'id' => $row['id'],
            'status' => $row['status'],
            'subtotal' => (float) $row['subtotal'],
            'shipping' => (float) $row['shipping'],
            'discount' => (float) $row['discount'],
            'total' => (float) $row['total'],
            'couponCode' => $row['coupon_code'],
            'paymentMethod' => $row['payment_method'],
            'paymentTag' => $row['payment_tag'],
            'customer' => json_decode($row['customer_json'], true) ?: [],
            'address' => json_decode($row['address_json'], true) ?: [],
            'items' => json_decode($row['items_json'], true) ?: [],
            'trackingCode' => $row['tracking_code'],
            'createdAt' => $row['created_at'],
            'updatedAt' => $row['updated_at'],
        ];
    }
}
