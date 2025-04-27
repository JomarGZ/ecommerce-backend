<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Filament\Resources\ProductResource\RelationManagers;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariation;
use Filament\Forms;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\CheckboxColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
            Section::make('Product details')
                ->schema([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn ($set, $state) => $set('slug', Str::slug($state))),
                    TextInput::make('slug')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->disabled()
                        ->maxLength(255),
                        
                    Select::make('category_id')
                        ->label('Category')
                        ->relationship('category', 'name')
                        ->required()
                        ->searchable()
                        ->preload(),
                        
                    TextInput::make('base_price')
                        ->required()
                        ->numeric()
                        ->prefix('$')
                        ->minValue(1)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function ($state, $set, Get $get) {
                            $variations = $get('variations');
                            $productName = Str::slug($get('name'));
            
                            if (!empty($variations) && !empty($productName)) {
                                $skus = self::generateSkusFromVariations(
                                    $productName,
                                    collect($variations),
                                    $state 
                                );
                                $set('skus', $skus);
                            }
                        }),
                    
                    MarkdownEditor::make('description')->required(),
                    Checkbox::make('published')->required(),
                ]),
            Section::make('Product Variations')
                ->schema([
                    Repeater::make('variations')
                        ->relationship('variations')
                        ->schema([
                            Select::make('variation_type')
                                ->options([
                                    'color' => 'Color',
                                    'size' => 'Size'
                                ])
                                ->required()
                                ->live(onBlur: true),
                            
                            Select::make('variation_value')
                            ->options(function (Get $get, $state): array {
                                $type = $get('variation_type');
                                $allVariations = $get('../../variations');
                                
                                $allOptions = match ($type) {
                                    'color' => [
                                        'red' => 'Red',
                                        'blue' => 'Blue',
                                        'green' => 'Green',
                                    ],
                                    'size' => [
                                        'small' => 'Small',
                                        'medium' => 'Medium',
                                        'large' => 'Large',
                                    ],
                                    default => [],
                                };
                        
                                if (empty($type)) {
                                    return $allOptions;
                                }
                        
                                $usedValues = collect($allVariations)
                                    ->where('variation_type', $type) 
                                    ->pluck('variation_value')
                                    ->filter() 
                                    ->unique() 
                                    ->toArray();
                        
                                if ($state && in_array($state, $usedValues)) {
                                    return $allOptions;
                                }
                        
                                return array_diff_key($allOptions, array_flip($usedValues));
                            })
                            ->required()
                            ->searchable()
                            ->live(onBlur: true),

                        FileUpload::make('product_image')
                            ->required()
                            ->image()
                            ->visible(fn (Get $get): bool => $get('variation_type') === 'color')
                            ->disk('public')
                            ->directory('product-variations'),
                        TextInput::make('price_adjustment')
                            ->numeric()
                            ->minValue(1)
                            ->default(0)
                    ])
                    ->live(onBlur: true)
                    ->afterStateUpdated(function ($state, callable $set, Get $get) {
                        $variations = collect($state);
                        
                        if ($variations->isEmpty()) {
                            return;
                        }
                        
                        $productName = Str::slug($get('name'));
                        if (empty($productName)) {
                            return;
                        }

                        $basePrice = (int)$get('base_price') ?? 0;
                        
                        $skus = self::generateSkusFromVariations($productName, $variations, $basePrice);
                        $set('skus', $skus);
                    }),
                    
                ]),
            
                Section::make('Product stock keeping unit')
                    ->schema([
                        Repeater::make('skus')
                        ->relationship('skus')
                        ->schema([
                            TextInput::make('sku_code')
                                ->required()
                                ->unique(ignoreRecord: true)
                                ->readOnly() 
                                ->dehydrated(), 
                            
                            TextInput::make('price')
                                ->required()
                                ->readOnly()
                                ->numeric()
                                ->dehydrated()
                                ->prefix('$')
                                ->default(function (Get $get) {
                                    return $get('../../base_price');
                                })
                                ->live(onBlur: true),
                            
                            TextInput::make('stock')
                                ->required()
                                ->numeric()
                                ->minValue(1)
                                ->default(0),
                            
                            Checkbox::make('is_active')
                                ->default(true)
                        ])
                        ->deletable(false)
                        ->addable(false)
                        ->columnSpanFull()
                        ->live(onBlur: true)
                        ->columns(4)
                    ]),
            ]);
    }


    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id'),
                TextColumn::make('name'),
                TextColumn::make('category.name'),
                CheckboxColumn::make('published'),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
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

    protected static function generateSkusFromVariations(string $productName, Collection $variations, $basePrice): array
    {
        $variationsByType = [];
        $priceAdjustmentData = [];
        foreach ($variations as $variation) {
            if (!isset($variation['variation_type']) || !isset($variation['variation_value'])) {
                continue;
            }
            
            $type = $variation['variation_type'];
            $value = $variation['variation_value'];
            $priceAdjustment = $variation['price_adjustment'] ?? 0;

            if (!isset($variationsByType[$type])) {
                $variationsByType[$type] = [];
            }
            $variationsByType[$type][] = $value;

            if (!isset($priceAdjustmentData[$type][$value])) {
                $priceAdjustmentData[$type][$value] = 0;
            }
            $priceAdjustmentData[$type][$value] += $priceAdjustment;
        }

        if (empty($variationsByType)) {
            return [
                [
                    'sku_code' => $productName . '-default',
                    'price' => $basePrice, 
                    'stock' => 0,
                    'is_active' => true
                ]
            ];
        }
        
        $combinations = self::generateVariationCombinations($variationsByType);
        
        $skus = [];
        foreach ($combinations as $combination) {
            $finalPrice = $basePrice;
            ksort($combination);
        
            $skuSuffix = implode('-', $combination);
            $skuCode = $productName . '-' . $skuSuffix;
            
            foreach ($combination as $type => $value) {
                if (isset($priceAdjustmentData[$type][$value])) {
                    $finalPrice += (int) $priceAdjustmentData[$type][$value];
                }
            }
            $skus[] = [
                'sku_code' => $skuCode,
                'price' => $finalPrice, 
                'stock' => 0,
                'is_active' => true
            ];
        }
        
        return $skus;
    }
    
    protected static function generateVariationCombinations(array $variationsByType): array
    {
        $result = [[]];
        
        foreach ($variationsByType as $type => $values) {
            $tempResult = [];
            
            foreach ($result as $existingComb) {
                foreach ($values as $value) {
                    $tempResult[] = array_merge($existingComb, [$type => $value]);
                }
            }
            
            $result = $tempResult;
        }
        
        return $result;
    }
}
