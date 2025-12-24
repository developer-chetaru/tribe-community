<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    /**
     * Download invoice as PDF
     */
    public function download($invoiceId)
    {
        $invoice = Invoice::with(['organisation', 'subscription', 'payments'])
            ->findOrFail($invoiceId);

        $user = auth()->user();

        // Check permissions - super_admin can access all, director can access their org's invoices
        if ($user->hasRole('super_admin')) {
            // Super admin can access all invoices
        } elseif ($user->hasRole('director')) {
            // Director can only access invoices from their organisation
            if ($invoice->organisation_id !== $user->orgId) {
                abort(403, 'Unauthorized access. You can only access invoices from your organisation.');
            }
        } else {
            abort(403, 'Only directors and administrators can download invoices.');
        }

        // Return HTML view with download headers (can be printed as PDF by browser)
        $html = view('invoices.pdf', [
            'invoice' => $invoice,
            'organisation' => $invoice->organisation,
            'subscription' => $invoice->subscription,
            'payments' => $invoice->payments,
        ])->render();

        return response($html)
            ->header('Content-Type', 'text/html')
            ->header('Content-Disposition', 'attachment; filename="invoice-' . $invoice->invoice_number . '.html"');
    }

    /**
     * View invoice in browser
     */
    public function view($invoiceId)
    {
        $invoice = Invoice::with(['organisation', 'subscription', 'payments'])
            ->findOrFail($invoiceId);

        $user = auth()->user();

        // Check permissions - super_admin can access all, director can access their org's invoices
        if ($user->hasRole('super_admin')) {
            // Super admin can access all invoices
        } elseif ($user->hasRole('director')) {
            // Director can only access invoices from their organisation
            if ($invoice->organisation_id !== $user->orgId) {
                abort(403, 'Unauthorized access. You can only access invoices from your organisation.');
            }
        } else {
            abort(403, 'Only directors and administrators can view invoices.');
        }

        // Return HTML view
        return view('invoices.pdf', [
            'invoice' => $invoice,
            'organisation' => $invoice->organisation,
            'subscription' => $invoice->subscription,
            'payments' => $invoice->payments,
        ]);
    }
}
