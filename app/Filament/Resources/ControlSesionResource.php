<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ControlSesionResource\Pages;
use App\Models\ControlSesion;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Actions\EditAction;

class ControlSesionResource extends Resource
{
    protected static ?string $model = ControlSesion::class;
    protected static ?string $label = 'Control de Horario';
    protected static ?string $pluralLabel = 'Control de Horarios';
    protected static ?string $navigationIcon = 'heroicon-o-clock';
    protected static ?string $navigationGroup = 'Sistema';

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Select::make('dia')
                ->label('Día')
                ->options([
                    'Lunes'     => 'Lunes',
                    'Martes'    => 'Martes',
                    'Miércoles' => 'Miércoles',
                    'Jueves'    => 'Jueves',
                    'Viernes'   => 'Viernes',
                    'Sábado'    => 'Sábado',
                    'Domingo'   => 'Domingo',
                ])
                ->required()
                ->disabled(), // No editable: se gestiona un registro por día

            TimePicker::make('hora_apertura')
                ->label('Hora de Apertura')
                ->required()
                ->placeholder('Selecciona la hora de apertura')
                ->disabled(fn ($get) => $get('cerrado_manual') === true),

            TimePicker::make('hora_cierre')
                ->label('Hora de Cierre')
                ->required()
                ->placeholder('Selecciona la hora de cierre')
                ->disabled(fn ($get) => $get('cerrado_manual') === true),

            Toggle::make('cerrado_manual')
                ->label('Cerrado Manual')
                ->helperText('Marca para cerrar todo el día independientemente del horario')
                ->default(false)
                ->reactive()
                ->afterStateUpdated(function (bool $state, callable $get, callable $set) {
                    $record = ControlSesion::find($get('id'));
                    if ($record) {
                        $record->update([
                            'cerrado_manual' => $state,
                        ]);
                    }
                }),
        ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                TextColumn::make('dia')
                    ->label('Día')
                    ->sortable(),

                TextColumn::make('hora_apertura')
                    ->label('Hora de Apertura')
                    ->sortable()
                    ->time('H:i'),

                TextColumn::make('hora_cierre')
                    ->label('Hora de Cierre')
                    ->sortable()
                    ->time('H:i'),

                TextColumn::make('cerrado_manual')
                    ->label('Cerrado Manual')
                    ->badge()
                    ->formatStateUsing(fn (bool $state) => $state ? 'Sí' : 'No')
                    ->color(fn (bool $state) => $state ? 'danger' : 'success'),
            ])            
            ->paginationPageOptions([10, 25, 50, 100, 500])
            ->defaultPaginationPageOption(25)
            ->actions([
                EditAction::make(),
            ])
            ->bulkActions([])
            ->paginationPageOptions([7]) // 7 días fijos
            ->defaultPaginationPageOption(7);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListControlSesions::route('/'),
            'edit'   => Pages\EditControlSesion::route('/{record}/edit'),
        ];
    }
}
