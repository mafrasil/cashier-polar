<?php

namespace Mafrasil\CashierPolar\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Mafrasil\CashierPolar\WebhookHandler\ProcessPolarWebhook;

class WebhookController extends Controller
{
    public function __invoke(Request $request)
    {
        try {
            dispatch(new ProcessPolarWebhook($request->input()));

            return response()->json(['message' => 'ok']);
        } catch (\Throwable $e) {
            logger()->error('Webhook processing failed: '.$e->getMessage());

            return response()->json(['message' => 'Webhook processing failed'], 400);
        }
    }
}
