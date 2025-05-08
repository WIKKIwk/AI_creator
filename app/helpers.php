<?php

use Filament\Notifications\Notification;

function showSuccess(string $message = '', string $title = 'Success'): void
{
    Notification::make()
        ->title($title)
        ->body($message)
        ->icon('heroicon-o-check-circle')
        ->iconColor('success')
        ->success()
        ->duration(10000)
        ->send();
}

function showError(string $message = '', string $title = 'Error'): void
{
    Notification::make()
        ->title($title)
        ->body($message)
        ->icon('heroicon-o-x-circle')
        ->iconColor('danger')
        ->danger()
        ->duration(10000)
        ->send();
}

function showWarning(string $message = '', string $title = 'Warning'): void
{
    Notification::make()
        ->title($title)
        ->body($message)
        ->icon('heroicon-o-exclamation')
        ->iconColor('warning')
        ->warning()
        ->duration(10000)
        ->send();
}
