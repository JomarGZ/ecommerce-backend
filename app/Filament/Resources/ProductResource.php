<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Filament\Resources\ProductResource\RelationManagers;
use App\Models\Category;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
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
                    ->options(Category::all()->pluck('name', 'id'))
                    ->required()
                    ->searchable()
                    ->preload(),
                    
                TextInput::make('base_price')
                    ->required()
                    ->numeric()
                    ->prefix('$')
                    ->minValue(1)
                    ->live(),
                 
                MarkdownEditor::make('description')->required(),
                Checkbox::make('published')->required(),
                
                // The variations repeater
                Repeater::make('variations')
                    ->relationship('variations')
                    ->schema([
                        Select::make('variation_type')
                            ->options([
                                'color' => 'Color',
                                'size' => 'Size'
                            ])
                            ->required()
                            ->live(),
                        
                        Select::make('variation_value')
                            ->options(function (Get $get): array {
                                $type = $get('variation_type');
                                
                                return match ($type) {
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
                            })
                            ->required()
                            ->searchable(),

                        FileUpload::make('product_image')
                            ->required()
                            ->visible(fn (Get $get): bool => $get('variation_type') === 'color')
                            ->disk('public')
                            ->directory('product-variations'),
                        TextInput::make('price_adjustment')
                            ->numeric()
                            ->default(0)
                    ])
                    ->live()
                    ->afterStateUpdated(function ($state, callable $set, Get $get) {
                        $variations = collect($state);
                        
                        // Only proceed if we have variations
                        if ($variations->isEmpty()) {
                            return;
                        }
                        
                        // Get product name for SKU base
                        $productName = Str::slug($get('name'));
                        if (empty($productName)) {
                            return;
                        }

                        $basePrice = (int)$get('base_price') ?? 0;
                        
                        // Generate SKUs and update the SKUs repeater
                        $skus = self::generateSkusFromVariations($productName, $variations, $basePrice);
                        $set('skus', $skus);
                    }),
                    
                // The SKUs repeater
                Repeater::make('skus')
                    ->relationship('skus')
                    ->schema([
                        TextInput::make('sku_code')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->disabled() // Auto-generated, so disabled
                            ->dehydrated(), // Still include in form submission
                        
                        TextInput::make('price')
                            ->required()
                            ->disabled()
                            ->numeric()
                            ->prefix('$')
                            ->default(function (Get $get) {
                                // Default price is the base product price
                                return $get('../../base_price');
                            })
                            ->live(),
                        
                        TextInput::make('stock')
                            ->required()
                            ->numeric()
                            ->default(0),
                        
                        Checkbox::make('is_active')
                            ->default(true)
                    ])
                    ->columnSpanFull()
                    ->columns(4)
            ]);
    }


    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                //
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
        // Group variations by type for easier processing
        $variationsByType = [];
        $priceAdjustmentData = [];
        foreach ($variations as $variation) {
            if (!isset($variation['variation_type']) || !isset($variation['variation_value'])) {
                continue;
            }
            
            $type = $variation['variation_type'];
            $value = $variation['variation_value'];
            $priceAdjustment = $variation['price_adjustment'] ?? 0;
            // Add to the grouped variations
            if (!isset($variationsByType[$type])) {
                $variationsByType[$type] = [];
            }
            $variationsByType[$type][] = $value;

            if (!isset($priceAdjustmentData[$type][$value])) {
                $priceAdjustmentData[$type][$value] = 0;
            }
            $priceAdjustmentData[$type][$value] += $priceAdjustment;

        }

        // If no variations, return a single default SKU
        if (empty($variationsByType)) {
            return [
                [
                    'sku_code' => $productName . '-default',
                    'price' => $basePrice, // Will be set to base_price by default
                    'stock' => 0,
                    'is_active' => true
                ]
            ];
        }
        
        // Generate combinations of all variations
        $combinations = self::generateVariationCombinations($variationsByType);
        
        // Create SKUs for each combination
        $skus = [];
        foreach ($combinations as $combination) {
            $finalPrice = $basePrice;
            // Sort by variation type (color first, then size, etc.)
            ksort($combination);
            dump($finalPrice);
            dump($basePrice);
            // Create the SKU code
            $skuSuffix = implode('-', $combination);
            $skuCode = $productName . '-' . $skuSuffix;
            
            foreach ($combination as $type => $value) {
                if (isset($priceAdjustmentData[$type][$value])) {
                    $finalPrice += (int) $priceAdjustmentData[$type][$value];
                }
            }
            // Add the SKU
            $skus[] = [
                'sku_code' => $skuCode,
                'price' => $finalPrice, // Will be set to base_price + price_adjustments
                'stock' => 0,
                'is_active' => true
            ];
        }
        
        return $skus;
    }
    
    protected static function generateVariationCombinations(array $variationsByType): array
    {
        // Start with an empty combination
        $result = [[]];
        
        // For each variation type and its values
        foreach ($variationsByType as $type => $values) {
            $tempResult = [];
            
            // For each existing combination
            foreach ($result as $existingComb) {
                // For each value of this type
                foreach ($values as $value) {
                    // Add this value to the existing combination
                    $tempResult[] = array_merge($existingComb, [$type => $value]);
                }
            }
            
            // Replace result with new combinations
            $result = $tempResult;
        }
        
        return $result;
    }
}
