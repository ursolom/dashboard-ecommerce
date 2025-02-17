<?php

namespace App\Filament\Resources;

use Filament\Tables;
use App\Models\Product;
use Filament\Forms\Set;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use App\Enums\ProductTypeEnum;
use Filament\Resources\Resource;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Section;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Model;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Columns\ImageColumn;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Filters\TernaryFilter;
use App\Filament\Resources\ProductResource\Pages;

class ProductResource extends Resource
{
  protected static ?string $model = Product::class;
  protected static ?string $navigationIcon = 'heroicon-o-bolt';
  protected static ?string $navigationLabel = 'Products';
  protected static ?int $navigationSort =  0;
  protected static ?string $navigationGroup = 'Shop';
  protected static ?string $recordTitleAttribute = 'name';
  protected static int $globalSearchResultsLimit = 10;
  protected static ?string $activeNavigationIcon = 'heroicon-o-check-badge';
  public static function getNavigationBadge(): ?string
  {
    return static::getModel()::count();
  }
  public static function getGloballySearchableAttributes(): array
  {
    return ['name', 'slug', 'description', 'brand.name'];
  }

  public static function getGlobalSearchResultDetails(Model $record): array
  {
    return [
      'Brand' => $record->brand->name,
      'Price' => $record->price,
      'Quantity' => $record->quantity,
    ];
  }

  public static function form(Form $form): Form
  {
    return $form
      ->schema([
        Group::make()
          ->schema([
            Section::make()
              ->schema([
                TextInput::make('name')
                  ->required()
                  ->live(debounce: 600)
                  ->afterStateUpdated(function (string $operation, $state, Set $set) {
                    if ($operation !== 'create') {
                      return;
                    }
                    $set('slug', Str::slug($state));
                  }),
                TextInput::make('slug')
                  ->disabled()
                  ->dehydrated()
                  ->required()
                  ->unique(Product::class, 'slug', ignoreRecord: true),
              ])->columns(2),
            Section::make('Pricing & Inventory')
              ->schema([
                TextInput::make('sku')->label("SKU (Stack keeping Unit)")->unique()->required(),
                TextInput::make('price')->required()->numeric()
                  ->rules(['regex:/^\d+(\.\d{1,2})?$/']),
                TextInput::make('quantity')->required()->numeric()->minValue(0)->maxValue(100),
                Select::make('type')
                  ->options([
                    'downloadable' => ProductTypeEnum::DOWNLOADABLE->value,
                    'deliverable' => ProductTypeEnum::DELIVERABLE->value,
                  ])->required()
              ])->columns(2)
          ]),
        Group::make()
          ->schema([
            Section::make()
              ->schema([
                Toggle::make('is_visible')
                  ->label('Visibility')->helperText('Enable or disable product visibility')->default(true),
                Toggle::make('is_featured')
                  ->label('Featured')->helperText('Enable or disable product featured status'),
                DatePicker::make('published_at')
                  ->label('Availability')->default(now()),
              ]),
            Section::make('Image')
              ->schema([
                FileUpload::make('image')->directory('form-attachments')
                  ->preserveFilenames()
                  ->image()
                  ->required()
                  ->imageEditor(),
              ])->collapsible(),
            Section::make('Associations')
              ->schema([
                Select::make('brand_id')
                  ->relationship('brand', 'name')->required(),
                Select::make('categories')
                  ->relationship('categories', 'name')->multiple()->required()
              ])
          ]),
      ]);
  }

  public static function table(Table $table): Table
  {
    return $table
      ->columns([
        ImageColumn::make('image')
        ->label('Image')
        ->url(function ($record) {
            return $record->image ? asset('form-attachments/' . $record->image) : null;
        }),
    
        TextColumn::make('name')
          ->searchable()->sortable(),
        TextColumn::make('brand.name')
          ->searchable()->sortable()->toggleable(),
        IconColumn::make('is_visible')->boolean()
          ->sortable()->toggleable()->label('Visibility'),
        TextColumn::make('price')
          ->searchable()->sortable(),
        TextColumn::make('quantity')
          ->searchable()->sortable(),
        TextColumn::make('published_at')
          ->date()->sortable(),
        TextColumn::make('type'),
      ])
      ->filters([
        TernaryFilter::make('is_visible')->label('Visibility')->boolean()->trueLabel('Only Visible Products')
          ->falseLabel('Only Hidden Products')
          ->native(false),
        SelectFilter::make('brand')->relationship('brand', 'name')
      ])
      ->actions([
        ActionGroup::make([
          DeleteAction::make(),
          ViewAction::make(),
          Tables\Actions\EditAction::make(),
        ])
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
      //
    ];
  }

  public static function getPages(): array
  {
    return [
      'index' => Pages\ListProducts::route('/'),
      'create' => Pages\CreateProduct::route('/create'),
      'edit' => Pages\EditProduct::route('/{record}/edit'),
    ];
  }
}
