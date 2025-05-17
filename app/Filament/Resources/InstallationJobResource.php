<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InstallationJobResource\Pages;
use App\Filament\Resources\InstallationJobResource\RelationManagers;
use App\Models\InstallationJob;
use App\Models\User; // Untuk select teknisi & admin
use App\Models\OdpAsset; // Untuk select ODP Asset
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\Wizard; // Untuk form multi-langkah jika diperlukan
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Actions\Action; // Untuk custom action
use Illuminate\Support\Facades\Auth;

class InstallationJobResource extends Resource
{
    protected static ?string $model = InstallationJob::class;

    protected static ?string $navigationIcon = 'heroicon-o-briefcase'; // Ganti ikon sesuai selera

    protected static ?string $navigationGroup = 'Manajemen Pekerjaan'; // Pengelompokan di sidebar

    protected static ?string $modelLabel = 'Pekerjaan Instalasi / Proposal';

    protected static ?string $pluralModelLabel = 'Pekerjaan Instalasi / Proposal';


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Detail Proposal/Pekerjaan')
                            ->schema([
                                Forms\Components\TextInput::make('job_title')
                                    ->label('Judul Pekerjaan / Area')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpanFull(),
                                Forms\Components\Select::make('technician_id')
                                    ->label('Teknisi Pengaju/Pelaksana')
                                    ->relationship('technician', 'name') // Asumsi relasi 'technician' di model InstallationJob ke User
                                    ->searchable()
                                    ->preload()
                                    ->required(),
                                Forms\Components\Select::make('job_type')
                                    ->label('Jenis Pekerjaan')
                                    ->options(self::getJobTypeOptions()) // Ambil dari model atau definisikan di sini
                                    ->required(),
                                Forms\Components\Textarea::make('justification')
                                    ->label('Justifikasi / Alasan Pengajuan')
                                    ->columnSpanFull(),
                                Forms\Components\TextInput::make('job_reference_id')
                                    ->label('Nomor Referensi Pekerjaan (Opsional)')
                                    ->maxLength(50)
                                    ->unique(InstallationJob::class, 'job_reference_id', ignoreRecord: true),
                            ])->columns(2),

                        Forms\Components\Section::make('RAB & Biaya')
                            ->schema([
                                Forms\Components\TextInput::make('rab_estimated_total_cost')
                                    ->label('Estimasi Total Biaya RAB')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->readOnly(fn(string $operation) => $operation === 'edit') // Biaya RAB biasanya dihitung dari item, mungkin readonly di form utama
                                    ->helperText('Total biaya akan dihitung otomatis dari item RAB di tab "Item RAB".'),
                                Forms\Components\TextInput::make('actual_total_cost')
                                    ->label('Biaya Aktual Total (Opsional)')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->visible(fn(?InstallationJob $record) => $record && in_array($record->status, ['INSTALLATION_COMPLETED', 'VERIFIED_CLOSED'])),
                            ]),

                        Forms\Components\Section::make('Detail Lokasi ODP')
                            ->schema([
                                Forms\Components\Select::make('odp_asset_id')
                                    ->label('Aset ODP Terkait (Jika Ada)')
                                    ->relationship('odpAsset', 'odp_unique_identifier') // Asumsi relasi 'odpAsset'
                                    ->searchable()
                                    ->preload()
                                    ->helperText('Pilih ODP yang sudah ada jika pekerjaan terkait ODP eksisting, atau biarkan kosong untuk proposal ODP baru (akan dibuat setelah approval).'),
                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\TextInput::make('proposed_latitude')
                                            ->label('Latitude Usulan (ODP Baru)')
                                            ->numeric()
                                            ->helperText('Isi jika ini proposal ODP baru dan belum ada Aset ODP.'),
                                        Forms\Components\TextInput::make('proposed_longitude')
                                            ->label('Longitude Usulan (ODP Baru)')
                                            ->numeric()
                                            ->helperText('Isi jika ini proposal ODP baru dan belum ada Aset ODP.'),
                                        // Untuk integrasi map picker, Anda mungkin perlu plugin Filament pihak ketiga atau custom field.
                                        // Contoh: https://filamentphp.com/plugins/map-picker
                                    ]),
                            ]),
                    ])->columnSpan(['lg' => 2]),

                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Status & Persetujuan (Khusus Admin)')
                            ->schema([
                                Forms\Components\Select::make('status')
                                    ->label('Status Pekerjaan/Proposal')
                                    ->options(self::getStatusOptions()) // Ambil dari model atau definisikan di sini
                                    ->required()
                                    ->reactive() // Agar field lain bisa bereaksi terhadap perubahan status
                                    ->disabled(fn(string $operation, ?InstallationJob $record) => $operation === 'create'), // Hanya admin bisa set status saat edit, teknisi tidak bisa saat create
                                Forms\Components\Select::make('admin_approver_id')
                                    ->label('Admin Pemberi Persetujuan')
                                    ->relationship('adminApprover', 'name') // Asumsi relasi 'adminApprover' di model InstallationJob ke User (admin)
                                    ->searchable()
                                    ->preload()
                                    ->visible(fn(callable $get) => in_array($get('status'), ['APPROVED', 'REJECTED', 'REVISION_REQUESTED', 'VERIFIED_CLOSED']))
                                    ->disabled(), // Diisi otomatis oleh sistem saat aksi Approve/Reject
                                Forms\Components\DateTimePicker::make('approval_rejection_timestamp')
                                    ->label('Waktu Persetujuan/Penolakan')
                                    ->visible(fn(callable $get) => in_array($get('status'), ['APPROVED', 'REJECTED', 'REVISION_REQUESTED']))
                                    ->disabled(), // Diisi otomatis
                                Forms\Components\Textarea::make('admin_comments')
                                    ->label('Komentar/Alasan dari Admin')
                                    ->visible(fn(callable $get) => in_array($get('status'), ['REJECTED', 'REVISION_REQUESTED'])),
                            ]),
                        Forms\Components\Section::make('Jadwal & Penyelesaian')
                            ->schema([
                                Forms\Components\DatePicker::make('scheduled_installation_date')
                                    ->label('Tanggal Rencana Instalasi'),
                                Forms\Components\DateTimePicker::make('actual_completion_date')
                                    ->label('Tanggal & Waktu Selesai Aktual')
                                    ->visible(fn(?InstallationJob $record) => $record && in_array($record->status, ['INSTALLATION_COMPLETED', 'VERIFIED_CLOSED']))
                                    ->disabled(fn(string $operation) => $operation === 'edit'), // Mungkin hanya bisa diisi oleh teknisi via API atau di-override admin
                            ]),

                        Forms\Components\Section::make('Informasi Tambahan')
                            ->schema([
                                Forms\Components\Placeholder::make('created_at')
                                    ->label('Dibuat pada')
                                    ->content(fn(?InstallationJob $record): ?string => $record?->created_at?->diffForHumans()),
                                Forms\Components\Placeholder::make('updated_at')
                                    ->label('Terakhir diubah')
                                    ->content(fn(?InstallationJob $record): ?string => $record?->updated_at?->diffForHumans()),
                            ])
                            ->visible(fn(string $operation) => $operation !== 'create'),
                    ])->columnSpan(['lg' => 1]),
            ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('job_title')
                    ->label('Judul Pekerjaan')
                    ->searchable()
                    ->limit(30)
                    ->tooltip(fn(InstallationJob $record): string => $record->job_title),
                Tables\Columns\TextColumn::make('technician.name') // Asumsi relasi 'technician'
                    ->label('Teknisi')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge() // Menampilkan status sebagai badge dengan warna
                    ->color(fn(string $state): string => match ($state) {
                        'DRAFT_PROPOSAL' => 'gray',
                        'PENDING_APPROVAL' => 'warning',
                        'APPROVED' => 'info',
                        'INSTALLATION_IN_PROGRESS' => 'primary',
                        'INSTALLATION_COMPLETED' => 'success',
                        'VERIFIED_CLOSED' => 'success',
                        'REJECTED' => 'danger',
                        'REVISION_REQUESTED' => 'warning',
                        'CANCELLED' => 'danger',
                        default => 'gray',
                    })
                    ->searchable(),
                Tables\Columns\TextColumn::make('job_type')->label('Jenis Pekerjaan')->searchable(),
                Tables\Columns\TextColumn::make('rab_estimated_total_cost')
                    ->label('Est. Biaya RAB')
                    ->money('IDR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Tgl Pengajuan')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('approval_rejection_timestamp')
                    ->label('Tgl Approval/Reject')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('actual_completion_date')
                    ->label('Tgl Selesai Aktual')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(self::getStatusOptions()),
                SelectFilter::make('technician_id')
                    ->label('Teknisi')
                    ->relationship('technician', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('job_type')
                    ->options(self::getJobTypeOptions()),
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')->label('Dari Tanggal'),
                        Forms\Components\DatePicker::make('created_until')->label('Sampai Tanggal'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn(Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn(Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    })
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                // Aksi custom untuk approval/rejection bisa ditambahkan di sini atau di halaman View/Edit
                Action::make('approve')
                    ->label('Setujui')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (InstallationJob $record) {
                        $record->status = 'APPROVED';
                        $record->admin_approver_id = Auth::id();
                        $record->approval_rejection_timestamp = now();
                        $record->save();
                        // Kirim notifikasi ke teknisi jika perlu
                    }),
                Action::make('reject')
                    ->label('Tolak')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('admin_comments')
                            ->label('Alasan Penolakan')
                            ->required(),
                    ])
                    ->action(function (InstallationJob $record, array $data) {
                        $record->status = 'REJECTED';
                        $record->admin_approver_id = Auth::id();
                        $record->approval_rejection_timestamp = now();
                        $record->admin_comments = $data['admin_comments'];
                        $record->save();
                        // Kirim notifikasi ke teknisi
                    })
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
            RelationManagers\RabJobItemRelationManager::class,
            RelationManagers\InstallationPhotoRelationManager::class,
            RelationManagers\InstallationJobNoteRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInstallationJobs::route('/'),
            'create' => Pages\CreateInstallationJob::route('/create'),
            // Halaman View biasanya otomatis ter-handle jika ada ViewAction atau bisa dibuat custom
            // 'view' => Pages\ViewInstallationJob::route('/{record}'),
            'edit' => Pages\EditInstallationJob::route('/{record}/edit'),
        ];
    }

    // Helper untuk opsi status dan tipe pekerjaan (bisa juga diambil dari Enum di model)
    public static function getStatusOptions(): array
    {
        // Sesuaikan dengan ENUM di migrasi Anda
        return [
            'DRAFT_PROPOSAL' => 'Draft Proposal',
            'PENDING_APPROVAL' => 'Menunggu Persetujuan',
            'APPROVED' => 'Disetujui',
            'REJECTED' => 'Ditolak',
            'REVISION_REQUESTED' => 'Perlu Revisi',
            'INSTALLATION_IN_PROGRESS' => 'Instalasi Berjalan',
            'INSTALLATION_COMPLETED' => 'Instalasi Selesai',
            'VERIFIED_CLOSED' => 'Terverifikasi & Ditutup',
            'CANCELLED' => 'Dibatalkan',
        ];
    }

    public static function getJobTypeOptions(): array
    {
        // Sesuaikan dengan ENUM di migrasi Anda
        return [
            'NEW_INSTALLATION_PROPOSAL' => 'Proposal Instalasi Baru',
            'EXISTING_ODP_MAINTENANCE' => 'Pemeliharaan ODP Eksisting',
            'CAPACITY_UPGRADE' => 'Upgrade Kapasitas',
            'SURVEY' => 'Survei Lokasi',
        ];
    }

    // Mengoptimalkan query untuk relasi yang sering diakses
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['technician', 'odpAsset', 'adminApprover']);
    }
}
