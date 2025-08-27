<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RegistroLiquidacionResource\Pages;
use App\Filament\Resources\RegistroLiquidacionResource\RelationManagers;
use App\Models\RegistroLiquidacion;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Tables\Actions\Action;
use App\Filament\Pages\RegistroAbonos as RegistroAbonosPage; // <-- Importa tu página de Filament y le damos un alias

class RegistroLiquidacionResource extends Resource
{
    protected static ?string $model = RegistroLiquidacion::class;

    protected static ?string $navigationIcon = 'hugeicons-note';
    protected static ?string $navigationGroup = 'Registros';
    protected static ?string $modelLabel = 'Registro de Liquidación';
    protected static ?string $pluralModelLabel = 'Registro de Liquidaciones';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('nombre')
                    ->required()
                    ->label('Nombre de Registro')
                    ->maxLength(50)
                    ->columnSpanFull(),
                // Forms\Components\Select::make('user_id')
                //     ->relationship('user', 'name')
                //     ->required()
                //     ->label('Usuario')
                //     ->searchable()
                //     ->preload(),
                // Forms\Components\DateTimePicker::make('desde')
                //     ->label('Fecha y Hora Inicio/Desde')
                //     ->nullable(),
                // Forms\Components\DateTimePicker::make('hasta')
                //     ->label('Fecha y Hora Fin/Hasta')
                //     ->nullable(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nombre')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Usuario')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('desde')
                    ->label('Desde')
                    ->toggleable()
                    ->dateTime('Y-m-d h:i A') // Changed to 12-hour format with AM/PM
                    ->sortable(),
                Tables\Columns\TextColumn::make('hasta')
                    ->label('Hasta')
                    ->toggleable()
                    ->dateTime('Y-m-d h:i A') // Changed to 12-hour format with AM/PM
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Action::make('abrir_liquidacion')
                    ->label('Ver Liquidación Guardada') // <-- Cambiamos la etiqueta para más claridad
                    ->icon('heroicon-o-document-magnifying-glass') // <-- Un ícono más apropiado
                    ->color('success')
                    ->url(function (RegistroLiquidacion $record): string {
                        // APUNTAMOS A LA NUEVA RUTA ESTÁTICA
                        return static::getUrl('view-saved', ['record' => $record->id]);
                    })
                    ->openUrlInNewTab(),

            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
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
            'index' => Pages\ListRegistroLiquidacions::route('/'),
            'create' => Pages\CreateRegistroLiquidacion::route('/create'),
            'edit' => Pages\EditRegistroLiquidacion::route('/{record}/edit'),
            'view-saved' => Pages\VerLiquidacionGuardada::route('/{record}/view-saved'),
        ];
    }
}