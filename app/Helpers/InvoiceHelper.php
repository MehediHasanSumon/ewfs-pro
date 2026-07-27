<?php

namespace App\Helpers;

use App\Models\Purchase;
use App\Models\Sale;
use App\Models\CreditSale;
use App\Models\WhiteSale;

class InvoiceHelper
{
    public static function generateInvoiceId(): string
    {
        $lastInvoiceNumber = self::getLastInvoiceNumber();
        $nextNumber = $lastInvoiceNumber + 1;
        
        return 'IN' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }

    private static function getLastInvoiceNumber(): int
    {
        $lastPurchase = Purchase::where('invoice_no', 'LIKE', 'IN%')
            ->orderBy('invoice_no', 'desc')
            ->value('invoice_no');
            
        $lastSale = Sale::where('invoice_no', 'LIKE', 'IN%')
            ->orderBy('invoice_no', 'desc')
            ->value('invoice_no');
            
        $lastCreditSale = CreditSale::where('invoice_no', 'LIKE', 'IN%')
            ->orderBy('invoice_no', 'desc')
            ->value('invoice_no');
            
        $lastWhiteSale = WhiteSale::where('invoice_no', 'LIKE', 'IN%')
            ->orderBy('invoice_no', 'desc')
            ->value('invoice_no');

        $numbers = [];
        
        if ($lastPurchase) {
            $numbers[] = (int) substr($lastPurchase, 2);
        }
        
        if ($lastSale) {
            $numbers[] = (int) substr($lastSale, 2);
        }
        
        if ($lastCreditSale) {
            $numbers[] = (int) substr($lastCreditSale, 2);
        }
        
        if ($lastWhiteSale) {
            $numbers[] = (int) substr($lastWhiteSale, 2);
        }
        
        return empty($numbers) ? 0 : max($numbers);
    }
}