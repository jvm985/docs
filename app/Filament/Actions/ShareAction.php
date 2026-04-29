<?php

namespace App\Filament\Actions;

use App\Models\Share;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;

class ShareAction extends Action
{
    public static function make(?string $name = 'share'): static
    {
        return parent::make($name)
            ->label('Delen')
            ->icon('heroicon-o-share')
            ->modalHeading('Delen')
            ->modalWidth('lg')
            ->schema([
                Toggle::make('is_public')
                    ->label('Deel met iedereen')
                    ->live(),

                Select::make('public_permission')
                    ->label('Iedereen mag')
                    ->options([
                        'read' => 'Alleen lezen',
                        'write' => 'Lezen en schrijven',
                    ])
                    ->default('read')
                    ->visible(fn ($get) => $get('is_public')),

                Repeater::make('users')
                    ->label('Gedeeld met specifieke gebruikers')
                    ->schema([
                        TextInput::make('email')
                            ->label('E-mailadres')
                            ->email()
                            ->required(),

                        Select::make('permission')
                            ->label('Rechten')
                            ->options([
                                'read' => 'Alleen lezen',
                                'write' => 'Lezen en schrijven',
                            ])
                            ->default('read')
                            ->required(),
                    ])
                    ->columns(2)
                    ->addActionLabel('Gebruiker toevoegen')
                    ->defaultItems(0)
                    ->visible(fn ($get) => ! $get('is_public')),
            ])
            ->action(function (array $data, $record) {
                // Remove existing shares for this record
                $record->shares()->delete();

                if ($data['is_public']) {
                    $record->shares()->create([
                        'is_public' => true,
                        'permission' => $data['public_permission'] ?? 'read',
                    ]);
                } else {
                    foreach ($data['users'] ?? [] as $userEntry) {
                        $user = User::where('email', $userEntry['email'])->first();
                        if ($user) {
                            $record->shares()->create([
                                'user_id' => $user->id,
                                'permission' => $userEntry['permission'],
                            ]);
                        }
                    }
                }

                Notification::make()
                    ->title('Deelinstelling opgeslagen')
                    ->success()
                    ->send();
            });
    }
}
