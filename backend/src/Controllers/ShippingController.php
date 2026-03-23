<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;

class ShippingController
{
    public function estimate(Request $request): void
    {
        $state = strtoupper(trim((string) ($_GET['state'] ?? '')));
        $city = trim((string) ($_GET['city'] ?? ''));
        $subtotal = (float) ($_GET['subtotal'] ?? 0);

        $baseByRegion = [
            'PB' => 14.9, 'PE' => 16.9, 'RN' => 16.9, 'CE' => 18.9, 'AL' => 18.9, 'SE' => 18.9,
            'BA' => 22.9, 'PI' => 22.9, 'MA' => 24.9,
            'SP' => 25.9, 'RJ' => 27.9, 'MG' => 27.9, 'ES' => 27.9,
            'PR' => 29.9, 'SC' => 29.9, 'RS' => 31.9,
            'GO' => 28.9, 'DF' => 26.9, 'MT' => 32.9, 'MS' => 31.9,
            'AM' => 39.9, 'PA' => 36.9, 'RO' => 36.9, 'RR' => 42.9, 'AP' => 42.9, 'AC' => 42.9, 'TO' => 34.9,
        ];
        $capitals = ['João Pessoa','Recife','Natal','Fortaleza','Maceió','Aracaju','Salvador','Teresina','São Luís','São Paulo','Rio de Janeiro','Belo Horizonte','Vitória','Curitiba','Florianópolis','Porto Alegre','Goiânia','Brasília','Cuiabá','Campo Grande','Manaus','Belém','Porto Velho','Boa Vista','Macapá','Rio Branco','Palmas'];
        $shipping = $baseByRegion[$state] ?? 29.9;
        if (in_array($city, $capitals, true)) {
            $shipping -= 3;
        }
        if ($subtotal >= 399) {
            $shipping = max(0, $shipping - 10);
        }

        jsonResponse(['ok' => true, 'data' => ['shipping' => round($shipping, 2), 'state' => $state, 'city' => $city]]);
    }
}
