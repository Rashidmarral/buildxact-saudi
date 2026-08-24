<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Jobs\SendWebhookDelivery;
use App\Models\AuditLog;
use App\Models\Webhook;
use App\Rules\PublicHttpUrl;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    public function index()
    {
        $webhooks = Webhook::latest()->get();

        return view('user.settings.webhooks.index', [
            'webhooks' => $webhooks,
            'availableEvents' => Webhook::EVENTS,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'url' => ['required', 'string', 'max:2048', new PublicHttpUrl],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['string', 'in:'.implode(',', Webhook::EVENTS)],
        ]);

        $webhook = Webhook::create($data);

        AuditLog::record('webhook.create', $webhook, __('Registered webhook for :url', ['url' => $webhook->url]));

        return redirect()->route('app.settings.webhooks.show', $webhook)->with('status', __('Webhook created.'));
    }

    public function show(Webhook $webhook)
    {
        $deliveries = $webhook->deliveries()->paginate(20);

        return view('user.settings.webhooks.show', [
            'webhook' => $webhook,
            'deliveries' => $deliveries,
            'availableEvents' => Webhook::EVENTS,
        ]);
    }

    public function update(Request $request, Webhook $webhook)
    {
        $data = $request->validate([
            'url' => ['required', 'string', 'max:2048', new PublicHttpUrl],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['string', 'in:'.implode(',', Webhook::EVENTS)],
        ]);

        $webhook->update($data);

        AuditLog::record('webhook.update', $webhook, __('Updated webhook for :url', ['url' => $webhook->url]));

        return back()->with('status', __('Webhook updated.'));
    }

    public function toggle(Webhook $webhook)
    {
        $webhook->update(['is_active' => ! $webhook->is_active]);

        AuditLog::record(
            $webhook->is_active ? 'webhook.enable' : 'webhook.disable',
            $webhook,
            $webhook->is_active ? __('Enabled webhook for :url', ['url' => $webhook->url]) : __('Disabled webhook for :url', ['url' => $webhook->url])
        );

        return back()->with('status', $webhook->is_active ? __('Webhook enabled.') : __('Webhook disabled.'));
    }

    public function regenerateSecret(Webhook $webhook)
    {
        $webhook->update(['secret' => \Illuminate\Support\Str::random(40)]);

        AuditLog::record('webhook.regenerate_secret', $webhook, __('Regenerated signing secret for :url', ['url' => $webhook->url]));

        return back()->with('status', __('Signing secret regenerated. Update your endpoint to verify with the new secret.'));
    }

    public function sendTest(Webhook $webhook)
    {
        SendWebhookDelivery::dispatchSync($webhook->id, 'webhook.test', [
            'message' => 'This is a test delivery from Daftari.',
        ]);

        return back()->with('status', __('Test event sent — check the delivery log below.'));
    }

    public function destroy(Webhook $webhook)
    {
        $url = $webhook->url;
        $webhook->delete();

        AuditLog::record('webhook.delete', null, __('Deleted webhook for :url', ['url' => $url]));

        return redirect()->route('app.settings.webhooks.index')->with('status', __('Webhook deleted.'));
    }
}
