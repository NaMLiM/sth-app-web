<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers;
use App\Models\User;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Manajemen Akses';

    protected static ?string $modelLabel = 'Pengguna';

    protected static ?string $pluralModelLabel = 'Pengguna';

    // Otorisasi: Hanya admin atau supervisor yang boleh melihat menu ini

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Informasi Dasar Pengguna')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nama Lengkap')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->label('Alamat Email')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(User::class, 'email', ignoreRecord: true),
                        TextInput::make('phone_number')
                            ->label('Nomor Telepon')
                            ->tel()
                            ->maxLength(20),
                    ])->columns(2),

                Section::make('Keamanan & Peran')
                    ->schema([
                        TextInput::make('password')
                            ->label('Kata Sandi Baru')
                            ->password()
                            ->dehydrateStateUsing(fn($state) => Hash::make($state)) // Hash password sebelum disimpan
                            ->dehydrated(fn($state) => filled($state)) // Hanya proses jika field diisi (untuk edit)
                            ->required(fn(string $context): bool => $context === 'create') // Wajib saat buat baru
                            ->confirmed() // Akan otomatis menambahkan field 'password_confirmation'
                            ->maxLength(255)
                            ->helperText('Kosongkan jika tidak ingin mengubah kata sandi saat edit.'),
                        TextInput::make('password_confirmation')
                            ->label('Konfirmasi Kata Sandi Baru')
                            ->password()
                            ->required(fn(string $context, callable $get): bool => $context === 'create' || filled($get('password')))
                            ->dehydrated(false), // Jangan simpan field konfirmasi ini ke database
                        Select::make('role')
                            ->label('Peran Pengguna')
                            ->options([ // Sesuaikan dengan enum atau definisi peran Anda
                                'technician' => 'Teknisi',
                                'admin' => 'Administrator',
                                'supervisor' => 'Supervisor',
                            ])
                            ->required(),
                        Toggle::make('is_active')
                            ->label('Status Akun Aktif')
                            ->default(true),
                    ])->columns(2),

                Section::make('Informasi Tambahan')
                    ->schema([
                        Placeholder::make('email_verified_at')
                            ->label('Email Terverifikasi pada')
                            ->content(fn(?User $record): string => $record?->email_verified_at ? $record->email_verified_at->translatedFormat('d F Y, H:i:s') : 'Belum diverifikasi'),
                        // Tombol untuk memverifikasi email bisa ditambahkan sebagai custom action jika perlu
                    ])
                    ->visible(fn(string $operation) => $operation !== 'create'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('name')
                    ->label('Nama')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('role')
                    ->label('Peran')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'admin' => 'danger',
                        'supervisor' => 'warning',
                        'technician' => 'success',
                        default => 'gray',
                    })
                    ->searchable()
                    ->sortable(),
                TextColumn::make('phone_number')
                    ->label('No. Telepon')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('email_verified_at')
                    ->label('Email Terverifikasi')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Tanggal Dibuat')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->label('Peran')
                    ->options([
                        'technician' => 'Teknisi',
                        'admin' => 'Administrator',
                        'supervisor' => 'Supervisor',
                    ]),
                TernaryFilter::make('is_active')
                    ->label('Status Akun Aktif'),
                TernaryFilter::make('email_verified_at')
                    ->label('Email Terverifikasi')
                    ->nullable(),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make()
                    ->before(function (User $record, DeleteAction $action) {
                        // Pencegahan agar tidak menghapus akun diri sendiri atau akun admin penting lainnya
                        if ($record->id === Auth::id()) {
                            $action->cancel();
                            // Bisa juga dengan mengirim notifikasi error
                            // Notifications\Notification::make()
                            //     ->title('Tidak dapat menghapus akun sendiri')
                            //     ->danger()
                            //     ->send();
                        }
                        // Tambahkan logika lain jika perlu
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            // Jika ada relasi yang ingin ditampilkan di halaman detail user, daftarkan di sini
            // Contoh: RelationManagers\InstallationJobsRelationManager::class, (jika ingin menampilkan pekerjaan yang dibuat user ini)
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            // 'view' => Pages\ViewUser::route('/{record}'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    // Opsional: Jika Anda ingin menjalankan query default dengan eager loading
    // public static function getEloquentQuery(): Builder
    // {
    //     return parent::getEloquentQuery()->withCount('posts'); // contoh
    // }
}
