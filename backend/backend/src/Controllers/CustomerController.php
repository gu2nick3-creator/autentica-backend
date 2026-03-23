<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Services\AuthService;
use PDO;
use Throwable;

class CustomerController
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function index(Request $request): void
    {
        $user = AuthService::guard($request);
        if (!$user || ($user['role'] ?? '') !== 'admin') {
            jsonResponse(['ok' => false, 'message' => 'Não autorizado.'], 401);
            return;
        }

        $stmt = $this->db->query('SELECT * FROM customers ORDER BY created_at DESC');
        $items = array_map([$this, 'mapCustomer'], $stmt->fetchAll());
        jsonResponse(['ok' => true, 'data' => $items]);
    }

    public function register(Request $request): void
    {
        $data = $request->body();
        $name = trim((string) ($data['name'] ?? ''));
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $phone = trim((string) ($data['phone'] ?? ''));
        $cpf = trim((string) ($data['cpf'] ?? ''));
        $password = (string) ($data['password'] ?? '');

        if ($name === '' || $email === '' || $password === '') {
            jsonResponse(['ok' => false, 'message' => 'Nome, e-mail e senha são obrigatórios.'], 422);
            return;
        }

        $exists = $this->db->prepare('SELECT id FROM customers WHERE email = :email LIMIT 1');
        $exists->execute(['email' => $email]);
        if ($exists->fetch()) {
            jsonResponse(['ok' => false, 'message' => 'Já existe uma conta com esse e-mail.'], 422);
            return;
        }

        $id = uuidv4();
        $stmt = $this->db->prepare('
            INSERT INTO customers (
                id, name, email, phone, cpf, password_hash, addresses_json, total_spent, created_at, updated_at
            ) VALUES (
                :id, :name, :email, :phone, :cpf, :password_hash, :addresses_json, :total_spent, NOW(), NOW()
            )
        ');
        $stmt->execute([
            'id' => $id,
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'cpf' => $cpf !== '' ? $cpf : null,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'addresses_json' => json_encode([], JSON_UNESCAPED_UNICODE),
            'total_spent' => 0,
        ]);

        $customer = $this->findById($id);
        $auth = AuthService::issueCustomerToken($customer);
        jsonResponse(['ok' => true, 'message' => 'Conta criada com sucesso.', 'data' => $auth], 201);
    }

    public function login(Request $request): void
    {
        $data = $request->body();
        $email = strtolower(trim((string) ($data['email'] ?? $data['username'] ?? '')));
        $password = (string) ($data['password'] ?? '');

        if ($email === '' || $password === '') {
            jsonResponse(['ok' => false, 'message' => 'E-mail e senha são obrigatórios.'], 422);
            return;
        }

        $stmt = $this->db->prepare('SELECT * FROM customers WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch();

        if (!$row || !password_verify($password, (string) $row['password_hash'])) {
            jsonResponse(['ok' => false, 'message' => 'E-mail ou senha inválidos.'], 401);
            return;
        }

        $customer = $this->mapCustomer($row);
        $auth = AuthService::issueCustomerToken($customer);
        jsonResponse(['ok' => true, 'data' => $auth]);
    }

    public function me(Request $request): void
    {
        $user = AuthService::guard($request);
        if (!$user || ($user['role'] ?? '') !== 'customer') {
            jsonResponse(['ok' => false, 'message' => 'Não autorizado.'], 401);
            return;
        }

        $customer = $this->findById((string) $user['sub']);
        if (!$customer) {
            jsonResponse(['ok' => false, 'message' => 'Cliente não encontrado.'], 404);
            return;
        }

        jsonResponse(['ok' => true, 'data' => $customer]);
    }

    public function orders(Request $request): void
    {
        $user = AuthService::guard($request);
        if (!$user || ($user['role'] ?? '') !== 'customer') {
            jsonResponse(['ok' => false, 'message' => 'Não autorizado.'], 401);
            return;
        }

        try {
            $stmt = $this->db->prepare('
                SELECT * FROM orders
                WHERE customer_id = :customer_id
                   OR JSON_UNQUOTE(JSON_EXTRACT(customer_json, "$.email")) = :email
                ORDER BY created_at DESC
            ');
            $stmt->execute([
                'customer_id' => (string) $user['sub'],
                'email' => (string) ($user['email'] ?? ''),
            ]);
            $rows = $stmt->fetchAll();
        } catch (Throwable $e) {
            $rows = [];
        }

        $orders = array_map(static function (array $row): array {
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
        }, $rows);

        jsonResponse(['ok' => true, 'data' => $orders]);
    }

    public static function syncTotalsForCustomer(PDO $db, string $customerId, array $address = []): void
    {
        $totalSpent = 0.0;

        try {
            $sumStmt = $db->prepare('SELECT COALESCE(SUM(total), 0) AS total_spent FROM orders WHERE customer_id = :customer_id');
            $sumStmt->execute(['customer_id' => $customerId]);
            $totalSpent = (float) ($sumStmt->fetch()['total_spent'] ?? 0);
        } catch (Throwable $e) {
            $totalSpent = 0.0;
        }

        $addresses = [];
        $customerStmt = $db->prepare('SELECT addresses_json FROM customers WHERE id = :id LIMIT 1');
        $customerStmt->execute(['id' => $customerId]);
        $current = $customerStmt->fetch();

        if ($current) {
            $addresses = json_decode((string) ($current['addresses_json'] ?? '[]'), true) ?: [];
        }

        if (!empty($address)) {
            $normalized = [
                'street' => (string) ($address['street'] ?? ''),
                'number' => (string) ($address['number'] ?? ''),
                'complement' => (string) ($address['complement'] ?? ''),
                'neighborhood' => (string) ($address['neighborhood'] ?? ''),
                'city' => (string) ($address['city'] ?? ''),
                'state' => (string) ($address['state'] ?? ''),
                'zipCode' => (string) ($address['zipCode'] ?? ''),
            ];

            $exists = false;
            foreach ($addresses as $existing) {
                if (
                    ($existing['street'] ?? '') === $normalized['street'] &&
                    ($existing['number'] ?? '') === $normalized['number'] &&
                    ($existing['zipCode'] ?? '') === $normalized['zipCode']
                ) {
                    $exists = true;
                    break;
                }
            }

            if (!$exists && $normalized['street'] !== '') {
                array_unshift($addresses, $normalized);
            }
        }

        $update = $db->prepare('
            UPDATE customers
            SET total_spent = :total_spent,
                addresses_json = :addresses_json,
                updated_at = NOW()
            WHERE id = :id
        ');
        $update->execute([
            'id' => $customerId,
            'total_spent' => $totalSpent,
            'addresses_json' => json_encode($addresses, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    private function findById(string $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM customers WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->mapCustomer($row) : null;
    }

    private function mapCustomer(array $row): array
    {
        $addresses = json_decode((string) ($row['addresses_json'] ?? '[]'), true) ?: [];
        $orders = [];

        try {
            $orderStmt = $this->db->prepare('SELECT id FROM orders WHERE customer_id = :customer_id ORDER BY created_at DESC');
            $orderStmt->execute(['customer_id' => $row['id']]);
            $orders = array_map(static fn(array $order): string => (string) $order['id'], $orderStmt->fetchAll());
        } catch (Throwable $e) {
            $orders = [];
        }

        return [
            'id' => $row['id'],
            'name' => $row['name'],
            'email' => $row['email'],
            'phone' => $row['phone'] ?? '',
            'cpf' => !empty($row['cpf']) ? $row['cpf'] : null,
            'orders' => $orders,
            'totalSpent' => (float) ($row['total_spent'] ?? 0),
            'createdAt' => $row['created_at'],
            'addresses' => $addresses,
        ];
    }
}
