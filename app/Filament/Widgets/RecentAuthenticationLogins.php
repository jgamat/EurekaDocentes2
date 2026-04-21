<?php

namespace App\Filament\Widgets;

use App\Models\AuthenticationLog;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class RecentAuthenticationLogins extends TableWidget
{
    protected static bool $isDiscovered = false;

    protected static ?string $heading = 'Ultimos accesos';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                AuthenticationLog::query()
                    ->with('authenticatable')
                    ->latest('login_at')
                    ->limit(12)
            )
            ->columns([
                Tables\Columns\TextColumn::make('authenticatable.name')
                    ->label('Usuario')
                    ->searchable(),
                Tables\Columns\TextColumn::make('authenticatable.email')
                    ->label('Email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP')
                    ->copyable(),
                Tables\Columns\IconColumn::make('login_successful')
                    ->label('Resultado')
                    ->boolean(),
                Tables\Columns\TextColumn::make('login_at')
                    ->label('Login')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),
            ])
            ->paginated(false)
            ->defaultSort('login_at', 'desc');
    }
}
