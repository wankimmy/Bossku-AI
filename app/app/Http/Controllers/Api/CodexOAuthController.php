<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Llm\ProviderRegistry;
use App\Services\OAuth\CodexOAuthService;
use Illuminate\Http\Request;

class CodexOAuthController extends Controller
{
    public function __construct(
        protected CodexOAuthService $codexOAuth,
        protected ProviderRegistry $providerRegistry,
    ) {}

    public function status()
    {
        return response()->json(array_merge(
            $this->codexOAuth->status(),
            ['configured' => $this->codexOAuth->isConfigured()],
        ));
    }

    public function authorize(Request $request)
    {
        try {
            $begin = $this->codexOAuth->beginAuthorization();

            return redirect()->away($begin['url']);
        } catch (\Throwable $e) {
            $return = (string) config('bossku_oauth.codex.frontend_return_url');

            return redirect()->away($return.'?codex=error&message='.urlencode($e->getMessage()));
        }
    }

    public function callback(Request $request)
    {
        $return = (string) config('bossku_oauth.codex.frontend_return_url');

        if ($request->has('error')) {
            return redirect()->away($return.'?codex=error&message='.urlencode((string) $request->query('error_description', $request->query('error', 'denied'))));
        }

        $code = (string) $request->query('code', '');
        $state = (string) $request->query('state', '');

        if ($code === '' || $state === '') {
            return redirect()->away($return.'?codex=error&message='.urlencode('Missing authorization code.'));
        }

        try {
            $this->codexOAuth->handleCallback($code, $state);
            $this->providerRegistry->refresh();

            return redirect()->away($return.'?codex=connected');
        } catch (\Throwable $e) {
            return redirect()->away($return.'?codex=error&message='.urlencode($e->getMessage()));
        }
    }

    public function disconnect()
    {
        $this->codexOAuth->disconnect();
        $this->providerRegistry->refresh();

        return response()->json(['connected' => false]);
    }
}
