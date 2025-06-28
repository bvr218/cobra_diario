<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FrecuenciaResource\Pages;
use App\Filament\Resources\FrecuenciaResource\RelationManagers;
use App\Models\Frecuencia;
use Filament\Forms\Form;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class FrecuenciaResource extends Resource
{
    protected static ?string $model = Frecuencia::class;

    protected static ?string $navigationGroup  = 'Sistema';
    protected static ?string $label = 'Plan de Pago';
    protected static ?string $pluralLabel = 'Planes de Pago';
    protected static ?string $navigationLabel = 'Planes de Pago';
    protected static ?string $navigationIcon = 'fluentui-timeline-24-o';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->required()
                    ->maxLength(20),
                TextInput::make('dias')
                    ->required()
                    ->maxLength(20)
                    ->numeric(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('dias')
                    ->sortable()
                    ->toggleable()
                    ->searchable(),
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
            'index' => Pages\ListFrecuencias::route('/'),
            'create' => Pages\CreateFrecuencia::route('/create'),
            'edit' => Pages\EditFrecuencia::route('/{record}/edit'),
        ];
    }
}
