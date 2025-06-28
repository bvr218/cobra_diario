<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\ControlSesion;
use Carbon\Carbon;

class CheckSystemClosed
{
    public function handle(Request $request, Closure $next)
    {
        $hoy = ControlSesion::hoy();

        if (! $hoy) {
            return $next($request);
        }

        $ahora = now()->format('H:i');

        // —————— AQUÍ LEEMOS EL VALOR "RAW" DEL CAMPO, NO LA RELACIÓN ——————
        $rawApertura = $hoy->getRawOriginal('hora_apertura');
        $rawCierre   = $hoy->getRawOriginal('hora_cierre');

        // Si por alguna razón no existieran, caemos a acceso normal
        if (is_null($rawApertura) || is_null($rawCierre)) {
            $rawApertura = $hoy->hora_apertura;
            $rawCierre   = $hoy->hora_cierre;
        }

        // Ahora sí parseamos con Carbon y sacamos H:i
        $horaApertura = Carbon::parse($rawApertura)->format('H:i');
        $horaCierre   = Carbon::parse($rawCierre)->format('H:i');

        $fueraDeHorario = ($ahora < $horaApertura) || ($ahora > $horaCierre);

        if (
            ($hoy->cerrado_manual || $fueraDeHorario)
            && Auth::check()
            && ! Auth::user()->hasRole('admin')
        ) {
            Auth::logout();

            return redirect('/admin/login');
        }

        return $next($request);
    }
}
