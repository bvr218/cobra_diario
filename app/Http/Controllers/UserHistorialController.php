<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User; // Asegúrate de importar el modelo User
use Maatwebsite\Excel\Facades\Excel; // Si vas a usar exportaciones a Excel
use Barryvdh\DomPDF\Facade\Pdf; // Para exportaciones a PDF
use Illuminate\Support\Str; // Para generar slugs amigables
use Carbon\Carbon; // Para manejo de fechas, si lo necesitas en tu vista o lógica
use App\Exports\UserHistorialExport; // Si vas a usar esta clase para Excel, debes crearla.

class UserHistorialController extends Controller
{
    /**
     * Descarga el historial completo en Excel para un usuario específico.
     *
     * @param  \App\Models\User  $user El usuario cuyo historial se va a exportar.
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function exportExcel(User $user)
    {
        // 1) Cargamos al usuario con los préstamos y la relación de cliente necesaria para el export.
        $userConDatos = User::with([
            'prestamos' => function ($q) {
                $q->orderBy('created_at', 'desc')
                  ->with('cliente'); // Solo necesitamos el cliente del préstamo
            }
        ])->findOrFail($user->id);

        // 2) Generamos el Excel utilizando la clase de exportación.
        // UserHistorialExport ahora solo necesita el objeto User y se encarga
        // de obtener los préstamos con la información correcta.
        // El constructor de UserHistorialExport solo espera $user.
        // Los parámetros adicionales $prestamos, $abonos, $refis no son utilizados por el constructor actual.
        // Si fueran necesarios, el constructor de UserHistorialExport debería ser ajustado.
        // Por ahora, asumimos que UserHistorialExport obtiene los datos a partir del $user.

        $slugUser = Str::slug($userConDatos->name);
        $fileName = 'historial_usuario_' . $slugUser . '.xlsx';

        return Excel::download(
            new UserHistorialExport($userConDatos), // Pasamos solo el usuario
            $fileName
        );
    }

    /**
     * Descarga el historial completo en PDF para un usuario específico.
     *
     * @param  \App\Models\User  $user El usuario cuyo historial se va a exportar.
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function exportPdf(User $user)
    {
        // 1) Cargamos al usuario con los préstamos y la relación de cliente.
        $userConDatos = User::with([
            'prestamos' => function ($q) {
                $q->orderBy('created_at', 'desc')
                  ->with('cliente'); // Solo necesitamos el cliente del préstamo
            }
        ])->findOrFail($user->id);

        // 2) Extraemos los préstamos. No necesitamos abonos ni refinanciamientos para esta vista.
        // Ordenamos los préstamos por el nombre del cliente (A-Z)
        $prestamos = $userConDatos->prestamos->sortBy(function ($prestamo) {
            return $prestamo->cliente->nombre ?? '';
        });

        // 3) Preparamos los datos para la vista PDF.
        $data = [
            'user'             => $userConDatos,
            'prestamos'        => $prestamos,
            // 'abonos'           => collect(), // Ya no se envían abonos
            // 'refinanciaciones' => collect(), // Ya no se envían refinanciaciones
            'carbon'           => Carbon::class, // Útil si necesitas funciones de Carbon directamente en la vista
        ];

        // 4) Cargamos la vista de Blade desde 'resources/views/exports/user-historial-pdf.blade.php'
        $pdf = Pdf::loadView('exports.user-historial-pdf', $data)
                  ->setPaper('A4', 'portrait'); // Cambiado a portrait ya que son menos columnas

        // 5) Generamos un nombre de archivo amigable para la descarga.
        $slugUser = Str::slug($userConDatos->name);
        $fileName = 'historial_usuario_' . $slugUser . '.pdf';

        // 6) Retornamos la respuesta de descarga del PDF.
        return response()->streamDownload(fn() => print($pdf->output()), $fileName);
    }
}