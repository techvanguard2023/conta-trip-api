<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

class StripeWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $payload   = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $secret    = config('services.stripe.webhook_secret');

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $secret);
        } catch (SignatureVerificationException $e) {
            return response()->json(['message' => 'Assinatura inválida.'], 400);
        }

        match ($event->type) {
            'checkout.session.completed'    => $this->handleCheckoutCompleted($event->data->object),
            'customer.subscription.updated' => $this->handleSubscriptionUpdated($event->data->object),
            'customer.subscription.deleted' => $this->handleSubscriptionDeleted($event->data->object),
            'invoice.payment_failed'        => $this->handlePaymentFailed($event->data->object),
            default                         => null,
        };

        return response()->json(['received' => true]);
    }

    private function handleCheckoutCompleted($session): void
    {
        if ($session->mode !== 'subscription') return;

        $userId = $session->metadata->user_id ?? null;
        $plan   = $session->metadata->plan ?? null;

        if (!$userId || !$plan) return;

        Subscription::updateOrCreate(
            ['stripe_subscription_id' => $session->subscription],
            [
                'user_id'            => $userId,
                'stripe_customer_id' => $session->customer,
                'plan'               => $plan,
                'status'             => 'active',
            ]
        );
    }

    private function handleSubscriptionUpdated($subscription): void
    {
        Subscription::where('stripe_subscription_id', $subscription->id)
            ->update([
                'status'             => $subscription->status,
                'current_period_end' => Carbon::createFromTimestamp($subscription->current_period_end),
            ]);
    }

    private function handleSubscriptionDeleted($subscription): void
    {
        Subscription::where('stripe_subscription_id', $subscription->id)
            ->update(['status' => 'canceled']);
    }

    private function handlePaymentFailed($invoice): void
    {
        Subscription::where('stripe_subscription_id', $invoice->subscription)
            ->update(['status' => 'past_due']);
    }
}
