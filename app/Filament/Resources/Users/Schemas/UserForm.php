<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required()
                    ->maxLength(255),
                TextInput::make('password')
                    ->password()
                    ->revealable()
                    ->maxLength(255)
                    ->dehydrated(fn($state) => !empty($state))
                    ->dehydrateStateUsing(fn($state) => bcrypt($state))
                    ->required(fn(string $operation): bool => $operation === 'create')
                    ->label('Password (isi untuk mengganti)'),
                Select::make('roles')
                    ->required()
                    ->relationship('roles', 'name')
                    ->preload(),
            ]);
    }
}
