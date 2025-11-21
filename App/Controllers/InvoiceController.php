<?php

namespace App\Controllers;

use App\Services\StripeService;
use App\Services\Logger;
use App\Utils\ResponseHelper;
use App\Utils\ErrorHandler;
use Flight;
use Config;

/**
 * Controller para gerenciar faturas
 */
class InvoiceController
{
    private StripeService $stripeService;

    public function __construct(StripeService $stripeService)
    {
        $this->stripeService = $stripeService;
    }

    /**
     * Obtém fatura por ID
     * GET /v1/invoices/:id
     */
    public function get(string $id): void
    {
        try {
            $invoice = $this->stripeService->getInvoice($id);

            ResponseHelper::sendSuccess([
                'id' => $invoice->id,
                'customer' => $invoice->customer,
                'amount_paid' => $invoice->amount_paid / 100,
                'amount_due' => $invoice->amount_due / 100,
                'currency' => strtoupper($invoice->currency),
                'status' => $invoice->status,
                'paid' => $invoice->paid,
                'invoice_pdf' => $invoice->invoice_pdf,
                'hosted_invoice_url' => $invoice->hosted_invoice_url,
                'created' => date('Y-m-d H:i:s', $invoice->created)
            ]);
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            ResponseHelper::sendNotFoundError('Fatura', ['action' => 'get_invoice', 'invoice_id' => $id]);
        } catch (\Exception $e) {
            ResponseHelper::sendGenericError($e, 'Erro ao obter fatura', 'INVOICE_GET_ERROR', ['action' => 'get_invoice', 'invoice_id' => $id]);
        }
    }
}

