<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InstallationJobResource\Pages;
use App\Filament\Resources\InstallationJobResource\RelationManagers;
use App\Models\InstallationJob;
use App\Models\User; // Untuk select teknisi & admin
use App\Models\OdpAsset; // Untuk select ODP Asset
use App\Models\RabItem;
use Filament\Forms\Components\Actions\Action as ActionsAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Actions\Action; // Untuk custom action
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Support\Collection;

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
                Group::make()
                    ->schema([
                        Section::make('Detail Proposal/Pekerjaan')
                            ->schema([
                                TextInput::make('job_title')
                                    ->label('Judul Pekerjaan / Area')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpanFull(),
                                Select::make('technician_id')
                                    ->label('Teknisi Pengaju/Pelaksana')
                                    ->relationship('technician', 'name') // Asumsi relasi 'technician' di model InstallationJob ke User
                                    ->searchable()
                                    ->preload()
                                    ->required(),
                                Select::make('job_type')
                                    ->label('Jenis Pekerjaan')
                                    ->options(self::getJobTypeOptions()) // Ambil dari model atau definisikan di sini
                                    ->required(),
                                Textarea::make('justification')
                                    ->label('Justifikasi / Alasan Pengajuan')
                                    ->columnSpanFull(),
                                TextInput::make('job_reference_id')
                                    ->label('Nomor Referensi Pekerjaan (Opsional)')
                                    ->maxLength(50)
                                    ->unique(InstallationJob::class, 'job_reference_id', ignoreRecord: true),
                            ])->columns(2),

                        Section::make('RAB & Biaya')
                            ->schema([
                                Repeater::make('rabJobItems') // Ini akan mengelola relasi hasMany 'rabJobItems'
                                    ->label('Item RAB')
                                    ->relationship() // Menandakan ini adalah repeater untuk relasi Eloquent
                                    ->schema([
                                        Select::make('rab_item_id')
                                            ->label('Pilih Item RAB')
                                            ->options(RabItem::where('is_active', true)->pluck('name', 'id')->all())
                                            ->searchable()
                                            ->preload()
                                            ->required()
                                            ->reactive()
                                            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                                $rabItem = RabItem::find($state);
                                                if ($rabItem) {
                                                    $set('price_at_creation', $rabItem->price);
                                                    // Hitung ulang line_total jika quantity sudah ada
                                                    $quantity = $get('quantity') ?? 0;
                                                    $set('line_total', $rabItem->price * $quantity);
                                                }
                                            })
                                            ->columnSpan([
                                                'md' => 4,
                                            ])
                                            ->helperText(function ($state) {
                                                if ($state) {
                                                    $item = RabItem::find($state);
                                                    return 'Sisa stok: ' . ($item->quantity ?? 0);
                                                }
                                                return null;
                                            }),

                                        TextInput::make('quantity')
                                            ->label('Kuantitas')
                                            ->numeric()
                                            ->required()
                                            ->minValue(1)
                                            // JIKA ADA STOK: ->maxValue(fn (Get $get) => RabItem::find($get('rab_item_id'))->available_quantity ?? 1 )
                                            // JIKA ADA STOK: ->helperText(fn (Get $get) => 'Maks: ' . (RabItem::find($get('rab_item_id'))->available_quantity ?? 'N/A'))
                                            ->reactive()
                                            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                                $price = $get('price_at_creation') ?? 0;
                                                $set('line_total', $state * $price);
                                            })
                                            ->suffix(function (Get $get): ?string {
                                                $rabItemId = $get('rab_item_id');
                                                if ($rabItemId) {
                                                    $item = RabItem::find($rabItemId);
                                                    return $item?->unit_of_measure; // Mengambil unit_of_measure dari RabItem yang dipilih
                                                }
                                                return null;
                                            })
                                            ->columnSpan([
                                                'md' => 3,
                                            ]),

                                        TextInput::make('price_at_creation')
                                            ->label('Harga Satuan')
                                            ->numeric()
                                            ->prefix('Rp')
                                            ->readOnly() // Harga diambil dari master item saat dipilih
                                            ->required()
                                            ->columnSpan([
                                                'md' => 3,
                                            ]),

                                        // Placeholder untuk total per baris, bisa juga tidak ditampilkan jika tidak perlu
                                        Placeholder::make('line_total_display')
                                            ->label('Subtotal Item')
                                            ->content(function (Get $get): string {
                                                $total = $get('quantity') * $get('price_at_creation');
                                                return 'Rp ' . number_format($total, 2, ',', '.');
                                            })
                                            ->columnSpan([ // Sembunyikan di form utama, hanya untuk kalkulasi
                                                'md' => 0, // efektif menyembunyikan dengan cara ini
                                            ])
                                            ->hidden(), // Atau gunakan hidden()

                                        // Field ini akan disimpan ke DB (rab_job_items.line_total)
                                        Hidden::make('line_total')
                                            ->default(0),

                                    ])
                                    ->columns([
                                        'md' => 10, // Sesuaikan jumlah kolom internal repeater
                                    ])
                                    ->addActionLabel('Tambah Item RAB Lain')
                                    ->defaultItems(1) // Minimal 1 item saat membuat baru
                                    ->columnSpanFull()
                                    ->reactive() // Repeater harus reactive agar total estimasi bisa diupdate
                                    ->afterStateUpdated(function (Get $get, Set $set) {
                                        // Fungsi untuk menghitung ulang total estimasi
                                        self::updateEstimatedTotalCost($get, $set);
                                    })
                                    // Untuk menghapus item juga perlu update total
                                    ->deleteAction(
                                        fn(ActionsAction $action) => $action->after(fn(Get $get, Set $set) => self::updateEstimatedTotalCost($get, $set)),
                                    )->collapsed()
                                    ->itemLabel(fn(array $state): ?string => $state['rab_item_id']['name'] ?? null),

                                TextInput::make('rab_estimated_total_cost')
                                    ->label('Estimasi Total Biaya RAB')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->readOnly() // Sekarang ini dihitung otomatis
                                    ->helperText('Total biaya dihitung otomatis dari item RAB di atas.'),
                            ]),

                        Section::make('Detail Lokasi ODP')
                            ->schema([
                                Select::make('odp_asset_id')
                                    ->label('Aset ODP Terkait (Jika Ada)')
                                    ->relationship('odpAsset', 'odp_unique_identifier') // Asumsi relasi 'odpAsset'
                                    ->searchable()
                                    ->preload()
                                    ->helperText('Pilih ODP yang sudah ada jika pekerjaan terkait ODP eksisting, atau biarkan kosong untuk proposal ODP baru (akan dibuat setelah approval).'),
                                Grid::make(2)
                                    ->schema([
                                        TextInput::make('proposed_latitude')
                                            ->label('Latitude Usulan (ODP Baru)')
                                            ->numeric()
                                            ->helperText('Isi jika ini proposal ODP baru dan belum ada Aset ODP.'),
                                        TextInput::make('proposed_longitude')
                                            ->label('Longitude Usulan (ODP Baru)')
                                            ->numeric()
                                            ->helperText('Isi jika ini proposal ODP baru dan belum ada Aset ODP.'),
                                        // Untuk integrasi map picker, Anda mungkin perlu plugin Filament pihak ketiga atau custom field.
                                        // Contoh: https://filamentphp.com/plugins/map-picker
                                    ]),
                            ]),
                    ])->columnSpan(['lg' => 2]),

                Group::make()
                    ->schema([
                        Section::make('Status & Persetujuan')
                            ->schema([
                                Select::make('status')
                                    ->label('Status Pekerjaan/Proposal')
                                    ->options(self::getStatusOptions()) // Ambil dari model atau definisikan di sini
                                    ->required()
                                    ->reactive(), // Agar field lain bisa bereaksi terhadap perubahan status
                                // ->disabled(fn(string $operation, ?InstallationJob $record) => $operation === 'create'), // Hanya admin bisa set status saat edit, teknisi tidak bisa saat create
                                Select::make('admin_approver_id')
                                    ->label('Admin Pemberi Persetujuan')
                                    ->relationship('adminApprover', 'name') // Asumsi relasi 'adminApprover' di model InstallationJob ke User (admin)
                                    ->searchable()
                                    ->preload()
                                    ->visible(fn(callable $get) => in_array($get('status'), ['APPROVED', 'REJECTED', 'REVISION_REQUESTED', 'VERIFIED_CLOSED']))
                                    ->disabled(), // Diisi otomatis oleh sistem saat aksi Approve/Reject
                                DateTimePicker::make('approval_rejection_timestamp')
                                    ->label('Waktu Persetujuan/Penolakan')
                                    ->visible(fn(callable $get) => in_array($get('status'), ['APPROVED', 'REJECTED', 'REVISION_REQUESTED']))
                                    ->disabled(), // Diisi otomatis
                                Textarea::make('admin_comments')
                                    ->label('Komentar/Alasan dari Admin')
                                    ->visible(fn(callable $get) => in_array($get('status'), ['REJECTED', 'REVISION_REQUESTED'])),
                            ]),
                        Section::make('Jadwal & Penyelesaian')
                            ->schema([
                                DatePicker::make('scheduled_installation_date')
                                    ->label('Tanggal Rencana Instalasi'),
                                DateTimePicker::make('actual_completion_date')
                                    ->label('Tanggal & Waktu Selesai Aktual')
                                    ->visible(fn(?InstallationJob $record) => $record && in_array($record->status, ['INSTALLATION_COMPLETED', 'VERIFIED_CLOSED']))
                                    ->disabled(fn(string $operation) => $operation === 'edit'), // Mungkin hanya bisa diisi oleh teknisi via API atau di-override admin
                            ]),

                        Section::make('Informasi Tambahan')
                            ->schema([
                                Placeholder::make('created_at')
                                    ->label('Dibuat pada')
                                    ->content(fn(?InstallationJob $record): ?string => $record?->created_at?->diffForHumans()),
                                Placeholder::make('updated_at')
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
                TextColumn::make('id')->sortable()->searchable(),
                TextColumn::make('job_title')
                    ->label('Judul Pekerjaan')
                    ->searchable()
                    ->limit(30)
                    ->tooltip(fn(InstallationJob $record): string => $record->job_title),
                TextColumn::make('technician.name') // Asumsi relasi 'technician'
                    ->label('Teknisi')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('status')
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
                TextColumn::make('job_type')->label('Jenis Pekerjaan')->searchable(),
                TextColumn::make('rab_estimated_total_cost')
                    ->label('Est. Biaya RAB')
                    ->money('IDR')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Tgl Pengajuan')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('approval_rejection_timestamp')
                    ->label('Tgl Approval/Reject')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('actual_completion_date')
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
                Filter::make('created_at')
                    ->form([
                        DatePicker::make('created_from')->label('Dari Tanggal'),
                        DatePicker::make('created_until')->label('Sampai Tanggal'),
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
                ViewAction::make(),
                EditAction::make(),
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
                        Textarea::make('admin_comments')
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
                BulkActionGroup::make([
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

    // Fungsi helper untuk update total biaya tetap sama
    public static function updateEstimatedTotalCost(Get $get, Set $set): void
    {
        $totalCost = 0;
        $rabItemsData = $get('rabJobItems'); // 'rabJobItems' adalah nama dari Repeater kita

        if (is_array($rabItemsData)) {
            foreach ($rabItemsData as $itemData) {
                // Pastikan key ada dan numerik sebelum kalkulasi
                $quantity = isset($itemData['quantity']) && is_numeric($itemData['quantity']) ? floatval($itemData['quantity']) : 0;
                $price = isset($itemData['price_at_creation']) && is_numeric($itemData['price_at_creation']) ? floatval($itemData['price_at_creation']) : 0;
                $totalCost += $quantity * $price;
            }
        }
        $set('rab_estimated_total_cost', $totalCost);
    }
}
