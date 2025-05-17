<?php

namespace App\Filament\Resources\OdpAssetResource\RelationManagers;

use App\Filament\Resources\InstallationJobResource; // Untuk helper options dan navigasi
use App\Models\InstallationJob;
use App\Models\User; // Untuk select teknisi
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class InstallationJobRelationManager extends RelationManager
{
    protected static string $relationship = 'installationJobs';

    protected static ?string $recordTitleAttribute = 'job_title'; // Atribut yang dijadikan judul record

    protected static ?string $modelLabel = 'Pekerjaan Instalasi Terkait';

    protected static ?string $pluralModelLabel = 'Pekerjaan Instalasi Terkait';


    public function form(Form $form): Form
    {
        // Form ini digunakan saat membuat atau mengedit InstallationJob DARI HALAMAN OdpAsset.
        // odp_asset_id akan otomatis terisi dengan ID OdpAsset saat ini.
        // Kita bisa menyederhanakan form ini dibandingkan form utama InstallationJobResource.
        return $form
            ->schema([
                Forms\Components\TextInput::make('job_title')
                    ->label('Judul Pekerjaan')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                Forms\Components\Select::make('technician_id')
                    ->label('Teknisi Pelaksana')
                    ->relationship('technician', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Forms\Components\Select::make('job_type')
                    ->label('Jenis Pekerjaan')
                    ->options(InstallationJobResource::getJobTypeOptions()) // Menggunakan helper dari InstallationJobResource
                    ->required()
                    ->default(fn(RelationManager $livewire) => $livewire->ownerRecord->exists ? 'EXISTING_ODP_MAINTENANCE' : 'NEW_INSTALLATION_PROPOSAL') // Default berbeda tergantung konteks
                    ->helperText('Biasanya untuk pemeliharaan atau upgrade pada ODP ini.'),
                Forms\Components\Select::make('status')
                    ->label('Status Awal')
                    ->options(InstallationJobResource::getStatusOptions()) // Menggunakan helper
                    ->required()
                    ->default('PENDING_APPROVAL')
                    ->visibleOn('create'), // Hanya saat buat baru dari sini
                Forms\Components\DatePicker::make('scheduled_installation_date')
                    ->label('Tanggal Rencana Pengerjaan'),
                Forms\Components\Textarea::make('justification')
                    ->label('Justifikasi / Deskripsi Pekerjaan')
                    ->columnSpanFull(),
                // Kolom odp_asset_id tidak perlu di form ini karena sudah otomatis terhubung
                // dengan ODP Asset saat ini.
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            // ->recordTitleAttribute('job_title') // Sudah di atas
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable()->label('ID Job'),
                Tables\Columns\TextColumn::make('job_title')
                    ->label('Judul Pekerjaan')
                    ->searchable()
                    ->limit(30)
                    ->tooltip(fn(InstallationJob $record): string => $record->job_title),
                Tables\Columns\TextColumn::make('technician.name')
                    ->label('Teknisi')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
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
                Tables\Columns\TextColumn::make('job_type')->label('Jenis Pekerjaan'),
                Tables\Columns\TextColumn::make('rab_estimated_total_cost')
                    ->label('Est. Biaya RAB')
                    ->money('IDR')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('actual_completion_date')
                    ->label('Tgl Selesai Aktual')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Tgl Dibuat')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(InstallationJobResource::getStatusOptions()),
                Tables\Filters\SelectFilter::make('job_type')
                    ->options(InstallationJobResource::getJobTypeOptions()),
            ])
            ->headerActions([
                // Tombol untuk membuat pekerjaan baru yang langsung terkait dengan ODP ini
                // Berguna jika admin ingin membuat tugas pemeliharaan untuk ODP yang sedang dilihat.
                Tables\Actions\CreateAction::make()
                    ->label('Buat Pekerjaan Baru untuk ODP Ini')
                    ->mutateFormDataUsing(function (array $data, RelationManager $livewire): array {
                        $data['odp_asset_id'] = $livewire->ownerRecord->id; // Otomatis set odp_asset_id
                        // Admin yang membuat dianggap sebagai approver awal atau statusnya langsung Approved jika perlu
                        // $data['admin_approver_id'] = auth()->id();
                        // $data['approval_rejection_timestamp'] = now();
                        // $data['status'] = 'APPROVED'; // Atau tetap PENDING_APPROVAL
                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->url(fn(InstallationJob $record): string => InstallationJobResource::getUrl('view', ['record' => $record])), // Arahkan ke halaman view resource utama
                Tables\Actions\EditAction::make()
                    ->url(fn(InstallationJob $record): string => InstallationJobResource::getUrl('edit', ['record' => $record])), // Arahkan ke halaman edit resource utama
                // Tables\Actions\DeleteAction::make(), // Hati-hati jika menghapus pekerjaan dari sini
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    // Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    // Anda bisa menonaktifkan beberapa aksi jika tidak relevan dikelola dari sini
    // protected function canCreate(): bool { return true; }
    // protected function canEdit(Model $record): bool { return true; }
    // protected function canDelete(Model $record): bool { return true; }
    // protected function canView(Model $record): bool { return true; }
}
