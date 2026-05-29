<?php

namespace App\Filament\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class ForcePasswordChange extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-lock-closed';

    protected string $view = 'filament.pages.force-password-change';

    protected static bool $shouldRegisterNavigation = false;

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([
                    TextInput::make('current_password')
                        ->label('Contraseña Actual')
                        ->password()
                        ->required()
                        ->currentPassword(),
                    TextInput::make('new_password')
                        ->label('Nueva Contraseña')
                        ->password()
                        ->required()
                        ->confirmed()
                        ->different('current_password')
                        ->rules([Password::defaults()]),
                    TextInput::make('new_password_confirmation')
                        ->label('Confirmar Nueva Contraseña')
                        ->password()
                        ->required(),
                ])
                    ->livewireSubmitHandler('submit')
                    ->footer([
                        Actions::make([
                            Action::make('submit')
                                ->label('Actualizar Contraseña')
                                ->submit('submit'),
                        ]),
                    ]),
            ])
            ->statePath('data');
    }

    public function submit(): void
    {
        $data = $this->form->getState();

        $user = Auth::user();

        $user->update([
            'password' => Hash::make($data['new_password']),
            'password_changed_at' => now(),
            'requires_password_change' => false,
        ]);

        Notification::make()
            ->title('Contraseña actualizada exitosamente')
            ->success()
            ->send();

        $this->redirect(route('filament.admin.pages.dashboard'));
    }
}
