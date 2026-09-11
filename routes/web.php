use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json(['message' => 'SIGS Admin API - voir /api/ping']);
});

// Force la route csrf-cookie si le package ne l'enregistre pas automatiquement en production
Route::get('/sanctum/csrf-cookie', \Laravel\Sanctum\Http\Controllers\CsrfCookieController::class . '@show');
