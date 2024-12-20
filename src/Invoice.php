<?php

namespace Mafrasil\CashierPolar;

use Mafrasil\CashierPolar\Models\PolarTransaction;
use Symfony\Component\HttpFoundation\Response;

class Invoice
{
    public function __construct(
        protected $owner,
        protected PolarTransaction $transaction
    ) {}

    public function owner()
    {
        return $this->owner;
    }

    public function transaction()
    {
        return $this->transaction;
    }

    public function total(): string
    {
        return $this->formatAmount($this->transaction->total);
    }

    public function tax(): string
    {
        return $this->formatAmount($this->transaction->tax);
    }

    public function download(array $data = []): Response
    {
        $filename = $data['filename'] ?? $this->filename();

        return response($this->pdf($data), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}.pdf\"",
        ]);
    }

    protected function filename(): string
    {
        return "invoice-{$this->transaction->polar_id}";
    }

    protected function pdf(array $data = []): string
    {
        return app('dompdf.wrapper')
            ->loadView('cashier-polar::invoice', array_merge($data, [
                'invoice' => $this,
                'owner' => $this->owner,
                'transaction' => $this->transaction,
            ]))
            ->stream();
    }

    protected function formatAmount(float $amount): string
    {
        return number_format($amount / 100, 2);
    }
}
