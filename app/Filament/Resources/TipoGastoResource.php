<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TipoGastoResource\Pages;
use App\Filament\Resources\TipoGastoResource\RelationManagers;
use App\Models\TipoGasto;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TipoGastoResource extends Resource
{
    protected static ?string $model = TipoGasto::class;

    protected static ?string $label = 'Tipo de Gasto';
    protected static ?string $pluralLabel = 'Tipos de Gastos';
    protected static ?string $navigationGroup = 'Registros';

    protected static ?string $navigationIcon = 'eos-sell-o';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('nombre')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(50),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nombre')
                    ->label('Nombre')
                    ->sortable()
                    ->searchable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
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
            'index' => Pages\ListTipoGastos::route('/'),
            'create' => Pages\CreateTipoGasto::route('/create'),
            'edit' => Pages\EditTipoGasto::route('/{record}/edit'),
        ];
    }
}
