<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Array de permisos
        $permissions = [
            'abonos.index',
            'abonos.view',
            'map.view',
            'map.index',
            'mapOficina.index',
            'abonos.create',
            'abonos.edit',
            'abonos.delete',
            'abonosOficina.index',
            'prestamos.index',
            'prestamos.view',
            'prestamos.create',
            'prestamos.edit',
            'prestamos.delete',
            'prestamosOficina.index',
            'prestamosRefinanciar.view',
            'asignarAgentePrestamo.view',
            'activarPrestamosOficina.view',
            'users.index',
            'users.view',
            'users.create',
            'users.edit',
            'users.delete',
            'usersOficina.index',
            'clientes.index',
            'clientes.view',
            'clientes.create',
            'clientes.edit',
            'clientes.delete',
            'clientesOficina.index',
            'roles.index',
            'roles.view',
            'roles.create',
            'roles.edit',
            'roles.delete',
            'registro.index',
            'registro.view',
            'registrarPago.index',
            'registrarPago.view',
            'registrarPagoOficina.index',
            'frecuencias.index',
            'frecuencias.view',
            'frecuencias.create',
            'frecuencias.edit',
            'frecuencias.delete',
            'limpiarAgente.view',
            'gastos.index',
            'gastos.view',
            'gastos.create',
            'gastos.edit',
            'gastos.delete',
            'gastosOficina.index',
            'autorizarGastos.view',
            'tipoGastos.index',
            'tipoGastos.create',
            'tipoGastos.edit',
            'tipoGastos.delete',
            'controlSesion.index',
            'controlSesion.view',
            'controlSesion.create',
            'controlSesion.edit',
            'controlSesion.delete',
            'controlSesion.ignore',
            'ruta.index',
            'dineroBase.index',
            'dineroBase.view',
            'dineroBase.create',
            'dineroBase.edit',
            'dineroBase.delete',
            'historialMovimiento.index',
            'stats.index',
            'stats-agente.index',
            'password.index',
            'refinanciamientos.index',
            'refinanciamientos.view',
            'refinanciamientos.edit',
            'refinanciamientos.create',
            'refinanciamientos.delete',
            'registroliquidaciones.index',
            'registroliquidaciones.delete',
            'registroliquidaciones.edit',
            'notificaciones.index',
            'liquidaciones-mensuales.index',
        ];

        // Crear permisos
        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }
    }
}