<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use PDO;

class ProductController
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function index(Request $request): void
    {
        $stmt = $this->db->query('SELECT * FROM products ORDER BY created_at DESC');
        $items = array_map([$this, 'mapProduct'], $stmt->fetchAll());
        jsonResponse(['ok' => true, 'data' => $items]);
    }

    public function show(Request $request, array $params): void
    {
        $stmt = $this->db->prepare('SELECT * FROM products WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $params['id']]);
        $row = $stmt->fetch();
        if (!$row) {
            jsonResponse(['ok' => false, 'message' => 'Produto não encontrado.'], 404);
            return;
        }
        jsonResponse(['ok' => true, 'data' => $this->mapProduct($row)]);
    }

    public function store(Request $request): void
    {
        $data = $this->validate($request->body());
        if (isset($data['error'])) {
            jsonResponse(['ok' => false, 'message' => $data['error']], 422);
            return;
        }

        $sql = 'INSERT INTO products (id, name, slug, sku, short_description, description, price, sale_price, category, product_type, sizes_json, colors_json, images_json, stock, featured, is_new, on_sale, best_seller, active, rating, review_count, created_at, updated_at)
                VALUES (:id, :name, :slug, :sku, :short_description, :description, :price, :sale_price, :category, :product_type, :sizes_json, :colors_json, :images_json, :stock, :featured, :is_new, :on_sale, :best_seller, :active, :rating, :review_count, NOW(), NOW())';
        $stmt = $this->db->prepare($sql);
        $payload = $this->dbPayload($data + ['id' => uuidv4()]);
        $stmt->execute($payload);

        $created = $this->find($payload['id']);
        jsonResponse(['ok' => true, 'message' => 'Produto criado com sucesso.', 'data' => $created], 201);
    }

    public function update(Request $request, array $params): void
    {
        $data = $this->validate($request->body(), false);
        if (isset($data['error'])) {
            jsonResponse(['ok' => false, 'message' => $data['error']], 422);
            return;
        }
        $existing = $this->find($params['id']);
        if (!$existing) {
            jsonResponse(['ok' => false, 'message' => 'Produto não encontrado.'], 404);
            return;
        }

        $merged = array_merge($existing, $data);
        $payload = $this->dbPayload($merged);
        $payload['id'] = $params['id'];

        $sql = 'UPDATE products SET name=:name, slug=:slug, sku=:sku, short_description=:short_description, description=:description, price=:price, sale_price=:sale_price, category=:category, product_type=:product_type, sizes_json=:sizes_json, colors_json=:colors_json, images_json=:images_json, stock=:stock, featured=:featured, is_new=:is_new, on_sale=:on_sale, best_seller=:best_seller, active=:active, rating=:rating, review_count=:review_count, updated_at=NOW() WHERE id=:id';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($payload);

        jsonResponse(['ok' => true, 'message' => 'Produto atualizado com sucesso.', 'data' => $this->find($params['id'])]);
    }

    public function destroy(Request $request, array $params): void
    {
        $stmt = $this->db->prepare('DELETE FROM products WHERE id = :id');
        $stmt->execute(['id' => $params['id']]);
        jsonResponse(['ok' => true, 'message' => 'Produto excluído com sucesso.']);
    }

    private function find(string $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM products WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ? $this->mapProduct($row) : null;
    }

    private function validate(array $data, bool $required = true): array
    {
        $name = trim((string) ($data['name'] ?? ''));
        if ($required && $name === '') {
            return ['error' => 'Nome do produto é obrigatório.'];
        }

        $price = (float) ($data['price'] ?? 0);
        $category = trim((string) ($data['category'] ?? ''));
        $shortDescription = trim((string) ($data['shortDescription'] ?? ''));
        $description = trim((string) ($data['description'] ?? ''));
        $sizes = array_values(array_filter($data['sizes'] ?? [], static fn($item) => trim((string) $item) !== ''));
        $images = array_values(array_filter($data['images'] ?? [], static fn($item) => trim((string) $item) !== ''));
        $colors = array_values(array_filter($data['colors'] ?? [], static fn($item) => !empty($item['name']) && !empty($item['hex'])));

        if ($required) {
            if ($shortDescription === '') return ['error' => 'Descrição curta é obrigatória.'];
            if ($description === '') return ['error' => 'Descrição completa é obrigatória.'];
            if ($category === '') return ['error' => 'Categoria é obrigatória.'];
            if ($price <= 0) return ['error' => 'Preço deve ser maior que zero.'];
            if (empty($sizes)) return ['error' => 'Informe pelo menos um tamanho.'];
            if (empty($colors)) return ['error' => 'Informe pelo menos uma cor válida.'];
            if (empty($images)) return ['error' => 'Adicione pelo menos uma imagem.'];
        }

        return [
            'name' => $name ?: ($data['name'] ?? ''),
            'slug' => trim((string) ($data['slug'] ?? $this->slugify($name ?: (string) ($data['name'] ?? 'produto')))),
            'sku' => trim((string) ($data['sku'] ?? '')) ?: null,
            'shortDescription' => $shortDescription,
            'description' => $description,
            'price' => $price,
            'salePrice' => array_key_exists('salePrice', $data) && $data['salePrice'] !== null && $data['salePrice'] !== '' ? (float) $data['salePrice'] : null,
            'category' => $category,
            'productType' => trim((string) ($data['productType'] ?? 'roupa')),
            'sizes' => $sizes,
            'colors' => $colors,
            'images' => $images,
            'stock' => (int) ($data['stock'] ?? 0),
            'featured' => !empty($data['featured']),
            'isNew' => !empty($data['isNew']),
            'onSale' => !empty($data['onSale']),
            'bestSeller' => !empty($data['bestSeller']),
            'active' => array_key_exists('active', $data) ? (bool) $data['active'] : true,
            'rating' => (float) ($data['rating'] ?? 5),
            'reviewCount' => (int) ($data['reviewCount'] ?? 0),
        ];
    }

    private function dbPayload(array $data): array
    {
        return [
            'id' => $data['id'],
            'name' => $data['name'],
            'slug' => $data['slug'],
            'sku' => $data['sku'],
            'short_description' => $data['shortDescription'],
            'description' => $data['description'],
            'price' => $data['price'],
            'sale_price' => $data['salePrice'],
            'category' => $data['category'],
            'product_type' => $data['productType'],
            'sizes_json' => json_encode($data['sizes'], JSON_UNESCAPED_UNICODE),
            'colors_json' => json_encode($data['colors'], JSON_UNESCAPED_UNICODE),
            'images_json' => json_encode($data['images'], JSON_UNESCAPED_SLASHES),
            'stock' => $data['stock'],
            'featured' => (int) $data['featured'],
            'is_new' => (int) $data['isNew'],
            'on_sale' => (int) $data['onSale'],
            'best_seller' => (int) $data['bestSeller'],
            'active' => (int) $data['active'],
            'rating' => $data['rating'],
            'review_count' => $data['reviewCount'],
        ];
    }

    private function mapProduct(array $row): array
    {
        return [
            'id' => $row['id'],
            'name' => $row['name'],
            'slug' => $row['slug'],
            'sku' => $row['sku'] ?? '',
            'shortDescription' => $row['short_description'],
            'description' => $row['description'],
            'price' => (float) $row['price'],
            'salePrice' => $row['sale_price'] !== null ? (float) $row['sale_price'] : null,
            'category' => $row['category'],
            'productType' => $row['product_type'],
            'sizes' => json_decode($row['sizes_json'], true) ?: [],
            'colors' => json_decode($row['colors_json'], true) ?: [],
            'images' => json_decode($row['images_json'], true) ?: [],
            'stock' => (int) $row['stock'],
            'featured' => (bool) $row['featured'],
            'isNew' => (bool) $row['is_new'],
            'onSale' => (bool) $row['on_sale'],
            'bestSeller' => (bool) $row['best_seller'],
            'active' => (bool) $row['active'],
            'rating' => (float) $row['rating'],
            'reviewCount' => (int) $row['review_count'],
            'createdAt' => $row['created_at'],
            'updatedAt' => $row['updated_at'],
        ];
    }

    private function slugify(string $text): string
    {
        $text = strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9]+/i', '-', iconv('UTF-8', 'ASCII//TRANSLIT', $text) ?: $text);
        return trim((string) $text, '-') ?: 'produto';
    }
}
