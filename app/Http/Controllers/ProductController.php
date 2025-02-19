<?php

namespace App\Http\Controllers;

use App\Enums\Association;
use Illuminate\Http\JsonResponse;
use App\Repositories\ProductRepository;
use App\Http\Controllers\Base\BaseController;
use App\Http\Requests\Models\Product\ShowProductsRequest;
use App\Http\Requests\Models\Product\CreateProductRequest;
use App\Http\Requests\Models\Product\UpdateProductRequest;
use App\Http\Requests\Models\Product\DeleteProductsRequest;
use App\Http\Requests\Models\Product\CreateProductPhotoRequest;
use App\Http\Requests\Models\Product\CreateProductVariationsRequest;
use App\Http\Requests\Models\Product\UpdateProductVisibilityRequest;
use App\Http\Requests\Models\Product\UpdateProductArrangementRequest;

class ProductController extends BaseController
{
    /**
     *  @var ProductRepository
     */
    protected $repository;

    /**
     * ProductController constructor.
     *
     * @param ProductRepository $repository
     */
    public function __construct(ProductRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Show products.
     *
     * @param ShowProductsRequest $request
     * @param string|null $storeId
     * @return JsonResponse
     */
    public function showProducts(ShowProductsRequest $request): JsonResponse
    {
        if($request->storeId) {
            $request->merge(['store_id' => $request->storeId]);
        }

        if($request->route()->named('show.store.shopping.products')) {
            $request->merge(['association' => Association::SHOPPER->value]);
        }

        return $this->prepareOutput($this->repository->showProducts($request->all()));
    }

    /**
     * Create product.
     *
     * @param CreateProductRequest $request
     * @return JsonResponse
     */
    public function createProduct(CreateProductRequest $request): JsonResponse
    {
        return $this->prepareOutput($this->repository->createProduct($request->all()));
    }

    /**
     * Delete products.
     *
     * @param DeleteProductsRequest $request
     * @return JsonResponse
     */
    public function deleteProducts(DeleteProductsRequest $request): JsonResponse
    {
        return $this->prepareOutput($this->repository->deleteProducts($request->all()));
    }

    /**
     * Update product visibility.
     *
     * @param UpdateProductVisibilityRequest $request
     * @return JsonResponse
     */
    public function updateProductVisibility(UpdateProductVisibilityRequest $request)
    {
        return $this->prepareOutput($this->repository->updateProductVisibility($request->all()));
    }

    /**
     * Update product arrangement.
     *
     * @param UpdateProductArrangementRequest $request
     * @return JsonResponse
     */
    public function updateProductArrangement(UpdateProductArrangementRequest $request)
    {
        return $this->prepareOutput($this->repository->updateProductArrangement($request->all()));
    }

    /**
     * Show product.
     *
     * @param string $productId
     * @return JsonResponse
     */
    public function showProduct(string $productId): JsonResponse
    {
        return $this->prepareOutput($this->repository->showProduct($productId));
    }

    /**
     * Update product.
     *
     * @param UpdateProductRequest $request
     * @param string $productId
     * @return JsonResponse
     */
    public function updateProduct(UpdateProductRequest $request, string $productId): JsonResponse
    {
        return $this->prepareOutput($this->repository->updateProduct($productId, $request->all()));
    }

    /**
     * Delete product.
     *
     * @param string $productId
     * @return JsonResponse
     */
    public function deleteProduct(string $productId): JsonResponse
    {
        return $this->prepareOutput($this->repository->deleteProduct($productId));
    }

    /**
     * Show product photos.
     *
     * @param string $productId
     * @return JsonResponse
     */
    public function showProductPhotos(string $productId): JsonResponse
    {
        return $this->prepareOutput($this->repository->showProductPhotos($productId));
    }

    /**
     * Create product photo.
     *
     * @param CreateProductPhotoRequest $request
     * @param string $productId
     * @return JsonResponse
     */
    public function createProductPhoto(CreateProductPhotoRequest $request, string $productId): JsonResponse
    {
        return $this->prepareOutput($this->repository->createProductPhoto($productId));
    }

    /**
     * Show product photo.
     *
     * @param string $productId
     * @param string $photoId
     * @return JsonResponse
     */
    public function showProductPhoto(string $productId, string $photoId): JsonResponse
    {
        return $this->prepareOutput($this->repository->showProductPhoto($productId, $photoId));
    }

    /**
     * Update product photo.
     *
     * @param string $productId
     * @param string $photoId
     * @return JsonResponse
     */
    public function updateProductPhoto(string $productId, string $photoId): JsonResponse
    {
        return $this->prepareOutput($this->repository->updateProductPhoto($productId, $photoId));
    }

    /**
     * Delete product photo.
     *
     * @param string $productId
     * @param string $photoId
     * @return JsonResponse
     */
    public function deleteProductPhoto(string $productId, string $photoId): JsonResponse
    {
        return $this->prepareOutput($this->repository->deleteProductPhoto($productId, $photoId));
    }

    /**
     * Show product variations.
     *
     * @param string $productId
     * @return JsonResponse
     */
    public function showProductVariations(string $productId): JsonResponse
    {
        return $this->prepareOutput($this->repository->showProductVariations($productId));
    }

    /**
     * Create product variations.
     *
     * @param CreateProductVariationsRequest $request
     * @param string $productId
     * @return JsonResponse
     */
    public function createProductVariations(CreateProductVariationsRequest $request, string $productId): JsonResponse
    {
        return $this->prepareOutput($this->repository->createProductVariations($productId, $request->all()));
    }
}
