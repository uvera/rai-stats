<?php

namespace Tests\Feature\Services\Receipts;

use App\Models\GroceryAccount;
use App\Services\Receipts\Data\InvoiceSummary;
use App\Services\Receipts\Data\ProviderToken;
use App\Services\Receipts\Metro\MetroClient;
use App\Services\Receipts\Metro\MetroProvider;
use Carbon\CarbonImmutable;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MetroProviderTest extends TestCase
{
    use RefreshDatabase;

    private function provider(array $responses): MetroProvider
    {
        $http = new Client(['handler' => HandlerStack::create(new MockHandler($responses)), 'http_errors' => false]);

        return new MetroProvider(new MetroClient($http));
    }

    private function token(): ProviderToken
    {
        return new ProviderToken('access-token', CarbonImmutable::now()->addHour());
    }

    public function test_list_invoices_maps_metro_rows(): void
    {
        $provider = $this->provider([
            new Response(200, [], json_encode([
                'numFound' => 1,
                'invoices' => [[
                    'invoiceDate' => '2026-08-23T12:34:33.000Z',
                    'transactionId' => 'SRB_22_11_1089199_20260823123433',
                    'totalAmount' => 4111.32,
                    'netAmount' => 3452.98,
                ]],
            ])),
        ]);

        $invoices = $provider->listInvoices(GroceryAccount::factory()->metro()->make(), $this->token());

        $this->assertCount(1, $invoices);
        $this->assertSame('SRB_22_11_1089199_20260823123433', $invoices[0]->externalRef);
        $this->assertSame(411132, $invoices[0]->totalCents);
        $this->assertSame(345298, $invoices[0]->netTotalCents);
        $this->assertSame('Metro 22', $invoices[0]->storeName);
    }

    public function test_fetch_receipt_builds_items_with_derived_vat(): void
    {
        $provider = $this->provider([
            new Response(200, [], json_encode(['articles' => [
                ['description' => 'METRO LIMETA', 'quantity' => 0.405, 'netItemPrice' => 663.62, 'grossItemPrice' => 729.98, 'netItemTotal' => 268.77, 'grossItemTotal' => 295.65],
            ]])),
            new Response(200, [], '%PDF-1.4 fake'),
        ]);

        $summary = InvoiceSummary::fromMetroRow([
            'transactionId' => 'SRB_1',
            'invoiceDate' => '2026-08-23T12:00:00Z',
            'totalAmount' => 295.65,
            'netAmount' => 268.77,
        ]);

        $fetched = $provider->fetchReceipt(GroceryAccount::factory()->metro()->make(), $this->token(), $summary);

        $item = $fetched->parsed->items[0];
        $this->assertSame('METRO LIMETA', $item->name);
        $this->assertSame(29565, $item->totalCents);
        $this->assertSame(26877, $item->netTotalCents);
        $this->assertEqualsWithDelta(10.0, $item->vatRate, 0.1);
    }

    public function test_refresh_returns_null_without_a_refresh_token(): void
    {
        $provider = $this->provider([]);

        $this->assertNull($provider->refresh(GroceryAccount::factory()->metro()->make(['refresh_token' => null])));
    }
}
