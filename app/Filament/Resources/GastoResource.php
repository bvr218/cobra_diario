<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GastoResource\Pages;
use App\Models\Gasto;
use App\Models\User; // Use App\Models\User
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Components;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns;
use Filament\Tables\Actions\Action;
use Illuminate\Support\Facades\Auth;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\DeleteAction; // Add this for single record delete action
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Columns\ImageColumn;

class GastoResource extends Resource
{
    protected static ?string $model = Gasto::class;
    protected static ?string $label = 'Gasto';
    protected static ?string $pluralLabel = 'Gastos';
    protected static ?string $navigationGroup = 'Registros';
    protected static ?string $navigationIcon = 'iconsax-two-money-send';


    public static function canAccess(): bool
    {
        // Asegúrate de que el permiso 'gastos.index' exista y esté asignado correctamente
        // o ajusta el permiso según tus necesidades.
        return Auth::user()?->can('gastos.index') || Auth::user()?->can('gastos.view') || Auth::user()?->can('gastosOficina.index') ?? false;
    }

    // Opcional: si quieres que aparezca en la navegación solo si tiene permiso
    public static function shouldRegisterNavigation(): bool
    {
        // Mismo permiso que canAccess o uno específico para la navegación
        return Auth::user()?->can('gastos.index') || Auth::user()?->can('gastos.view') || Auth::user()?->can('gastosOficina.index') ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Components\Select::make('user_id')
                    ->label('Usuario')
                    ->required()
                    ->default(fn () => auth()->id())
                    ->options(function () {
                        $user = auth()->user();

                        if ($user->can('gastos.view')) {
                            return User::where('id', $user->id)->pluck('name', 'id');
                        }

                        if ($user->can('gastosOficina.index')) {
                            return User::where('oficina_id', $user->oficina_id)->pluck('name', 'id');
                        }

                        return User::pluck('name', 'id');
                    })
                    ->disabled(function (?Gasto $record) {
                        $user = auth()->user();

                        // Disable for users with only 'gastos.view' permission
                        if ($user->can('gastos.view')) {
                            return true;
                        }

                        // Disable if authorized and user doesn't have edit permissions
                        return $record?->autorizado &&
                            !$user->can('gastos.index') &&
                            !$user->can('gastosOficina.index');
                    }),
                Components\Select::make('tipo_gasto_id')
                    ->label('Tipo de Gasto')
                    ->required()
                    ->relationship('tipoGasto', 'nombre')
                    ->searchable()
                    ->preload(),
                Components\TextInput::make('valor')
                    ->label('Valor')
                    ->numeric()
                    ->required()
                    ->minValue(1)
                    ->disabled(fn (?Gasto $record) =>
                        $record?->autorizado &&
                        (!auth()->user()->can('gastos.index') && !auth()->user()->can('gastosOficina.index'))
                    ),
                Components\Textarea::make('informacion')
                    ->label('Información')
                    ->required()
                    ->maxLength(255)
                    ->disabled(fn (?Gasto $record) =>
                        $record?->autorizado &&
                        (!auth()->user()->can('gastos.index') && !auth()->user()->can('gastosOficina.index'))
                    ),
                Components\FileUpload::make('imagen')
                    ->label('Imágenes')
                    ->multiple()
                    ->preserveFilenames()
                    ->enableOpen()
                    ->enableDownload()
                    ->disk('public')
                    ->directory('gastos')
                    ->acceptedFileTypes(['image/*'])
                    ->disabled(fn (?Gasto $record) =>
                        $record?->autorizado &&
                        (!auth()->user()->can('gastos.index') && !auth()->user()->can('gastosOficina.index'))
                    ),
                Components\Placeholder::make('autorizado')
                    ->label('Autorizado')
                    ->content(fn (?Gasto $record) => $record
                        ? ($record->autorizado ? 'Sí' : 'No')
                        : 'N/A'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Columns\TextColumn::make('user.name')
                    ->label('Usuario')
                    ->sortable()
                    ->searchable(),
                Columns\TextColumn::make('tipoGasto.nombre')
                    ->label('Tipo de Gasto')
                    ->sortable()
                    ->searchable(),
                Columns\TextColumn::make('valor')
                    ->label('Valor')
                    ->formatStateUsing(fn ($state) => '$' . number_format($state, 0, ',', '.') . ' COP')
                    ->sortable()
                    ->searchable(),
                Columns\TextColumn::make('informacion')
                    ->label('Información')
                    ->limit(80)
                    ->sortable()
                    ->searchable(),
                Columns\ImageColumn::make('imagen')
                    ->label('Imágenes'),
                Columns\BadgeColumn::make('autorizado')
                    ->label('Autorizado')
                    ->formatStateUsing(fn (bool $state) => $state ? 'Sí' : 'No')
                    ->color(fn (bool $state) => $state ? 'success' : 'danger')
                    ->sortable(),
                Columns\TextColumn::make('created_at')
                    ->label('Fecha de creación')
                    ->dateTime('d/m/Y H:i')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),
                Columns\TextColumn::make('updated_at')
                    ->label('Fecha de actualización')
                    ->dateTime('d/m/Y H:i')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),
            ])
            ->paginationPageOptions([10, 25, 50, 100, 500])
            ->defaultPaginationPageOption(25)
            ->defaultSort('created_at', 'desc')
            ->actions([
                Action::make('autorizar_gasto')
                    ->label('Autorizar Gasto')
                    ->requiresConfirmation()
                    ->color('success')
                    ->icon('heroicon-m-check-circle')
                    ->visible(fn (Gasto $record): bool =>
                        !$record->autorizado
                        && auth()->user()->can('autorizarGastos.view')
                    )
                    ->action(function (Gasto $record) {
                        $record->update(['autorizado' => true]);
                        Notification::make()
                            ->success()
                            ->title('Gasto autorizado correctamente')
                            ->send();
                    }),
                EditAction::make()
                    ->visible(fn (Gasto $record): bool =>
                        auth()->user()->can('gastos.index') || auth()->user()->can('gastosOficina.index') || !$record->autorizado
                    ),
                // Show delete action only if user has 'gastos.delete' permission
                DeleteAction::make()
                    ->visible(fn (): bool => auth()->user()->can('gastos.delete')),
            ])
            ->bulkActions([
                // Show bulk delete action only if user has 'gastos.delete' permission
                DeleteBulkAction::make()
                    ->visible(fn (): bool => auth()->user()->can('gastos.delete')),
            ]);
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
            'index' => Pages\ListGastos::route('/'),
            'create' => Pages\CreateGasto::route('/create'),
            'edit' => Pages\EditGasto::route('/{record}/edit'),
        ];
    }

    /**
     * Define the global authorization for deleting a record.
     * This method is called by Filament to check if a user can delete.
     */
    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()->can('gastos.delete');
    }
}