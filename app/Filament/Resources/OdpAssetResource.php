<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OdpAssetResource\Pages;
use App\Filament\Resources\OdpAssetResource\RelationManagers;
use App\Models\OdpAsset;
use App\Models\User; // Untuk created_by_user_id jika ingin menampilkan nama
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;

// Pertimbangkan untuk menggunakan plugin map picker jika ingin input lokasi yang lebih interaktif
// Contoh: use Cheesegrits\FilamentGoogleMaps\Fields\Map;
// atau plugin lain yang kompatibel dengan Leaflet/OpenStreetMap

class OdpAssetResource extends Resource
{
    protected static ?string $model = OdpAsset::class;

    protected static ?string $navigationIcon = 'heroicon-o-map-pin'; // Ikon untuk aset ODP

    protected static ?string $navigationGroup = 'Manajemen Aset'; // Pengelompokan di sidebar

    protected static ?string $modelLabel = 'Aset ODP';

    protected static ?string $pluralModelLabel = 'Aset ODP';

    protected static ?int $navigationSort = 1; // Urutan di navigasi grup

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Identitas ODP')
                    ->schema([
                        Forms\Components\TextInput::make('odp_unique_identifier')
                            ->label('Identifier Unik ODP')
                            ->required()
                            ->maxLength(100)
                            ->unique(OdpAsset::class, 'odp_unique_identifier', ignoreRecord: true)
                            ->helperText('Contoh: ODP-CLG-001, ODP/AREA/NOMOR_URUT'),
                        Forms\Components\Textarea::make('address_detail')
                            ->label('Alamat / Detail Lokasi')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])->columns(1),

                Forms\Components\Section::make('Koordinat Lokasi')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('latitude')
                                    ->label('Latitude')
                                    ->numeric()
                                    ->required()
                                    // Tambahkan validasi untuk rentang latitude jika perlu
                                    ->rules(['regex:/^[-]?(([0-8]?[0-9])\.(\d+))|(90(\.0+)?)$/']),
                                Forms\Components\TextInput::make('longitude')
                                    ->label('Longitude')
                                    ->numeric()
                                    ->required()
                                    // Tambahkan validasi untuk rentang longitude jika perlu
                                    ->rules(['regex:/^[-]?((((1[0-7][0-9])|([0-9]?[0-9]))\.(\d+))|180(\.0+)?)$/']),
                            ]),
                        // Catatan: Untuk input peta interaktif, Anda perlu plugin.
                        // Jika menggunakan plugin seperti Cheesegrits\FilamentGoogleMaps\Fields\Map:
                        // Map::make('location') // Ini akan menyimpan ke kolom 'location' (JSON) atau bisa dikonfigurasi
                        //    ->label('Pilih Lokasi di Peta')
                        //    ->default([
                        //        'lat' => -6.200000, // Lokasi default (misal Jakarta)
                        //        'lng' => 106.816666
                        //    ])
                        //    ->liveLocation()
                        //    ->mapControls([
                        //        'mapTypeControl    ' => true,
                        //        'scaleControl'       => true,
                        //        // ... kontrol lainnya
                        //    ])
                        //    ->columnSpanFull()
                        //    ->afterStateUpdated(function ($state, callable $set) {
                        //        $set('latitude', $state['lat']); // Update field latitude
                        //        $set('longitude', $state['lng']); // Update field longitude
                        //    })
                        //    ->reactive(), // Agar afterStateUpdated berjalan
                        // Jika tidak menggunakan plugin, admin harus input manual Latitude dan Longitude.
                        Forms\Components\Placeholder::make('map_info')
                            ->label(' ')
                            ->content('Untuk input lokasi via peta interaktif, pertimbangkan penggunaan plugin map picker. Saat ini, silakan input Latitude dan Longitude secara manual.'),

                    ]),

                Forms\Components\Section::make('Detail Teknis dan Status')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->label('Status Aset')
                            ->options(self::getStatusOptions()) // Helper method untuk opsi
                            ->required(),
                        Forms\Components\DatePicker::make('installation_date')
                            ->label('Tanggal Instalasi Aktual'),
                        Forms\Components\TextInput::make('capacity_ports')
                            ->label('Kapasitas Port')
                            ->numeric()
                            ->minValue(0),
                        Forms\Components\Select::make('odp_type') // Atau TextInput jika pilihannya banyak & bebas
                            ->label('Tipe ODP')
                            ->options([
                                'Pole Mount' => 'Pole Mount',
                                'Wall Mount' => 'Wall Mount',
                                'Pedestal' => 'Pedestal',
                                'Underground' => 'Underground',
                                'Lainnya' => 'Lainnya',
                            ])
                            ->searchable(),
                        Forms\Components\Toggle::make('is_legacy_data')
                            ->label('Ini Data ODP Legacy?')
                            ->default(false)
                            ->helperText('Aktifkan jika ini adalah ODP yang sudah ada sebelum sistem ini digunakan.'),
                        Forms\Components\Textarea::make('notes')
                            ->label('Catatan Tambahan')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])->columns(2),

                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Placeholder::make('created_by_user_id')
                            ->label('Dibuat Oleh')
                            ->content(fn(?OdpAsset $record): string => $record?->createdBy?->name ?? 'Sistem')
                            ->visible(fn(string $operation) => $operation !== 'create'),
                        Forms\Components\Placeholder::make('last_updated_by_user_id')
                            ->label('Diperbarui Oleh')
                            ->content(fn(?OdpAsset $record): string => $record?->lastUpdatedBy?->name ?? 'Sistem')
                            ->visible(fn(string $operation) => $operation !== 'create'),
                        Forms\Components\Placeholder::make('created_at')
                            ->label('Dibuat pada')
                            ->content(fn(?OdpAsset $record): ?string => $record?->created_at?->translatedFormat('d F Y, H:i:s')),
                        Forms\Components\Placeholder::make('updated_at')
                            ->label('Terakhir diubah')
                            ->content(fn(?OdpAsset $record): ?string => $record?->updated_at?->translatedFormat('d F Y, H:i:s')),
                    ])
                    ->columns(2)
                    ->visible(fn(string $operation) => $operation !== 'create'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('odp_unique_identifier')
                    ->label('ID ODP Unik')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('address_detail')
                    ->label('Alamat/Detail Lokasi')
                    ->limit(40)
                    ->tooltip(fn(OdpAsset $record): string => $record->address_detail ?? '')
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'PLANNED' => 'info',
                        'ACTIVE' => 'success',
                        'MAINTENANCE' => 'warning',
                        'DECOMMISSIONED' => 'danger',
                        'LEGACY_ACTIVE' => 'primary',
                        default => 'gray',
                    })
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('odp_type')
                    ->label('Tipe ODP')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('capacity_ports')
                    ->label('Port')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('is_legacy_data')
                    ->label('Legacy')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('installation_date')
                    ->label('Tgl Instalasi')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Terakhir Diubah')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(self::getStatusOptions()),
                SelectFilter::make('odp_type')
                    ->options([
                        'Pole Mount' => 'Pole Mount',
                        'Wall Mount' => 'Wall Mount',
                        'Pedestal' => 'Pedestal',
                        'Underground' => 'Underground',
                        'Lainnya' => 'Lainnya',
                    ])
                    ->searchable(),
                TernaryFilter::make('is_legacy_data')
                    ->label('Data Legacy'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->before(function (OdpAsset $record, Tables\Actions\DeleteAction $action) {
                        // Cek apakah ODP masih terkait dengan InstallationJob yang aktif
                        if ($record->installationJobs()->whereNotIn('status', ['VERIFIED_CLOSED', 'CANCELLED', 'REJECTED'])->exists()) {
                            // Kirim notifikasi error ke admin
                            \Filament\Notifications\Notification::make()
                                ->title('Tidak Dapat Menghapus Aset ODP')
                                ->body('Aset ODP ini masih terkait dengan pekerjaan instalasi yang aktif atau tertunda.')
                                ->danger()
                                ->send();
                            $action->cancel(); // Batalkan aksi hapus
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    // Tables\Actions\DeleteBulkAction::make(), // Hati-hati dengan bulk delete
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\InstallationJobRelationManager::class, // Untuk menampilkan daftar pekerjaan terkait ODP ini
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOdpAssets::route('/'),
            'create' => Pages\CreateOdpAsset::route('/create'),
            // 'view' => Pages\ViewOdpAsset::route('/{record}'),
            'edit' => Pages\EditOdpAsset::route('/{record}/edit'),
        ];
    }

    // Helper untuk opsi status
    public static function getStatusOptions(): array
    {
        // Sesuaikan dengan ENUM di migrasi atau model OdpAsset Anda
        return [
            'PLANNED' => 'Terencana',
            'ACTIVE' => 'Aktif',
            'MAINTENANCE' => 'Dalam Pemeliharaan',
            'DECOMMISSIONED' => 'Nonaktif (Decommissioned)',
            'LEGACY_ACTIVE' => 'Legacy Aktif',
        ];
    }

    // Mengoptimalkan query untuk relasi yang sering diakses
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['createdBy', 'lastUpdatedBy']); // Eager load relasi user jika ada
    }
}
