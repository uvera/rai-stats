<?php

namespace App\Services\Receipts;

use App\Services\Receipts\Data\ParsedItem;
use App\Services\Receipts\Data\ParsedReceipt;
use Smalot\PdfParser\Parser as PdfParser;

/**
 * Scrapes the itemised fiscal receipt out of a Moj Maxi eReceipt PDF.
 *
 * The PDF's text layer (via smalot/pdfparser) preserves the receipt's
 * fixed-width layout, so each product is two consecutive lines:
 *
 *     Pikant salama Yuhor/KG (Ђ)
 *          1.799,00      0.604        1.086,60
 *
 * i.e. a name line ending in a Cyrillic VAT-class letter in parentheses,
 * then an amounts line: unit price, quantity, line total. Serbian number
 * format: "." groups thousands, "," is the decimal separator - except the
 * quantity column, which uses "." as its decimal separator.
 */
class ReceiptPdfParser
{
    private const NAME_LINE = '/^(?<name>.+?)\s*\((?<vat>[\p{Cyrillic}])\)\s*$/u';

    private const AMOUNT_LINE = '/^\s*(?<unit>[\d.]+,\d{2})\s+(?<qty>[\d.]+)\s+(?<total>[\d.]+,\d{2})\s*$/';

    private const VAT_LINE = '/^\s*(?<label>[\p{Cyrillic}])\s+(?<name>\S+)\s+(?<rate>[\d,]+)%\s+(?<tax>[\d.]+,\d{2})\s*$/u';

    public function __construct(private readonly PdfParser $pdf = new PdfParser) {}

    public function parse(string $pdfBytes): ParsedReceipt
    {
        $text = $this->pdf->parseContent($pdfBytes)->getText();
        $lines = preg_split('/\R/u', $text) ?: [];

        return new ParsedReceipt(
            items: $this->parseItems($lines),
            vatLines: $this->parseVatLines($lines),
            pfrNumber: $this->firstMatch('/ПФР\s+број\s+рачуна:\s*(\S+)/u', $text),
            pursVl: $this->parsePursVl($pdfBytes),
            totalCents: $this->parseTotal($text),
            rawText: $text,
        );
    }

    /**
     * The PDF's plain text layer, with no parsing - used to keep a
     * human-readable copy of a receipt whose items came from a structured API
     * (Metro) rather than the PDF itself.
     */
    public function extractText(string $pdfBytes): string
    {
        return $this->pdf->parseContent($pdfBytes)->getText();
    }

    /**
     * @param  array<int, string>  $lines
     * @return ParsedItem[]
     */
    private function parseItems(array $lines): array
    {
        $items = [];
        $count = count($lines);
        $lineNo = 0;

        for ($i = 0; $i < $count - 1; $i++) {
            if (! preg_match(self::NAME_LINE, trim($lines[$i]), $nameMatch)) {
                continue;
            }

            // Look at the next non-blank line for the amounts.
            $j = $i + 1;
            while ($j < $count && trim($lines[$j]) === '') {
                $j++;
            }

            if ($j >= $count || ! preg_match(self::AMOUNT_LINE, $lines[$j], $amountMatch)) {
                continue;
            }

            $items[] = new ParsedItem(
                lineNo: ++$lineNo,
                name: trim($nameMatch['name']),
                quantity: (float) $amountMatch['qty'],
                unitPriceCents: $this->srMoneyToCents($amountMatch['unit']),
                totalCents: $this->srMoneyToCents($amountMatch['total']),
                vatLabel: $nameMatch['vat'],
                vatRate: $this->vatRateForLabel($nameMatch['vat'], $lines),
            );

            $i = $j;
        }

        return $items;
    }

    /**
     * @param  array<int, string>  $lines
     * @return array<int, array{label: string, name: string, rate: float, tax_cents: int}>
     */
    private function parseVatLines(array $lines): array
    {
        $out = [];

        foreach ($lines as $line) {
            if (preg_match(self::VAT_LINE, $line, $m)) {
                $out[] = [
                    'label' => $m['label'],
                    'name' => $m['name'],
                    'rate' => (float) str_replace(',', '.', $m['rate']),
                    'tax_cents' => $this->srMoneyToCents($m['tax']),
                ];
            }
        }

        return $out;
    }

    /**
     * @param  array<int, string>  $lines
     */
    private function vatRateForLabel(string $label, array $lines): ?float
    {
        foreach ($this->parseVatLines($lines) as $vat) {
            if ($vat['label'] === $label) {
                return $vat['rate'];
            }
        }

        return null;
    }

    private function parseTotal(string $text): ?int
    {
        $value = $this->firstMatch('/Укупан\s+износ:\s*([\d.]+,\d{2})/u', $text);

        return $value !== null ? $this->srMoneyToCents($value) : null;
    }

    private function parsePursVl(string $pdfBytes): ?string
    {
        if (preg_match('~suf\.purs\.gov\.rs\S*?[?&]vl=([^)\s"\'\\\\<>]+)~', $pdfBytes, $m)) {
            return rawurldecode($m[1]);
        }

        return null;
    }

    /**
     * "1.799,00" -> 179900 cents.
     */
    private function srMoneyToCents(string $value): int
    {
        $normalised = str_replace(',', '.', str_replace('.', '', trim($value)));

        return (int) round(((float) $normalised) * 100);
    }

    private function firstMatch(string $pattern, string $subject): ?string
    {
        return preg_match($pattern, $subject, $m) ? trim($m[1]) : null;
    }
}
