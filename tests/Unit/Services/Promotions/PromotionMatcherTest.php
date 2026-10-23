<?php

namespace Tests\Unit\Services\Promotions;

use App\Services\Promotions\PromotionMatcher;
use PHPUnit\Framework\TestCase;

class PromotionMatcherTest extends TestCase
{
    private PromotionMatcher $matcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->matcher = new PromotionMatcher;
    }

    public function test_a_short_receipt_name_matches_the_catalog_name(): void
    {
        $this->assertTrue($this->matcher->matches(
            'COLLA PARMIGANO REGGIANO 200G',
            'COLLA Tvrdi sir parmigano reggiano 200 g',
        ));

        $this->assertTrue($this->matcher->matches(
            'PALMOLIVE GEL 500ML',
            'PALMOLIVE Gel za tuširanje naturals milk&honey 500 ml',
        ));
    }

    public function test_an_unrelated_one_word_article_does_not_match(): void
    {
        $this->assertFalse($this->matcher->matches(
            'MLEKO',
            'PALMOLIVE Gel za tuširanje naturals milk&honey 500 ml',
        ));
    }

    public function test_sizes_and_packaging_do_not_match_unrelated_articles(): void
    {
        $this->assertFalse($this->matcher->matches(
            '60L FINO KESE ZA DJ.TYTAN 10/1',
            'vileda Sunđer glitzi rainbow 10/1',
        ));

        $this->assertFalse($this->matcher->matches(
            '1L BARISTA MLEKO ZA KAFU 3.2%',
            'aro Produžni kabl sa prekidačem 3 utičnice 2 usb ulaza beli 1,4 m',
        ));

        $this->assertFalse($this->matcher->matches(
            '1.5L COCA COLA SOK GAZ PET',
            'MLEKARA SUBOTICA Jogurt 1%mm 975 g pet',
        ));

        $this->assertFalse($this->matcher->matches(
            '1L BARISTA MLEKO ZA KAFU 3.2%',
            'MLEKARA SUBOTICA Zdravo! Kiselo mleko 2,8% mm 180 g',
        ));

        $this->assertFalse($this->matcher->matches(
            'ARO UBRUS JUMBO REC 1SL 1470LI',
            'aro Led sijalica 8,5W E27 2700K 2/1',
        ));

        $this->assertFalse($this->matcher->matches(
            'METRO LIMUN  KG',
            'FAIRY Deterdžent za ručno pranje posuđa max power limun 545 ml',
        ));
    }

    public function test_a_short_generic_token_does_not_match_the_aisle(): void
    {
        $this->assertFalse($this->matcher->matches(
            'SIR',
            'COLLA Tvrdi sir parmigano reggiano 200 g',
        ));
    }
}
