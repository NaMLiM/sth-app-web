<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RabItemResource\Pages;
use App\Filament\Resources\RabItemResource\RelationManagers;
use App\Models\RabItem;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload; // Jika menggunakan Spatie Media Library untuk gambar item, misalnya.

class RabItemResource extends Resource
{
    protected static ?string $model = RabItem::class;

    protected static ?string $navigationIcon = 'heroicon-o-archive-box'; // Ikon untuk item RAB

    protected static ?string $navigationGroup = 'Manajemen Data Master'; // Pengelompokan di sidebar

    protected static ?string $modelLabel = 'Item RAB';

    protected static ?string $pluralModelLabel = 'Item-item RAB';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Dasar Item RAB')
                    ->schema([
                        Forms\Components\TextInput::make('item_code')
                            ->label('Kode Item')
                            ->required()
                            ->maxLength(50)
                            ->unique(RabItem::class, 'item_code', ignoreRecord: true)
                            ->helperText('Kode unik untuk item ini, contoh: MAT-001, JSA-002.'),
                        Forms\Components\TextInput::make('name')
                            ->label('Nama Item')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('description')
                            ->label('Deskripsi Item')
                            ->columnSpanFull()
                            ->rows(3),
                    ])->columns(1), // Atur layout kolom jika perlu

                Forms\Components\Section::make('Detail Harga dan Satuan')
                    ->schema([
                        Forms\Components\TextInput::make('unit_of_measure')
                            ->label('Satuan Unit')
                            ->required()
                            ->maxLength(50)
                            ->helperText('Contoh: meter, pcs, jam, unit, ls (lumpsum).'),
                        Forms\Components\TextInput::make('price')
                            ->label('Harga Satuan')
                            ->numeric()
                            ->prefix('Rp')
                            ->required()
                            ->minValue(0),
                        Forms\Components\TextInput::make('category')
                            ->label('Kategori Item (Opsional)')
                            ->maxLength(100)
                            ->helperText('Contoh: Material Fiber Optik, Peralatan Pasif, Jasa Instalasi, Transportasi.'),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Status Aktif')
                            ->default(true)
                            ->helperText('Jika tidak aktif, item tidak akan muncul saat pemilihan RAB.'),
                    ])->columns(2),

                // Informasi audit (dibuat oleh, diubah oleh) bisa ditampilkan sebagai placeholder
                // dan diisi secara otomatis oleh sistem.
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Placeholder::make('created_by_user_id')
                            ->label('Dibuat Oleh')
                            ->content(fn(?RabItem $record): string => $record?->createdBy?->name ?? 'Sistem')
                            ->visible(fn(string $operation) => $operation !== 'create'),
                        Forms\Components\Placeholder::make('updated_by_user_id')
                            ->label('Diperbarui Oleh')
                            ->content(fn(?RabItem $record): string => $record?->updatedBy?->name ?? 'Sistem')
                            ->visible(fn(string $operation) => $operation !== 'create'),
                        Forms\Components\Placeholder::make('created_at')
                            ->label('Dibuat pada')
                            ->content(fn(?RabItem $record): ?string => $record?->created_at?->translatedFormat('d F Y, H:i:s')),
                        Forms\Components\Placeholder::make('updated_at')
                            ->label('Terakhir diubah')
                            ->content(fn(?RabItem $record): ?string => $record?->updated_at?->translatedFormat('d F Y, H:i:s')),
                    ])
                    ->columns(2)
                    ->visible(fn(string $operation) => $operation !== 'create'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('item_code')
                    ->label('Kode Item')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Item')
                    ->searchable()
                    ->sortable()
                    ->limit(50)
                    ->tooltip(fn(RabItem $record): string => $record->name),
                Tables\Columns\TextColumn::make('unit_of_measure')
                    ->label('Satuan')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('price')
                    ->label('Harga Satuan')
                    ->money('IDR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('category')
                    ->label('Kategori')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true), // Bisa disembunyikan defaultnya
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Terakhir Diubah')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->options(
                        // Ambil opsi kategori unik dari database jika memungkinkan
                        // atau definisikan secara manual jika terbatas
                        RabItem::query()->select('category')->whereNotNull('category')->distinct()->pluck('category', 'category')->all()
                    )
                    ->searchable(),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Status Aktif'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make() // Pastikan ada konfirmasi
                    ->before(function (RabItem $record, Tables\Actions\DeleteAction $action) {
                        // Tambahkan logika di sini jika item tidak boleh dihapus jika sudah terpakai di RAB
                        // Misalnya, cek apakah $record->rabJobItems()->exists()
                        // Jika iya, $action->cancel() atau $action->halt() dan kirim notifikasi error
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            // Biasanya item master tidak memiliki relasi yang dikelola langsung dari halamannya,
            // kecuali jika Anda ingin menampilkan di mana saja item ini digunakan.
            // RelationManagers\RabJobItemsRelationManager::class, // Contoh jika ingin melihat pekerjaan yang menggunakan item ini
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRabItems::route('/'),
            'create' => Pages\CreateRabItem::route('/create'),
            // 'view' => Pages\ViewRabItem::route('/{record}'), // Tambahkan jika Anda membuat halaman view kustom atau menggunakan ViewAction
            'edit' => Pages\EditRabItem::route('/{record}/edit'),
        ];
    }

    // Untuk mengisi created_by_user_id dan updated_by_user_id secara otomatis
    // Anda bisa menggunakan Model Observer atau method di dalam resource/model.
    // Contoh menggunakan fitur Filament:
    // protected function mutateFormDataBeforeCreate(array $data): array
    // {
    //     $data['created_by_user_id'] = auth()->id();
    //     $data['updated_by_user_id'] = auth()->id();
    //     return $data;
    // }

    // protected function mutateFormDataBeforeSave(array $data): array // Untuk edit
    // {
    //     $data['updated_by_user_id'] = auth()->id();
    //     return $data;
    // }

    // Cara yang lebih disarankan adalah menggunakan Model Observer di Laravel.
    // Buat observer: php artisan make:observer RabItemObserver --model=RabItem
    // Lalu di RabItemObserver.php:
    // public function creating(RabItem $rabItem): void
    // {
    //     if (auth()->check()) {
    //         $rabItem->created_by_user_id = auth()->id();
    //         $rabItem->updated_by_user_id = auth()->id();
    //     }
    // }
    // public function updating(RabItem $rabItem): void
    // {
    //     if (auth()->check()) {
    //         $rabItem->updated_by_user_id = auth()->id();
    //     }
    // }
    // Jangan lupa daftarkan Observer di AppServiceProvider.
}
