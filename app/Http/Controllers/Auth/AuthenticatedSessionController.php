<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
            // Credenciais do admin de desenvolvimento — nunca em produção.
            'devCredentials' => app()->isProduction() ? null : [
                'email' => 'admin@admin.com',
                'password' => 'admin',
            ],
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): SymfonyResponse
    {
        try {
            $request->authenticate();
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Falha no login', [
                'classe' => get_class($e),
                'erro' => $e->getMessage(),
                'arquivo' => $e->getFile().':'.$e->getLine(),
            ]);

            // TEMP-DEBUG: com APP_DEBUG=true (caso da Vercel agora), mostra a
            // causa real na tela para diagnosticar o 302. REVERTER após o fix,
            // junto com APP_ENV=production / APP_DEBUG=false no vercel.json.
            $mensagem = 'Não foi possível entrar agora. Tente novamente em instantes.';
            if (config('app.debug')) {
                // Primeiros frames do trace revelam QUAL Manager recebeu "".
                $frames = [];
                foreach (array_slice($e->getTrace(), 0, 6) as $f) {
                    $frames[] = ($f['class'] ?? '').($f['type'] ?? '').($f['function'] ?? '?')
                        .' @ '.basename($f['file'] ?? '?').':'.($f['line'] ?? '?');
                }
                $mensagem .= ' [DEBUG '.get_class($e).': '.$e->getMessage()
                    .' | cfg='.json_encode([
                        'log' => config('logging.default'),
                        'cache' => config('cache.default'),
                        'session' => config('session.driver'),
                        'auth' => config('auth.defaults.guard'),
                        'queue' => config('queue.default'),
                        'hash_default' => config('hashing.default'),
                        'hash_driver_key_exists' => array_key_exists('driver', (array) config('hashing')),
                        'hash_driver' => config('hashing.driver'),
                        'env_HASH_DRIVER' => getenv('HASH_DRIVER'),
                        'env_LOG_CHANNEL' => getenv('LOG_CHANNEL'),
                        'config_cached' => app()->configurationIsCached(),
                    ])
                    .' | trace='.implode(' <- ', $frames).']';
            }

            throw ValidationException::withMessages([
                'email' => $mensagem,
            ]);
        }

        $request->session()->regenerate();

        // O destino (/admin) é Blade, não Inertia: para POST via XHR o
        // cliente Inertia abriria um modal de erro em vez de navegar (a
        // cadeia termina em HTML sem cabeçalho X-Inertia).
        // Inertia::location() força recarregamento total (XHR) ou
        // redirect comum (form HTML, ex: sidebar).
        $url = $request->session()->pull('url.intended', route('admin.inicio', absolute: false));

        return Inertia::location($url);
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): SymfonyResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        // Mesmo motivo do login: o destino é Blade — recarregamento total
        // para XHR (Inertia), redirect comum para form HTML (ex: sidebar).
        return Inertia::location(route('agendafacil.acesso'));
    }
}
