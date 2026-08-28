<?php

namespace Tests\Unit\Services\Receipts;

use App\Services\Receipts\Data\ParsedItem;
use App\Services\Receipts\ReceiptPdfParser;
use PHPUnit\Framework\TestCase;

class ReceiptPdfParserTest extends TestCase
{
    private function parse()
    {
        return (new ReceiptPdfParser)->parse(
            file_get_contents(__DIR__.'/../../../Fixtures/maxi/ereceipt-sample.pdf')
        );
    }

    public function test_parses_all_line_items(): void
    {
        $parsed = $this->parse();

        $this->assertCount(8, $parsed->items);
        $this->assertTrue($parsed->itemsReconcile());
        $this->assertSame(312545, $parsed->itemsTotalCents());
        $this->assertSame(312545, $parsed->totalCents);
    }

    public function test_parses_weighed_item_quantities_and_prices(): void
    {
        $items = collect($this->parse()->items)->keyBy('name');

        /** @var ParsedItem $salama */
        $salama = $items->get('Pikant salama Yuhor/KG');
        $this->assertEqualsWithDelta(0.604, $salama->quantity, 0.0001);
        $this->assertSame(179900, $salama->unitPriceCents);
        $this->assertSame(108660, $salama->totalCents);
        $this->assertSame('Ђ', $salama->vatLabel);
        $this->assertSame(20.0, $salama->vatRate);

        /** @var ParsedItem $paprika */
        $paprika = $items->get('Paprika silja crvena/KG');
        $this->assertEqualsWithDelta(0.544, $paprika->quantity, 0.0001);
        $this->assertSame(9791, $paprika->totalCents);
        $this->assertSame(10.0, $paprika->vatRate);
    }

    public function test_parses_pfr_number_and_purs_token(): void
    {
        $parsed = $this->parse();

        $this->assertSame('FAKE0000-FAKE0000-00001', $parsed->pfrNumber);
        $this->assertNotNull($parsed->pursVl);
        $this->assertStringStartsWith('A0ZBS0UwMDAw', $parsed->pursVl);
    }

    public function test_parses_vat_breakdown(): void
    {
        $vat = collect($this->parse()->vatLines)->keyBy('label');

        $this->assertSame(10.0, $vat['Е']['rate']);
        $this->assertSame(13008, $vat['Е']['tax_cents']);
        $this->assertSame(28243, $vat['Ђ']['tax_cents']);
    }
}
