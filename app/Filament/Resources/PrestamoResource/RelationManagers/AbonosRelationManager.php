<?php

namespace App\Filament\Resources\PrestamoResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\Placeholder;
use Filament\Tables\Columns\TextColumn;
use App\Filament\Resources\AbonoResource;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;

class AbonosRelationManager extends RelationManager
{
    protected static string $relationship = 'abonos';




    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('monto_abono')
                    ->label('Monto del Abono')
                    ->required()
                    ->numeric()
                    ->prefix('COP')
                    ->step(0.01)
                    ->helperText('Use punto (.) unicamente para separar decimales')
                    ->minValue(0),
                DatePicker::make('fecha_abono')
                    ->label('Fecha del Abono')
                    ->required()
                    ->date()
                    ->default(now()),
                TextInput::make('numero_cuota')
                    ->label('Número de Cuota')
                    ->numeric()
                    ->disabled()
                    ->dehydrated(false)
                    ->helperText('Se asigna automaticamente.'),
                Select::make('registrado_por_id')
                    ->label('Abono Registrado Por')
                    ->relationship('registradoPor', 'name')
                    ->searchable()
                    ->preload()
                    ->dehydrated(true)
                    ->default(fn () => auth()->user()->id)
                    ->disabled(fn()=>!request()->user()->hasRole("admin")),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('ID del Abono')->toggleable(),
                Tables\Columns\TextColumn::make('numero_cuota')->label('Numero de Cuota'),
                Tables\Columns\TextColumn::make('deuda_anterior')->label('Deuda Anterior')->formatStateUsing(fn ($state) => '$' . number_format($state, 0, ',', '.') . ' COP'),
                Tables\Columns\TextColumn::make('monto_abono')->label('Monto del Abono')->formatStateUsing(fn ($state) => '$' . number_format($state, 0, ',', '.') . ' COP'),
                Tables\Columns\TextColumn::make('deuda_actual')->label('Deuda Actual')->formatStateUsing(fn ($state) => '$' . number_format($state, 0, ',', '.') . ' COP'),
                Tables\Columns\TextColumn::make('fecha_abono')->label('Fecha del Abono'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),

            ])
            ->actions([
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\EditAction::make(),

            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                ]),
            ]);
    }
}
