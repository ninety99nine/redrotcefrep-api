<?php

namespace App\Repositories;

use App\Models\Store;
use App\Models\Product;
use App\Models\Variable;
use App\Traits\AuthTrait;
use App\Enums\Association;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use App\Enums\SortProductBy;
use App\Traits\Base\BaseTrait;
use App\Enums\RequestFileName;
use App\Enums\StockQuantityType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use App\Http\Resources\ProductResources;
use Illuminate\Database\Eloquent\Builder;
use App\Http\Resources\MediaFileResources;
use Illuminate\Database\Eloquent\Relations\Relation;

class ProductRepository extends BaseRepository
{
    use AuthTrait, BaseTrait;

    /**
     * Show products.
     *
     * @param array $data
     * @return ProductResources|array
     */
    public function showProducts(array $data = []): ProductResources|array
    {
        if($this->getQuery() == null) {

            $storeId = isset($data['store_id']) ? $data['store_id'] : null;

            if(is_null($storeId)) {
                if(!$this->isAuthourized()) return ['message' => 'You do not have permission to show products'];
                $this->setQuery(Product::query()->isNotVariation()->orderBy('position'));
            }else{

                $store = Store::find($storeId);

                if($store) {

                    $association = isset($data['association']) ? Association::tryFrom($data['association']) : null;

                    if($association == Association::SHOPPER) {

                        $this->setQuery($store->products()->isNotVariation()->visible()->orderBy('position'));

                    }else{

                        $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                        if(!$isAuthourized) return ['message' => 'You do not have permission to show products'];
                        $this->setQuery($store->products()->isNotVariation()->orderBy('position'));

                    }

                }else{
                    return ['message' => 'This store does not exist'];
                }
            }
        }

        return $this->applyFiltersOnQuery()->getOrCountResources();
    }

    /**
     * Create product.
     *
     * @param array $data
     * @return Product|array
     */
    public function createProduct(array $data): Product|array
    {
        $storeId = $data['store_id'];
        $store = Store::find($storeId);

        if($store) {
            $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
            if(!$isAuthourized) return ['created' => false, 'message' => 'You do not have permission to create products'];
        }else{
            return ['created' => false, 'message' => 'This store does not exist'];
        }

        $data = array_merge($data, [
            'user_id' => request()->current_user->id,
            'currency' => $store->currency,
            'store_id' => $storeId
        ]);

        $product = Product::create($data);
        $this->getMediaFileRepository()->authourize()->createMediaFile(RequestFileName::PRODUCT_PHOTO, $product);
        return $this->showCreatedResource($product);
    }

    /**
     * Delete products.
     *
     * @param array $data
     * @return array
     */
    public function deleteProducts(array $data): array
    {
        $storeId = $data['store_id'];

        if(is_null($storeId)) {
            if(!$this->isAuthourized()) return ['deleted' => false, 'message' => 'You do not have permission to delete products'];
            $this->setQuery(Product::query());
        }else{

            $store = Store::find($storeId);

            if($store) {
                $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                if(!$isAuthourized) return ['deleted' => false, 'message' => 'You do not have permission to delete products'];
                $this->setQuery($store->products());
            }else{
                return ['deleted' => false, 'message' => 'This store does not exist'];
            }

        }

        $productIds = $data['product_ids'];
        $products = $this->getProductsByIds($productIds);

        if($totalProducts = $products->count()) {

            foreach($products as $product) {
                $product->delete();
            }

            return ['deleted' => true, 'message' => $totalProducts . ($totalProducts == 1 ? ' product': ' products') . ' deleted'];

        }else{
            return ['deleted' => false, 'message' => 'No products deleted'];
        }
    }

    /**
     * Update product visibility
     *
     * @param array $data
     * @return array
     */
    public function updateProductVisibility(array $data): array
    {
        $storeId = $data['store_id'];
        $store = Store::find($storeId);

        if($store) {
            $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
            if(!$isAuthourized) return ['message' => 'You do not have permission to update product visibility'];
            $this->setQuery($store->products());
        }else{
            return ['message' => 'This store does not exist'];
        }

        $products = $this->query->get();
        $productIdsAndVisibility = $data['visibility'];

        $existingProductIdsAndVisibility = $products->map(function ($product) {
            return ['id' => $product->id, 'visible' => $product->visible];
        });

        $newProductIdsAndVisibility = collect($productIdsAndVisibility)->filter(function ($item) use ($existingProductIdsAndVisibility) {
            return $existingProductIdsAndVisibility->contains('id', $item['id']);
        })->toArray();

        $oldProductIdsAndVisibility = collect($existingProductIdsAndVisibility)->filter(function ($item) use ($productIdsAndVisibility) {
            return collect($productIdsAndVisibility)->doesntContain('id', $item['id']);
        })->toArray();

        $finalProductIdsAndVisibility = $newProductIdsAndVisibility + $oldProductIdsAndVisibility;
        $finalProductIdsAndVisibility = collect($finalProductIdsAndVisibility)->mapWithKeys(fn($item) => [$item['id'] => $item['visible'] ? 1 : 0])->toArray();

        if(count($finalProductIdsAndVisibility)) {

            DB::table('products')
            ->where('store_id', $store->id)
            ->whereIn('id', array_keys($finalProductIdsAndVisibility))
            ->update(['visible' => DB::raw('CASE id ' . implode(' ', array_map(function ($id, $visibility) {
                return 'WHEN "' . $id . '" THEN ' . $visibility . ' ';
            }, array_keys($finalProductIdsAndVisibility), $finalProductIdsAndVisibility)) . 'END')]);

            return ['updated' => true, 'message' => 'Product visibility has been updated'];

        }

        return ['updated' => false, 'message' => 'No matching products to update'];
    }

    /**
     * Update product arrangement
     *
     * @param array $data
     * @return array
     */
    public function updateProductArrangement(array $data): array
    {
        $storeId = $data['store_id'];
        $store = Store::find($storeId);

        if($store) {
            $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
            if(!$isAuthourized) return ['message' => 'You do not have permission to update product arrangement'];
            $this->setQuery($store->products()->orderBy('position', 'asc'));
        }else{
            return ['message' => 'This store does not exist'];
        }

        if(isset($data['sort_by'])) {

            $query = $store->products();

            switch ($data['sort_by']) {

                case SortProductBy::BEST_SELLING->value;

                /**
                 *  Best Selling Ranking Algorithm:
                 *  -------------------------------
                 *
                 *  1) Rank each product by sales velocity.
                 *  2) Products with unlimited or high stock levels have an advantage.
                 *  3) Do not consider cancelled product lines.
                 *  4) Do not consider product lines of cancencelled orders.
                 *
                 *  Note: Clone query because the query instance is modified.
                 */
                $productWithHighestStockQuantity = (clone $query)->where('stock_quantity_type', StockQuantityType::LIMITED->value)->orderBy('stock_quantity', 'desc')->first();
                $maxStockQuantity = $productWithHighestStockQuantity && $productWithHighestStockQuantity->stock_quantity > 0
                                    ? $productWithHighestStockQuantity->stock_quantity
                                    : 1;

                $productIds = $query->select('products.id')
                ->selectRaw('
                    (
                        (
                            SELECT SUM(product_lines.quantity)
                            FROM product_lines
                            INNER JOIN carts ON carts.id = product_lines.cart_id
                            INNER JOIN orders ON orders.cart_id = carts.id
                            WHERE product_lines.product_id = products.id
                            AND product_lines.is_cancelled = 0
                            AND orders.status != "cancelled"
                        ) /
                        GREATEST(
                            (
                                SELECT DATEDIFF(MAX(orders.created_at), MIN(orders.created_at))
                                FROM orders
                                INNER JOIN carts ON carts.id = orders.cart_id
                                INNER JOIN product_lines ON product_lines.cart_id = carts.id
                                WHERE product_lines.product_id = products.id
                                AND product_lines.is_cancelled = 0
                            ),
                            1
                        )
                    ) *
                    (CASE
                        WHEN products.stock_quantity_type = ?
                            THEN LEAST(products.stock_quantity / ?, 1)
                            ELSE 1
                    END) as sales_rate', [StockQuantityType::LIMITED->value, $maxStockQuantity]
                )
                ->orderByDesc('sales_rate')
                ->pluck('products.id');

                    break;
                case SortProductBy::MOST_STOCK->value;
                    $productIds = $query->select('id')->where('stock_quantity_type', StockQuantityType::LIMITED->value)->orderBy('stock_quantity', 'desc')->pluck('id');
                    break;
                case SortProductBy::LEAST_STOCK->value;
                    $productIds = $query->select('id')->where('stock_quantity_type', StockQuantityType::LIMITED->value)->orderBy('stock_quantity', 'asc')->pluck('id');
                    break;
                case SortProductBy::MOST_DISCOUNTED->value;
                    $productIds = $query->select('id')->where('on_sale', '1')->orderBy('unit_sale_discount_percentage', 'desc')->pluck('id');
                    break;
                case SortProductBy::MOST_EXPENSIVE->value;
                    $productIds = $query->select('id')->orderBy('unit_price', 'desc')->pluck('id');
                    break;
                case SortProductBy::MOST_AFFORDABLE->value;
                    $productIds = $query->select('id')->orderBy('unit_price', 'asc')->pluck('id');
                    break;
                case SortProductBy::ALPHABETICALLY->value;
                    $productIds = $query->select('id')->orderBy('name', 'asc')->pluck('id');
                    break;
                default:
                    return ['updated' => false, 'message' => 'Cannot sort using the sort by method provided'];
            }

        }else{
            $productIds = $data['product_ids'];
        }

        $products = $this->query->get();
        $originalProductPositions = $products->pluck('position', 'id');

        $arrangement = collect($productIds)->filter(function ($productId) use ($originalProductPositions) {
            return collect($originalProductPositions)->keys()->contains($productId);
        })->toArray();

        $movedProductPositions = collect($arrangement)->mapWithKeys(function ($productId, $newPosition) use ($originalProductPositions) {
            return [$productId => ($newPosition + 1)];
        })->toArray();

        $adjustedOriginalProductPositions = $originalProductPositions->except(collect($movedProductPositions)->keys())->keys()->mapWithKeys(function ($id, $index) use ($movedProductPositions) {
            return [$id => count($movedProductPositions) + $index + 1];
        })->toArray();

        $productPositions = $movedProductPositions + $adjustedOriginalProductPositions;

        if(count($productPositions)) {

            DB::table('products')
                ->where('store_id', $store->id)
                ->whereIn('id', array_keys($productPositions))
                ->update(['position' => DB::raw('CASE id ' . implode(' ', array_map(function ($id, $position) {
                    return 'WHEN "' . $id . '" THEN ' . $position . ' ';
                }, array_keys($productPositions), $productPositions)) . 'END')]);

            return ['updated' => true, 'message' => 'Product arrangement has been updated'];

        }

        return ['updated' => false, 'message' => 'No matching products to update'];
    }

    /**
     * Show product.
     *
     * @param string $productId
     * @return Product|array|null
     */
    public function showProduct(string $productId): Product|array|null
    {
        $product = $this->setQuery(Product::with(['store'])->whereId($productId))->applyEagerLoadingOnQuery()->getQuery()->first();

        if($product) {
            $store = $product->store;
            if($store) {
                $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                if(!$isAuthourized) return ['message' => 'You do not have permission to show product'];
                if(!$this->checkIfHasRelationOnRequest('store')) $product->unsetRelation('store');
            }else{
                return ['message' => 'This store does not exist'];
            }
        }

        return $this->showResourceExistence($product);
    }

    /**
     * Update product.
     *
     * @param string $productId
     * @param array $data
     * @return Product|array
     */
    public function updateProduct(string $productId, array $data): Product|array
    {
        $product = Product::with(['store'])->find($productId);

        if($product) {
            $store = $product->store;
            if($store) {
                $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                if(!$isAuthourized) return ['updated' => false, 'message' => 'You do not have permission to update product'];
                if(!$this->checkIfHasRelationOnRequest('store')) $product->unsetRelation('store');
            }else{
                return ['updated' => false, 'message' => 'This store does not exist'];
            }

            $product->update($data);
            return $this->showUpdatedResource($product);

        }else{
            return ['updated' => false, 'message' => 'This product does not exist'];
        }
    }

    /**
     * Delete product.
     *
     * @param string $productId
     * @return array
     */
    public function deleteProduct(string $productId): array
    {
        $product = Product::with(['store'])->find($productId);

        if($product) {
            $store = $product->store;
            if($store) {
                $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                if(!$isAuthourized) return ['deleted' => false, 'message' => 'You do not have permission to delete product'];
            }else{
                return ['deleted' => false, 'message' => 'This store does not exist'];
            }

            $deleted = $product->delete();

            if ($deleted) {
                return ['deleted' => true, 'message' => 'Product deleted'];
            }else{
                return ['deleted' => false, 'message' => 'Product delete unsuccessful'];
            }
        }else{
            return ['deleted' => false, 'message' => 'This product does not exist'];
        }
    }

    /**
     * Show product photos.
     *
     * @param string $productId
     * @return MediaFileResources|array
     */
    public function showProductPhotos(string $productId): MediaFileResources|array
    {
        $product = Product::with(['store'])->find($productId);

        if($product) {
            $store = $product->store;
            if($store) {
                $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                if(!$isAuthourized) return ['message' => 'You do not have permission to show product photos'];
            }else{
                return ['message' => 'This store does not exist'];
            }
            return $this->getMediaFileRepository()->setQuery($product->photos())->showMediaFiles();
        }else{
            return ['message' => 'This product does not exist'];
        }
    }

    /**
     * Create product photo(s).
     *
     * @param string $productId
     * @return array
     */
    public function createProductPhoto(string $productId): array
    {
        $product = Product::with(['store'])->find($productId);

        if($product) {
            $store = $product->store;
            if($store) {
                $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                if(!$isAuthourized) return ['message' => 'You do not have permission to create product photos'];
            }else{
                return ['created' => false, 'message' => 'This store does not exist'];
            }
            return $this->getMediaFileRepository()->authourize()->createMediaFile(RequestFileName::PRODUCT_PHOTO, $product);
        }else{
            return ['created' => false, 'message' => 'This product does not exist'];
        }
    }

    /**
     * Show product photo.
     *
     * @param string $productId
     * @param string $photoId
     * @return array
     */
    public function showProductPhoto(string $productId, string $photoId): array
    {
        $product = Product::with(['store'])->find($productId);

        if($product) {
            $store = $product->store;
            if($store) {
                $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                if(!$isAuthourized) return ['message' => 'You do not have permission to show product photo'];
            }else{
                return ['message' => 'This store does not exist'];
            }
            return $this->getMediaFileRepository()->setQuery($product->photos())->showMediaFile($photoId);
        }else{
            return ['message' => 'This product does not exist'];
        }
    }

    /**
     * Update product photo.
     *
     * @param string $productId
     * @param string $photoId
     * @return array
     */
    public function updateProductPhoto(string $productId, string $photoId): array
    {
        $product = Product::with(['store'])->find($productId);

        if($product) {
            $store = $product->store;
            if($store) {
                $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                if(!$isAuthourized) return ['message' => 'You do not have permission to update product photo'];
            }else{
                return ['message' => 'This store does not exist'];
            }
            return $this->getMediaFileRepository()->authourize()->setQuery($product->photos())->updateMediaFile($photoId);
        }else{
            return ['message' => 'This product does not exist'];
        }
    }

    /**
     * Delete product photo.
     *
     * @param string $productId
     * @param string $photoId
     * @return array
     */
    public function deleteProductPhoto(string $productId, string $photoId): array
    {
        $product = Product::with(['store'])->find($productId);

        if($product) {
            $store = $product->store;
            if($store) {
                $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                if(!$isAuthourized) return ['message' => 'You do not have permission to delete product photo'];
            }else{
                return ['message' => 'This store does not exist'];
            }
            return $this->getMediaFileRepository()->setQuery($product->photos())->deleteMediaFile($photoId);
        }else{
            return ['message' => 'This product does not exist'];
        }
    }

    /**
     * Show product variations.
     *
     * @param string $productId
     * @return ProductResources|array
     */
    public function showProductVariations(string $productId): ProductResources|array
    {
        $product = Product::with(['store'])->find($productId);

        if($product) {
            $this->setQuery($product->variations());
            $store = $product->store;
            if($store) {
                $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                if(!$isAuthourized) return ['message' => 'You do not have permission to show product variations'];
            }else{
                return ['message' => 'This store does not exist'];
            }
        }else{
            return ['message' => 'This product does not exist'];
        }

        return $this->applyFiltersOnQuery()->getOrCountResources();
    }

    /**
     *  Create product variations.
     *
     * @param Product|string $productId
     * @param array $data
     * @return ProductResources|array
     */
    public function createProductVariations(Product|string $productId, array $data): ProductResources|array
    {
        $product = Product::with(['store'])->find($productId);

        if($product) {
            $store = $product->store;
            if($store) {
                $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                if(!$isAuthourized) return ['message' => 'You do not have permission to show create product variations'];
            }else{
                return ['message' => 'This store does not exist'];
            }
        }else{
            return ['message' => 'This product does not exist'];
        }

        $variantAttributes = $data['variant_attributes'];
        $variantAttributes = $this->normalizeVariantAttributes($variantAttributes);
        $variantAttributeMatrix = $this->generateVariantAttributeMatrix($variantAttributes);
        $productVariationTemplates = $this->generateProductVariationTemplates($product, $variantAttributes, $variantAttributeMatrix);
        [$matchedProductVariations, $unMatchedProductVariations] = $this->matchProductVariations($product, $productVariationTemplates);
        $totalProductVariations = $matchedProductVariations->count() + $unMatchedProductVariations->count();

        if($totalProductVariations > Product::MAXIMUM_VARIATIONS_PER_PRODUCT) {
            return ['message' => 'This product has '.$totalProductVariations.' variations which is greater than the maximum limit of '.Product::MAXIMUM_VARIATIONS_PER_PRODUCT.' variations per product'];
        }

        if ($unMatchedProductVariations->count()) {
            $this->deleteUnmatchedProductVariations($unMatchedProductVariations);
        }

        if ($productVariationTemplates->count()) {
            $this->createNewProductVariations($productVariationTemplates, $product, $variantAttributes);
        }

        $this->setQuery($product->variations());
        return $this->applyFiltersOnQuery()->getOrCountResources();
    }

    /***********************************************
     *             MISCELLANEOUS METHODS           *
     **********************************************/

    /**
     * Query product by ID.
     *
     * @param Product|string $productId
     * @param array $relationships
     * @return Builder|Relation
     */
    public function queryProductById(Product|string $productId, array $relationships = []): Builder|Relation
    {
        return $this->query->where('products.id', $productId)->with($relationships);
    }

    /**
     * Get product by ID.
     *
     * @param Product|string $productId
     * @param array $relationships
     * @return Product|null
     */
    public function getProductById(Product|string $productId, array $relationships = []): Product|null
    {
        return $this->queryProductById($productId, $relationships)->first();
    }

    /**
     * Query products by IDs.
     *
     * @param array<string> $productId
     * @param string $relationships
     * @return Builder|Relation
     */
    public function queryProductsByIds($productIds): Builder|Relation
    {
        return $this->query->whereIn('products.id', $productIds);
    }

    /**
     * Get products by IDs.
     *
     * @param array<string> $productId
     * @param string $relationships
     * @return Collection
     */
    public function getProductsByIds($productIds): Collection
    {
        return $this->queryProductsByIds($productIds)->get();
    }

    /**
     * Normalize variant attributes.
     *
     * @param array $variantAttributes
     * @return Collection
     */
    private function normalizeVariantAttributes(array $variantAttributes): Collection
    {
        return collect($variantAttributes)->map(function ($variantAttribute) {
            $variantAttribute['name'] = ucfirst($variantAttribute['name']);
            if (!isset($variantAttribute['instruction']) || empty($variantAttribute['instruction'])) {
                $variantAttribute['instruction'] = 'Select option';
            }
            return $variantAttribute;
        });
    }

    /**
     * Generate variant attribute matrix.
     *
     * @param Collection $variantAttributes
     * @return array
     */
    private function generateVariantAttributeMatrix(Collection $variantAttributes): array
    {
        $variantAttributesRestructured = $variantAttributes->mapWithKeys(function ($variantAttribute) {
            return [$variantAttribute['name'] => $variantAttribute['values']];
        });

        return Arr::crossJoin(...$variantAttributesRestructured->values());
    }

    /**
     * Generate product variation templates.
     *
     * @param \App\Models\Product $product
     * @param Collection $variantAttributes
     * @param array $variantAttributeMatrix
     * @return Collection
     */
    private function generateProductVariationTemplates(Product $product, Collection $variantAttributes, array $variantAttributeMatrix): Collection
    {
        return collect($variantAttributeMatrix)->map(function ($options) use ($product, $variantAttributes) {
            $name = $product->name . ' (' . trim(collect($options)->map(fn($option) => ucfirst($option))->join(', ')) . ')';

            $template = [
                'id' => Str::uuid(),
                'name' => $name,
                'created_at' => now(),
                'updated_at' => now(),
                'store_id' => $product->store_id,
                'parent_product_id' => $product->id,
                'user_id' => request()->current_user->id,
                'variableTemplates' => collect($options)->map(function ($option, $key) use ($variantAttributes) {
                    $variantAttributeNames = $variantAttributes->keys();
                    return [
                        'id' => Str::uuid(),
                        'value' => $option,
                        'name' => $variantAttributeNames->get($key),
                    ];
                })
            ];

            return $template;
        });
    }

    /**
     * Match product variations.
     *
     * @param Product $product
     * @param Collection $productVariationTemplates
     * @return Collection
     */
    private function matchProductVariations(Product $product, Collection &$productVariationTemplates): Collection
    {
        $existingProductVariations = $product->variations()->with('variables')->get();

        return $existingProductVariations->partition(function ($existingProductVariation) use (&$productVariationTemplates) {
            $result1 = $existingProductVariation->variables->mapWithKeys(function ($variable) {
                return [$variable->name => $variable->value];
            });

            return collect($productVariationTemplates)->contains(function ($productVariationTemplate, $key) use ($result1, &$productVariationTemplates) {
                $result2 = collect($productVariationTemplate['variableTemplates'])->mapWithKeys(function ($variable) {
                    return [$variable['name'] => $variable['value']];
                });

                $exists = $result1->diffAssoc($result2)->isEmpty() && $result2->diffAssoc($result1)->isEmpty();

                if ($exists) {
                    $productVariationTemplates->forget($key);
                }

                return $exists;
            });
        });
    }

    /**
     * Delete unmatched product variations.
     *
     * @param Collection $unMatchedProductVariations
     */
    private function deleteUnmatchedProductVariations(Collection $unMatchedProductVariations): void
    {
        $unMatchedProductVariations->each(fn($unMatchedProductVariation) => $unMatchedProductVariation->delete());
    }

    /**
     * Create new product variations.
     *
     * @param Collection $productVariationTemplates
     * @param \App\Models\Product $product
     * @param Collection $variantAttributes
     */
    private function createNewProductVariations(Collection $productVariationTemplates, Product $product, Collection $variantAttributes): void
    {
        Product::insert(
            $productVariationTemplates->map(
                fn($productVariationTemplate) => collect($productVariationTemplate)->only(['id', 'name', 'parent_product_id', 'user_id', 'store_id', 'created_at', 'updated_at'])
            )->toArray()
        );

        $existingProductVariations = $product->variations()->get();
        $variableTemplates = $this->generateVariableTemplates($existingProductVariations, $productVariationTemplates);

        Variable::insert($variableTemplates->toArray());
        $totalVariations = $product->variations()->count();
        $totalVisibleVariations = $product->variations()->visible()->count();

        $product->update([
            'allow_variations' => true,
            'total_variations' => $totalVariations,
            'variant_attributes' => $variantAttributes,
            'total_visible_variations' => $totalVisibleVariations
        ]);
    }

    /**
     * Generate variable templates.
     *
     * @param Collection $existingProductVariations
     * @param Collection $productVariationTemplates
     * @return Collection
     */
    private function generateVariableTemplates(Collection $existingProductVariations, Collection $productVariationTemplates): Collection
    {
        return $existingProductVariations->flatMap(function ($existingProductVariation) use ($productVariationTemplates) {

            $productVariationTemplate = $productVariationTemplates->first(fn($productVariationTemplate) => $existingProductVariation->name === $productVariationTemplate['name']);

            if ($productVariationTemplate) {
                $variableTemplates = $productVariationTemplate['variableTemplates'];

                return collect($variableTemplates)->map(function ($variableTemplate) use ($existingProductVariation) {
                    $variableTemplate['product_id'] = $existingProductVariation->id;
                    return $variableTemplate;
                });
            }

            return [];
        });
    }
}
