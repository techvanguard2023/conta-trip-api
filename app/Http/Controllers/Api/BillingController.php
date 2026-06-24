<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Stripe\StripeClient;

class BillingController extends Controller
{
    private StripeClient $stripe;

    public function __construct()
    {
        $this->stripe = new StripeClient(config('services.stripe.secret'));
    }

    public function checkout(Request $request)
    {
        $request->validate([
            'plan'        => 'required|string|in:avancado',
            'price_id'    => 'required|string',
            'success_url' => 'required|url',
            'cancel_url'  => 'required|url',
        ]);

        $user       = Auth::user();
        $customerId = $user->subscription?->stripe_customer_id;

        if (!$customerId) {
            $customer   = $this->stripe->customers->create(['email' => $user->email, 'name' => $user->name]);
            $customerId = $customer->id;
        }

        $session = $this->stripe->checkout->sessions->create([
            'customer'   => $customerId,
            'mode'       => 'subscription',
            'line_items' => [['price' => $request->price_id, 'quantity' => 1]],
            'success_url'=> $request->success_url . '?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $request->cancel_url,
            'metadata'   => ['user_id' => $user->id, 'plan' => $request->plan],
        ]);

        return response()->json(['checkout_url' => $session->url]);
    }

    public function confirm(Request $request)
    {
        $request->validate(['session_id' => 'required|string']);

        $session = $this->stripe->checkout->sessions->retrieve($request->session_id, [
            'expand' => ['subscription'],
        ]);

        if ($session->payment_status !== 'paid' && $session->status !== 'complete') {
            return response()->json(['message' => 'Pagamento ainda não confirmado.'], 402);
        }

        $userId = $session->metadata->user_id;
        $plan   = $session->metadata->plan;
        $sub    = $session->subscription;

        Subscription::updateOrCreate(
            ['user_id' => $userId],
            [
                'stripe_customer_id'     => $session->customer,
                'stripe_subscription_id' => $sub->id,
                'plan'                   => $plan,
                'status'                 => $sub->status,
                'current_period_end'     => $sub->current_period_end ? Carbon::createFromTimestamp($sub->current_period_end) : null,
            ]
        );

        return response()->json(['message' => 'Assinatura ativada com sucesso.', 'plan' => $plan]);
    }

    public function portal(Request $request)
    {
        $request->validate(['return_url' => 'required|url']);

        $user = Auth::user();

        if (!$user->subscription?->stripe_customer_id) {
            return response()->json(['message' => 'Nenhuma assinatura ativa encontrada.'], 404);
        }

        $portalSession = $this->stripe->billingPortal->sessions->create([
            'customer'   => $user->subscription->stripe_customer_id,
            'return_url' => $request->return_url,
        ]);

        return response()->json(['portal_url' => $portalSession->url]);
    }

    public function plans()
    {
        $plans = collect(config('billing.plans'))->map(function ($plan, $key) {
            return [
                'key'         => $key,
                'name'        => $plan['name'],
                'price'       => $plan['price'],
                'price_id'    => $plan['price_id'],
                'group_limit' => $plan['group_limit'],
                'features'    => $plan['features'],
            ];
        })->values();

        return response()->json(['plans' => $plans]);
    }

    public function status()
    {
        $user = Auth::user()->load('subscription');

        return response()->json([
            'has_premium'        => $user->hasActivePremium(),
            'plan'               => $user->subscription?->plan,
            'status'             => $user->subscription?->status,
            'current_period_end' => $user->subscription?->current_period_end,
            'active_groups_count'=> $user->activeGroupsCount(),
            'group_limit'        => config("billing.plans.{$user->subscription?->plan}.group_limit"),
        ]);
    }
}
