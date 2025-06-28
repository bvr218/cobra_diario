<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RoleResource\Pages;
use App\Filament\Resources\RoleResource\RelationManagers;
use Spatie\Permission\Models\Role; 
use Spatie\Permission\Models\Permission;  
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static ?string $navigationGroup = 'Sistema';

    protected static ?string $label = 'Rol';

    protected static ?string $pluralLabel = 'Roles';

    protected static ?string $navigationIcon = 'mdi-card-account-details-outline';


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nombre del rol')
                    ->required()
                    ->unique(
                        ignoreRecord: true,
                    )
                    ->maxLength(255)
                    ->validationMessages([
                        'unique' => 'El nombre del rol ya existe.',
                    ]),
                Forms\Components\Select::make('permissions')
                    ->label('Permisos asignados')
                    ->multiple()
                    ->relationship('permissions', 'name')
                    ->preload()
                    ->getOptionLabelFromRecordUsing(
                        fn (Permission $record): string => 
                            PermissionResource::changePermissionName($record->name)
                    )
                    ->helperText('Selecciona uno o más permisos'),
            ]);
            
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Rol')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('permissions_count')
                    ->label('Permisos')
                    ->counts('permissions')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->toggleable(),
            ])
            ->filters([
                //
            ])
            ->paginationPageOptions([10, 25, 50, 100, 500])
            ->defaultPaginationPageOption(25)
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn ($record) => $record->name !== 'admin'),
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
            'index' => Pages\ListRoles::route('/'),
            'create' => Pages\CreateRole::route('/create'),
            'edit' => Pages\EditRole::route('/{record}/edit'),
        ];
    }
}
