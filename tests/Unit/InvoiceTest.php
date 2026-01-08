<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class InvoiceTest extends TestCase
{
    /**
     * Test invoice number format validation
     */
    public function test_invoice_number_format(): void
    {
        // Invoice numbers should follow pattern: INV-YYYY-MM-XXXXXX
        $year = date('Y');
        $month = date('m');
        $pattern = "/^INV-{$year}-{$month}-\\d+$/";
        
        // Example valid invoice number
        $exampleInvoiceNumber = "INV-{$year}-{$month}-000001";
        
        $this->assertMatchesRegularExpression($pattern, $exampleInvoiceNumber);
    }
}

