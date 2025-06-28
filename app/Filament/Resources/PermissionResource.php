<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PermissionResource\Pages;
use App\Filament\Resources\PermissionResource\RelationManagers;
use Spatie\Permission\Models\Permission;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PermissionResource extends Resource
{
    protected static ?string $model = Permission::class;

    protected static ?string $navigationIcon = 'untitledui-key';

    protected static ?string $navigationGroup = 'Sistema';

    protected static ?string $pluralLabel = 'Permisos';

    protected static ?string $label = 'Permiso';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nombre del permiso')
                    ->required()
                   
                    ->maxLength(255),
                Forms\Components\Select::make('guard_name')
                    ->label('Guard')
                    ->options([
                        'web' => 'web',
                    ])
                    ->default('web')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre del permiso')
                    ->sortable()
                    ->formatStateUsing(function ($state) {
                        return self::changePermissionName($state);
                    })
                    ->searchable(),
                Tables\Columns\TextColumn::make('guard_name')
                    ->label('Guard')
                    ->sortable()
                    ->searchable()
                    ->toggleable(),
            ])
            ->filters([
                //
            ])
            ->paginationPageOptions([10, 25, 50, 100, 500])
            ->defaultPaginationPageOption(25)
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    // Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function changePermissionName($name)
    {
        $permission = [
            'abonos.index' => 'Ver Todos Los Abonos',
            'abonos.view' => 'Ver unicamente Los Abonos De Clientes Asignados',
            'map.view' => 'Ver Mapa Con Clientes Asignados',
            'map.index' => 'Ver Mapa De Todos Los Clientes',
            'mapOficina.index' => 'Ver Mapa Con Clientes Asignados A Oficina',
            'abonos.create' => 'Crear Abono',
            'abonos.edit' => 'Editar abono',
            'abonos.delete' => 'Eliminar Abono',
            'abonosOficina.index' => 'Ver Abonos De Clientes Asignados A Oficina',
            'prestamos.index' => 'Ver Todos Los Prestamos',
            'prestamos.view' => 'Ver Unicamente Mis Prestamos Asignados',
            'prestamos.create' => 'Crear Prestamo',
            'prestamos.edit' => 'Editar Prestamo',
            'prestamos.delete' => 'Eliminar Prestamo',
            'prestamosOficina.index' => 'Ver Todos Los Prestamos Asignados A Oficina',
            'prestamosRefinanciar.view' => 'Refinanciar Prestamo',
            'asignarAgentePrestamo.view' => 'Asignar Agente A Prestamo',
            'activarPrestamosOficina.view' => 'Activar Todos Los Prestamos De Oficina',
            'users.index' => 'Ver Todos Los Agentes',
            'users.view' => 'Ver Agente',
            'users.create' => 'Crear Agente',
            'users.edit' => 'Editar Agente',
            'users.delete' => 'Eliminar Agente',
            'usersOficina.index' => 'Ver Todos Los Agentes Asignados A Oficina',
            'clientes.index' => 'Ver Todos Los clientes',
            'clientes.view' => 'Ver Unicamente Mis clientes Asignados',
            'clientes.create' => 'Crear cliente',
            'clientes.edit' => 'Editar cliente',
            'clientes.delete' => 'Eliminar cliente',
            'clientesOficina.index' => 'Ver Todos Los clientes Asignados A Oficina',
            'roles.delete' => 'Eliminar permiso',
            'roles.index' => 'Ver Todos Los roles',
            'roles.view' => 'Ver role',
            'roles.create' => 'Crear role',
            'roles.edit' => 'Editar role',
            'roles.delete' => 'Eliminar role',
            'registro.index' => 'Ver Todas Las Liquidaciones',
            'registro.view' => 'Ver Las Liquidaciones de Hoy',
            'registrarPago.index' => 'Registrar Pago a Cualquier Cliente',
            'registrarPago.view' => 'Registrar Pago a Clientes Asignados',
            'registrarPagoOficina.index' => 'Registrar Pago a Clientes Asignados a Oficina',
            'frecuencias.index' => 'Ver Todas Las Frecuencias',
            'frecuencias.view' => 'Ver Frecuencia',
            'frecuencias.create' => 'Crear Frecuencia',
            'frecuencias.edit' => 'Editar Frecuencia',
            'frecuencias.delete' => 'Eliminar Frecuencia',
            'limpiarAgente.view' => 'Limpiar Agente',
            'gastos.index' => 'Ver Todos Los Gastos',
            'gastos.view' => 'Ver Unicamente Mis Gastos',
            'gastos.create' => 'Crear Gasto',
            'gastos.edit' => 'Editar Gasto',
            'gastos.delete' => 'Eliminar Gasto',
            'gastosOficina.index' => 'Ver Gastos Asignados A Oficina',
            'autorizarGastos.view' => 'Autorizar Gastos',
            'tipoGastos.index' => 'Ver Pagina de Tipo de Gastos',
            'tipoGastos.create' => 'Crear Tipo de Gastos',
            'tipoGastos.edit' => 'Editar Tipo de Gastos',
            'tipoGastos.delete' => 'Eliminar Tipo de Gastos',
            'controlSesion.index' => 'Ver Todos Los Horarios',
            'controlSesion.view' => 'Ver Horario',
            'controlSesion.create' => 'Crear Horario',
            'controlSesion.edit' => 'Editar Horario',
            'controlSesion.delete' => 'Eliminar Horario',
            'ruta.index' => 'Ver Las Rutas',
            'dineroBase.index' => 'Ver todos los Dinero Base',
            'dineroBase.view' => 'Ver Dinero Base',
            'dineroBase.create' => 'Crear Dinero Base',
            'dineroBase.edit' => 'Editar Dinero Base',
            'dineroBase.delete' => 'Eliminar Dinero Base',
            'historialMovimiento.index' => 'Ver Historial De Movimientos',
            'stats.index' => 'Ver Estadisticas',
            'password.index' => 'Ver pagina de Cambiar contraseña',
            'refinanciamientos.index' => 'Ver la pagina de Refinanciamientos',
            'refinanciamientos.view' => 'Ver unicamente mis refinanciaciones',
            'refinanciamientos.edit' => 'Editar refinanciaciones',
            'refinanciamientos.create' => 'Crear Refinanciaciones',
            'refinanciamientos.delete' => 'Eliminar Refinanciaciones',
            'registroliquidaciones.index' => 'Ver Registro de Liquidaciones',
            'registroliquidaciones.delete' => 'Eliminar Registro de Liquidaciones',
            'registroliquidaciones.edit' => 'Editar los Registro de Liquidaciones',
            'notificaciones.index' => 'Ver Pagina de Notificaciones',



            // Add more permissions as needed
        ];
        return $permission[$name] ?? $name;
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPermissions::route('/'),
            'create' => Pages\CreatePermission::route('/create'),
            'edit' => Pages\EditPermission::route('/{record}/edit'),
        ];
    }
}
