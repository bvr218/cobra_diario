<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Permisos para el rol de Admin
        $admin_permissions = [
            'roles.index', 'roles.view', 'roles.create', 'roles.edit', 'roles.delete',
            'prestamos.index', 'prestamos.create', 'prestamos.edit', 'prestamos.delete',
            'abonos.index', 'abonos.create', 'abonos.edit', 'abonos.delete',
            'users.index', 'users.view', 'users.create', 'users.edit', 'users.delete',
            'clientes.index', 'clientes.create', 'clientes.edit', 'clientes.delete',
            'map.index', 'registro.index', 'registrarPago.index',
            'frecuencias.index', 'frecuencias.view', 'frecuencias.create', 'frecuencias.edit', 'frecuencias.delete',
            'gastos.index', 'gastos.create', 'gastos.edit', 'gastos.delete',
            'controlSesion.index', 'controlSesion.view', 'controlSesion.create', 'controlSesion.edit', 'controlSesion.delete', 'controlSesion.ignore',
            'asignarAgentePrestamo.view', 'prestamosRefinanciar.view', 'autorizarGastos.view',
            'historialMovimiento.index',
            'dineroBase.index', 'dineroBase.view', 'dineroBase.create', 'dineroBase.edit', 'dineroBase.delete',
            'stats.index', 'password.index',
            'refinanciamientos.index', 'refinanciamientos.edit', 'refinanciamientos.delete',
            'registroliquidaciones.index', 'registroliquidaciones.edit', 'registroliquidaciones.delete',
            'notificaciones.index',
            'tipoGastos.create', 'tipoGastos.edit', 'tipoGastos.delete', 'tipoGastos.index',
            'liquidaciones-mensuales.index'
        ];
        
        // Permisos para el rol de Oficina
        $oficina_permissions = [
            'prestamos.create', 'abonos.edit', 'users.create', 'clientes.create', 'clientes.edit',
            'gastos.view', 'gastos.create', 'gastos.edit', 'gastos.delete',
            'prestamosOficina.index', 'clientesOficina.index', 'asignarAgentePrestamo.view',
            'activarPrestamosOficina.view', 'abonosOficina.index', 'registrarPagoOficina.index',
            'mapOficina.index', 'gastosOficina.index', 'prestamosRefinanciar.view', 'usersOficina.index'
        ];

        // Permisos para el rol de Agente
        $agente_permissions = [
            'prestamos.view', 'prestamos.create', 'abonos.view', 'clientes.view', 'clientes.create',
            'clientes.edit', 'registrarPago.view', 'gastos.view', 'ruta.index',
            'prestamosRefinanciar.view', 'stats-agente.index'
        ];
        
        // Permisos para el rol de Supervisor
        $supervisor_permissions = [
            'registro.view'
        ];

        // Asignar permisos a los roles
        $role_admin = Role::findByName('admin');
        $role_admin->syncPermissions($admin_permissions);

        $role_oficina = Role::findByName('oficina');
        $role_oficina->syncPermissions($oficina_permissions);
        
        $role_agente = Role::findByName('agente');
        $role_agente->syncPermissions($agente_permissions);
        
        $role_supervisor = Role::findByName('supervisor');
        $role_supervisor->syncPermissions($supervisor_permissions);
    }
}