<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Form;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Filament\Notifications\Notification;
use Illuminate\Validation\ValidationException;

class CambiarClave extends Page
{

        // ----------------------------------------------------------------
    // 1) Restringir registro en navegación lateral
    // ----------------------------------------------------------------
    public static function shouldRegisterNavigation(): bool
    {
        return request()->user()->can('password.index');
    }

    public static function canAccess(): bool
    {
        return request()->user()->can('password.index');
    }

    use InteractsWithForms;


    protected static ?string $navigationIcon = 'eos-password';

    protected static ?string $navigationGroup = 'Sistema';
    protected static ?string $navigationLabel = 'Contraseña';
    protected static ?string $title = 'Cambiar Contraseña';

    protected static string $view = 'filament.pages.cambiar-clave';


    public ?string $current_password = null;
    public ?string $new_password = null;
    public ?string $new_password_confirmation = null;

    public array $data = [];

    public function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('current_password')
                ->label('Contraseña Actual')
                ->password()
                ->required()
                ->minLength(8)
                ->maxLength(36)
                ->rule('current_password')
                ->validationMessages([
                    'current_password' => 'La contraseña actual es incorrecta.',
                    'min_length' => 'La contraseña debe tener al menos 8 caracteres.',
                    'max_length' => 'La contraseña no puede tener más de 36 caracteres.',
                ]),

            TextInput::make('new_password')
                ->label('Nueva Contraseña')
                ->password()
                ->required()
                ->minLength(8)
                ->maxLength(36)
                ->validationMessages([
                    'min_length' => 'La contraseña debe tener al menos 8 caracteres.',
                    'max_length' => 'La contraseña no puede tener más de 36 caracteres.',
                ]),
            TextInput::make('new_password_confirmation')
                ->label('Confirmar Nueva Contraseña')
                ->password()
                ->required()
                ->minLength(8)
                ->maxLength(36)
                ->same('new_password')
                ->validationMessages([
                    'same' => 'La contraseña de confirmación no coincide con la nueva contraseña.',
                    'min_length' => 'La contraseña debe tener al menos 8 caracteres.',
                    'max_length' => 'La contraseña no puede tener más de 36 caracteres.',
                ]),

        ])->statePath('data');
    }

    public function submit(): void
    {
        $data = $this->form->getState();
        $user = Auth::user();

        if (Hash::check($data['new_password'], $user->password)) {
            throw ValidationException::withMessages([
                'data.new_password' => 'La nueva contraseña no puede ser igual a la contraseña actual.',
            ]);
        }
    

        $user->password = Hash::make($data['new_password']);
        $user->save();

        $this->reset('current_password', 'new_password', 'new_password_confirmation');
        $this->form->fill();

        Notification::make()
            ->title('Contraseña Cambiada')
            ->body('La contraseña ha sido cambiada exitosamente.')
            ->success()
            ->send();
    }
}
