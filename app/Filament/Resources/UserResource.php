<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Filters\SelectFilter;
use Spatie\Permission\Models\Role;
use Filament\Facades\Filament;
use Filament\Tables\Actions\Action as TablesAction;

// --- Importaciones para SoftDeletes ---
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Actions\ForceDeleteAction;
use Filament\Tables\Actions\RestoreAction;
use Filament\Tables\Actions\ForceDeleteBulkAction;
use Filament\Tables\Actions\RestoreBulkAction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class UserResource extends Resource
{
    protected static ?string $model = User::class;
    protected static ?string $label = 'Agente';
    protected static ?string $pluralLabel = 'Agentes';
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationGroup = 'Administración de Usuarios';

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('name')
                ->label('Nombre')
                ->required()
                ->unique(ignoreRecord: true)
                ->maxLength(30)
                ->helperText('Máximo 30 caracteres'),

            TextInput::make('email')
                ->label('Correo electrónico')
                ->required()
                ->email()
                ->unique(ignoreRecord: true)
                ->maxLength(50),

            TextInput::make('password')
                ->label('Contraseña')
                ->password()
                ->required(fn (string $context) => $context === 'create')
                ->minLength(4)
                ->maxLength(4)
                ->rule(['confirmed', 'max:50'])
                ->autocomplete(false)
                ->helperText('Mínimo 8 caracteres, máximo 30')
                ->dehydrated(fn ($state) => filled($state)),

            TextInput::make('password_confirmation')
                ->label('Confirmar contraseña')
                ->password()
                ->required(fn (string $context) => $context === 'create')
                ->minLength(4)
                ->maxLength(4)
                ->dehydrated(false)
                ->autocomplete(false),

            Select::make('role')
                ->label('Rol')
                ->reactive()
                ->options(Role::pluck('name', 'id'))
                ->default(function () {
                    $authUser = Filament::auth()->user();
                    return $authUser->hasRole('admin')
                        ? null
                        : Role::where('name', 'agente')->value('id');
                })
                ->disabled(function () {
                    $authUser = Filament::auth()->user();
                    return ! $authUser->hasRole('admin');
                })
                ->required()
                ->afterStateHydrated(function ($state, callable $set, $record) {
                    if ($record) {
                        $firstRole = $record->roles->first();
                        $set('role', $firstRole?->id);
                    }
                })
                ->afterStateUpdated(function ($state, callable $set) {
                    $roleName = Role::find($state)?->name;
                    if (in_array($roleName, ['admin', 'oficina'])) {
                        $set('oficina_id', null);
                    }
                }),

            Select::make('oficina_id')
                ->label('Oficina')
                ->reactive()
                ->preload()
                ->searchable()
                ->required()
                ->options(function (callable $get, ?User $record) {
                    $query = User::role('oficina');

                    if ($record) {
                        $query->where('id', '!=', $record->id);
                    }

                    return $query->pluck('name', 'id');
                })
                ->default(function () {
                    $authUser = Filament::auth()->user();
                    return $authUser->hasRole('oficina') ? $authUser->id : null;
                })
                ->disabled(function () {
                    $authUser = Filament::auth()->user();
                    return $authUser->hasRole('oficina');
                })
                ->visible(function (callable $get) {
                    $roleName = \Spatie\Permission\Models\Role::find($get('role'))?->name;
                    return !in_array($roleName, ['admin', 'oficina']);
                })
                ->afterStateHydrated(function (callable $set, $state, callable $get) {
                    $roleName = \Spatie\Permission\Models\Role::find($get('role'))?->name;
                    if ($roleName === 'oficina') {
                        $set('oficina_id', null);
                    }
                }),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nombre')->sortable()->searchable(),
                TextColumn::make('roles.name')
                    ->label('Rol')
                    ->badge()
                    ->getStateUsing(fn ($record) => $record->roles->first()?->name ?? '—')
                    ->colors([
                        'success' => 'admin',
                        'info'    => 'oficina',
                        'warning' => 'agente',
                        'morado'  => 'Supervisor',
                    ])
                    ->sortable()
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('email')->label('Correo')->sortable()->searchable()->toggleable(),
                TextColumn::make('oficina.name')->label('Oficina')->sortable()->searchable()->toggleable(),
                TextColumn::make('created_at')->label('Fecha de registro')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')->label('Fecha de eliminación')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('roles')
                    ->label('Filtrar por Rol')
                    ->options(Role::pluck('name', 'name'))
                    ->query(fn ($query, $data) => $data['value']
                        ? $query->whereHas('roles', fn ($q) => $q->where('name', $data['value']))
                        : $query),
                // // --- Filtro para ver eliminados ---
                // TrashedFilter::make(),
            ])
            ->paginationPageOptions([10, 25, 50, 100, 500])
            ->defaultPaginationPageOption(25)
            ->defaultSort('created_at', 'desc')
            ->actions([
                EditAction::make(),
                DeleteAction::make()
                    ->visible(fn () => auth()->user()->can('users.delete')),
                
                // --- Acciones para restaurar y forzar borrado ---
                ForceDeleteAction::make(),
                RestoreAction::make(),

                TablesAction::make('verHistorialUsuario')
                    ->label('Ver Historial')
                    ->icon('heroicon-o-document-magnifying-glass')
                    ->color('info')
                    ->visible(fn(): bool => auth()->user()->can('users.index'))
                    ->modalHeading(fn(User $record): string => 'Historial de ' . ($record->roles->first()?->name ?? 'Usuario') . ': ' . $record->name)
                    ->modalWidth('4xl')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar')
                    ->modalContent(fn(User $record) => view(
                        'filament.forms.components.user-historial-viewer',
                        ['user' => $record->id]
                    )),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn () => auth()->user()->can('users.delete')),
                    // --- Acciones en lote para restaurar y forzar borrado ---
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    // --- Método para incluir registros con SoftDeletes ---
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit'   => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}