<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NotificationResource\Pages;
// use App\Filament\Resources\NotificationResource\RelationManagers; // Probablemente no necesites relation managers aquí
use App\Models\Notification; // Asegúrate que es App\Models\Notification y que extiende BaseNotification
use App\Models\User; // Para filtrar por usuario
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
// use Illuminate\Database\Eloquent\SoftDeletingScope; // Las notificaciones de Laravel no usan SoftDeletes por defecto
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn; // Para el estado 'leído'
use Filament\Tables\Filters\SelectFilter; // Para filtrar por usuario
use Filament\Tables\Filters\TernaryFilter; // Para filtrar leídas/no leídas
use Illuminate\Support\Str; // Para formatear el tipo

class NotificationResource extends Resource
{
    protected static ?string $model = Notification::class; // Correcto

    protected static ?string $navigationIcon = 'heroicon-o-bell-alert'; // Un ícono más apropiado

    protected static ?string $navigationGroup = 'Sistema'; // Opcional, para agrupar en el menú
    protected static ?int $navigationSort = 100; // Opcional, para ordenar en el menú

    // Las notificaciones son generalmente solo para verlas, no para crearlas o editarlas desde aquí
    // por lo que podemos deshabilitar esas acciones.
    public static function canCreate(): bool
    {
        return false;
    }

    // El formulario es principalmente para la vista (si permites "EditAction" para ver detalles)
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('id')
                    ->label('ID de Notificación')
                    ->disabled()
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('type')
                    ->label('Tipo de Notificación')
                    ->formatStateUsing(fn (?string $state): string => $state ? Str::afterLast($state, '\\') : '-')
                    ->disabled(),
                Forms\Components\MorphToSelect::make('notifiable')
                    ->label('Notificable (Usuario/Entidad)')
                    ->types([
                        Forms\Components\MorphToSelect\Type::make(User::class)
                            ->titleAttribute('name'), // Atributo a mostrar del modelo User
                        // Añade otros modelos notificables si los tienes
                    ])
                    ->disabled(),
                Forms\Components\DateTimePicker::make('created_at')
                    ->label('Fecha de Creación')
                    ->disabled(),
                Forms\Components\DateTimePicker::make('read_at')
                    ->label('Fecha de Lectura')
                    ->disabled(),
                Forms\Components\KeyValue::make('data') // Muestra los datos JSON como clave-valor
                    ->label('Datos de la Notificación')
                    ->columnSpanFull()
                    ->disabled()
                    ->reorderable(false)
                    ->deletable(false)
                    ->addable(false),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true), // Oculto por defecto, pero se puede mostrar

                IconColumn::make('read_at')
                    ->label('Leída')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-envelope')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->sortable(),

                TextColumn::make('type')
                    ->label('Tipo')
                    ->formatStateUsing(fn (string $state): string => Str::afterLast($state, '\\')) // Muestra solo el nombre de la clase
                    ->searchable()
                    ->sortable(),

                TextColumn::make('data.title') // Accede al 'title' dentro de la columna 'data'
                    ->label('Título')
                    ->placeholder('N/A')
                    ->limit(30)
                    ->tooltip(fn (Notification $record): ?string => $record->data['title'] ?? null),

                TextColumn::make('data.message') // Accede al 'message' dentro de la columna 'data'
                    ->label('Mensaje')
                    ->placeholder('N/A')
                    ->limit(50)
                    ->wrap()
                    ->tooltip(fn (Notification $record): ?string => $record->data['message'] ?? null),

                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('read_at')
                    ->label('Leída en')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('No leída')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('read_at')
                    ->label('Estado de Lectura')
                    ->nullable()
                    ->trueLabel('Solo Leídas')
                    ->falseLabel('Solo No Leídas')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('read_at'),
                        false: fn (Builder $query) => $query->whereNull('read_at'),
                    ),
               
                SelectFilter::make('type')
                    ->label('Tipo de Notificación')
                    ->options(function () {
                        return Notification::query()
                            ->select('type')
                            ->distinct()
                            ->pluck('type', 'type')
                            ->mapWithKeys(function ($type) {
                                return [$type => Str::afterLast($type, '\\')];
                            })
                            ->toArray();
                    })
                    ->searchable(),
            ])
            ->paginationPageOptions([10, 25, 50, 100, 500])
            ->defaultPaginationPageOption(25)
            ->actions([
                Tables\Actions\ViewAction::make(), // Ver detalles usando el formulario
                Tables\Actions\Action::make('mark_as_read')
                    ->label('Marcar como Leída')
                    ->icon('heroicon-o-eye')
                    ->action(fn (Notification $record) => $record->markAsRead())
                    ->visible(fn (Notification $record): bool => is_null($record->read_at))
                    ->requiresConfirmation(),
                Tables\Actions\Action::make('mark_as_unread')
                    ->label('Marcar como No Leída')
                    ->icon('heroicon-o-envelope-open')
                    ->action(fn (Notification $record) => $record->markAsUnread())
                    ->visible(fn (Notification $record): bool => !is_null($record->read_at))
                    ->requiresConfirmation(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('mark_selected_as_read')
                        ->label('Marcar Seleccionadas como Leídas')
                        ->icon('heroicon-s-eye')
                        ->action(function (Tables\Actions\BulkAction $action, \Illuminate\Database\Eloquent\Collection $records) {
                            $records->each->markAsRead();
                            $action->success();
                        })
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\BulkAction::make('mark_selected_as_unread')
                        ->label('Marcar Seleccionadas como No Leídas')
                        ->icon('heroicon-s-envelope-open')
                        ->action(function (Tables\Actions\BulkAction $action, \Illuminate\Database\Eloquent\Collection $records) {
                            $records->each->markAsUnread();
                            $action->success();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->defaultSort('created_at', 'desc'); // Mostrar las más recientes primero
    }

    public static function getRelations(): array
    {
        return [
            // Generalmente no se necesitan relaciones aquí, a menos que quieras
            // mostrar algo muy específico relacionado con la notificación.
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNotifications::route('/'),
            // 'create' => Pages\CreateNotification::route('/create'), // Deshabilitado arriba
            // 'edit' => Pages\EditNotification::route('/{record}/edit'), // Reemplazado por ViewAction
            // 'view' => Pages\ViewNotification::route('/{record}'), // Si usas ViewAction, necesitas esta página
        ];
    }
}