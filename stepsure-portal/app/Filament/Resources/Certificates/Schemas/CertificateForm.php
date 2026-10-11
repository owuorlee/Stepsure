<?php

namespace App\Filament\Resources\Certificates\Schemas;

use App\Models\Certificate;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CertificateForm
{
    public static function configure(Schema $schema): Schema
    {
        $previous = fn (string $column): array => Certificate::query()->whereNotNull($column)
            ->distinct()->orderBy($column)->limit(200)->pluck($column)->all();

        return $schema
            ->columns(2)
            ->components([
                Section::make('Participant')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('participant_name')
                            ->label('Full name')
                            ->helperText('Exactly as it should appear on the certificate.')
                            ->required()
                            ->maxLength(120),
                        TextInput::make('participant_email')
                            ->label('Email')
                            ->email()
                            ->helperText('Optional. Used to email the certificate.'),
                    ]),
                Section::make('Course')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('course_title')
                            ->label('Course title')
                            ->required()
                            ->maxLength(160)
                            ->datalist(fn (): array => $previous('course_title'))
                            ->columnSpanFull(),
                        DatePicker::make('starts_on')
                            ->label('Start date')
                            ->required()
                            ->native(false)
                            ->displayFormat('j M Y'),
                        DatePicker::make('ends_on')
                            ->label('End date')
                            ->native(false)
                            ->displayFormat('j M Y')
                            ->afterOrEqual('starts_on')
                            ->helperText('Leave empty for a one-day course.'),
                        TextInput::make('venue')
                            ->placeholder('e.g. Nairobi Safari Club, or Online')
                            ->datalist(fn (): array => $previous('venue')),
                        TextInput::make('class_code')
                            ->label('Class code')
                            ->placeholder('e.g. SOM 3/2026')
                            ->datalist(fn (): array => $previous('class_code')),
                        TextInput::make('serial_no')
                            ->label('Serial no.')
                            ->placeholder('e.g. IA 2/2026'),
                    ]),
            ]);
    }
}
