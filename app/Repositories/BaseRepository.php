<?php

namespace App\Repositories;

use \Exception;
use App\Traits\AuthTrait;
use App\Enums\ReturnType;
use Illuminate\Support\Str;
use App\Traits\Base\BaseTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use App\Services\ShoppingCart\ShoppingCartService;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * Class BaseRepository
 *
 * Abstract class to serve as a base for other repositories.
 */
abstract class BaseRepository
{
    use AuthTrait, BaseTrait;

    protected $query = null;
    protected int $perPage = 15;
    protected int $maxPerPage = 50;
    protected $authourized = false;
    protected $returnType = ReturnType::ARRAY;
    protected string|null $resourceName = null;
    protected string|null $modelClassName = null;
    protected string|null $resourceClassName = null;
    protected string|null $resourceCollectionClassName = null;

    /**
     * Check if is authourized.
     *
     * @return bool
     */
    protected function isAuthourized(): bool
    {
        return $this->authourized || $this->authUserIsSuperAdmin();
    }

    /**
     * Authourize.
     *
     * @return $this
     */
    protected function authourize(): self
    {
        $this->authourized = true;
        return $this;
    }

    /**
     * Should return model.
     *
     * @return $this
     */
    protected function shouldReturnModel(): self
    {
        $this->returnType = ReturnType::MODEL;
        return $this;
    }

    /**
     * Get query.
     *
     * @return Builder|Relation|null
     */
    protected function getQuery(): Builder|Relation|null
    {
        return $this->query;
    }

    /**
     * Set query.
     *
     * @param Builder|Relation $query
     * @return $this
     */
    public function setQuery(Builder|Relation $query): self
    {
        $this->query = $query;
        return $this;
    }

    /**
     * Get the model class name.
     *
     * @return string
     */
    private function getModelClassName(): string
    {
        return $this->modelClassName ?? $this->getFallbackModelClassName();
    }

    /**
     * Get the fallback model class name.
     *
     * @return string
     */
    private function getFallbackModelClassName(): string
    {
        return 'App\Models\\' . Str::replace('Repository', '', class_basename($this));
    }

    /**
     * Get the resource name.
     *
     * @return string
     */
    private function getResourceName(): string
    {
        return $this->resourceName ?? $this->getFallbackResourceName();
    }

    /**
     * Get the fallback resource name.
     *
     * @return string
     */
    private function getFallbackResourceName(): string
    {
        return ucfirst(Str::snake(Str::replace('Repository', '', class_basename($this)), ' '));
    }

    /**
     * Get the resource class name.
     *
     * @return string
     */
    private function getResourceClassName(): string
    {
        return $this->resourceClassName ?? $this->getFallbackResourceClassName();
    }

    /**
     * Get the fallback resource class name.
     *
     * @return string
     */
    private function getFallbackResourceClassName(): string
    {
        return 'App\Http\Resources\\' . Str::replace('Repository', 'Resource', class_basename($this));
    }

    /**
     * Get the resource collection class name.
     *
     * @return string
     */
    private function getResourceCollectionClassName(): string
    {
        return $this->resourceCollectionClassName ?? $this->getFallbackResourceCollectionClassName();
    }

    /**
     * Get the fallback resource collection class name.
     *
     * @return string
     */
    private function getFallbackResourceCollectionClassName(): string
    {
        return 'App\Http\Resources\\' . Str::replace('Repository', 'Resources', class_basename($this));
    }

    /**
     * Apply search on query.
     *
     * @return self
     */
    protected function applySearchOnQuery(): self
    {
        if (request()->filled('search')) {
            $searchWord = request()->input('search');
            return $this->setQuery($this->query->search($searchWord));
        }

        return $this;
    }

    /**
     * Apply filters on query.
     *
     * How it works
     *
     * -------------------------------------
     * Equality / Non-Equality Filter:
     * -------------------------------------
     *
     * name:eq:John
     *
     * status:eq:active
     * is_active:neq:true
     *
     * created_at:eq:2023-10-16
     * updated_at:gt:2023-01-01
     *
     * -------------------------------------
     * Comparison Filters (>, <, >=, <=):
     * -------------------------------------
     *
     * price:gt:50.00
     * price:lt:100.00
     * created_at:gte:1704067200
     * created_at:lte:1733011200
     *
     * -------------------------------------
     * Range (Between) Filters:
     * -------------------------------------
     *
     * created_at:bt:1704067200:1733011200
     *
     * -------------------------------------
     * Inclusion Filter:
     * -------------------------------------
     *
     * status:in:active,pending
     * payment_status:in:active,pending,failed
     *
     * -------------------------------------
     * Exclusion Filter:
     * -------------------------------------
     *
     * status:not_in:active,pending
     * payment_status:not_in:active,pending,failed
     *
     * -------------------------------------
     * LIKE Filter
     * -------------------------------------
     *
     * name:like:Joh
     *
     * -------------------------------------
     * JSON Field Filters
     * -------------------------------------
     *
     * options->languages:eq:en
     * metadata->sms_credits:gte:100
     *
     * -------------------------------------
     * Combination
     * -------------------------------------
     *
     * name:eq:John|price:gt:50.00|created_at:gte:1704067200|metadata->sms_credits:gte:100
     *
     * @return self
     */
    protected function applyFiltersOnQuery(): self
    {
        if (request()->filled('_filters')) {
            $filters = explode('|', request()->input('_filters'));
            $filters = collect($filters)->map(fn($filter) => trim($filter));

            foreach($filters as $filter) {
                $this->applyFilterOnQuery($filter);
            }
        }

        return $this;
    }

    /**
     * Apply filter on query.
     *
     * @param string $filter e.g "name:eq:John"
     * @return void
     */
    public function applyFilterOnQuery(string $filter): void
    {
        $extracted = self::extractColumnOperatorAndValue($filter);
        $column = array_shift($extracted);
        $operator = array_shift($extracted);
        $input1 = array_shift($extracted);
        $input2 = array_shift($extracted);

        if(is_null($input1)) throw new Exception('The first value was not provided to filter the ('.$column.') column');
        if(is_null($input2) && $operator == 'bt') throw new Exception('The second value was not provided to filter the ('.$column.') column');

        $modelClassName = $this->getModelClassName();
        $modelInstance = new $modelClassName();
        $casts = $modelInstance->getCasts();

        $columnWithoutArrows = explode('->', $column)[0];
        $isJsonField = isset($casts[$columnWithoutArrows]) && $casts[$columnWithoutArrows] == 'App\Casts\JsonToArray';

        if($isJsonField) {
            $query = $this->applyJsonComparison($column, $operator, $input1, $input2);
        }else if($operator == 'bt') {

            $query = $this->getQuery()
                          ->where($column, '>=', $input1)
                          ->where($column, '<=', $input2);

        }else if($operator == 'bt_ex') {

            $query = $this->getQuery()
                          ->where($column, '>', $input1)
                          ->where($column, '<', $input2);

        }else{

            if($operator == 'in') {
                $options = explode(',', $input1);
                $query = $this->getQuery()->whereIn($column, $options);
            }else if($operator == 'not_in') {
                $options = explode(',', $input1);
                $query = $this->getQuery()->whereNotIn($column, $options);
            }else if($operator == 'like') {
                $query = $this->getQuery()->where($column, $operator, '%'.$input1.'%');
            }else{
                $query = $this->getQuery()->where($column, $operator, $input1);
            }
        }

        $this->setQuery($query);
    }

    /**
     * Extract column, operator and value.
     *
     * @param string $input
     * @return array
     */
    public static function extractColumnOperatorAndValue(string $input): array
    {
        $parts = explode(':', $input);
        $column = array_shift($parts);
        $operator = array_shift($parts);
        $operator = self::convertOperatorToSymbol($operator);

        if(empty($operator)) throw new Exception('The operator must be provided to filter the ('.$column.') column');

        if (count($parts) === 1) {
            $value = self::convertValueToAppropriateType($parts[0]);
            return [$column, $operator, $value];
        } elseif (count($parts) === 2) {
            $value1 = self::convertValueToAppropriateType($parts[0]);
            $value2 = self::convertValueToAppropriateType($parts[1]);
            return [$column, $operator, $value1, $value2];
        }
    }

    /**
     * Convert value to appropriate type.
     *
     * @param string $value
     * @return mixed
     */
    protected static function convertValueToAppropriateType(string $value)
    {
        if (is_numeric($value)) {
            if (strpos($value, '.') !== false) {
                return $value;
            }
            return (int)$value;
        } elseif (strtolower($value) === 'true') {
            return true;
        } elseif (strtolower($value) === 'false') {
            return false;
        } elseif (is_string($value)) {
            return $value;
        }

        return null;
    }

    /**
     * Convert operator to symbol.
     *
     * Input: gte
     * Output: >=
     *
     * @param string $operator
     * @return string|null
     */
    public static function convertOperatorToSymbol(string $operator): string|null
    {
        $operatorMap = [
            'like' => 'like',
            'gte' => '>=',
            'lte' => '<=',
            'gt'  => '>',
            'lt'  => '<',
            'eq'  => '=',
            'neq'  => '!=',
            'in'  => 'in',
            'not_in'  => 'not_in',
            'bt' => 'bt',
            'bt_ex' => 'bt_ex',
        ];

        return $operatorMap[$operator] ?? null;
    }

    /**
     * Apply Json comparison.
     *
     * Input: metadata->user->credits
     * Output: ["metadata", "$.user.credits"]
     *
     * @param string $column
     * @return array [jsonColumn, jsonPath]
     */
    public function applyJsonComparison(string $column, string $operator, $value, $value2 = null)
    {
        [$jsonColumn, $jsonPath] = $this->parseJsonColumnAndPath($column);

        if (empty($jsonPath) || $jsonPath === '$.') $jsonPath = '$';

        if ($operator === 'in') {
            $options = explode(',', $value);
            $query = $this->getQuery();

            $query->whereRaw("JSON_UNQUOTE(JSON_EXTRACT($jsonColumn, '$jsonPath')) = ?", [$options[0]]);

            foreach (array_slice($options, 1) as $option) {
                $query->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT($jsonColumn, '$jsonPath')) = ?", [$option]);
            }

            return $query;
        }

        if ($operator === 'not_in') {
            $options = explode(',', $value);
            $query = $this->getQuery();

            foreach ($options as $option) {
                $query->whereRaw("JSON_UNQUOTE(JSON_EXTRACT($jsonColumn, '$jsonPath')) != ?", [$option]);
            }

            return $query;
        }


        if ($operator === 'like') return $this->getQuery()->whereRaw("LOWER(JSON_UNQUOTE(JSON_EXTRACT($jsonColumn, '$jsonPath'))) LIKE LOWER(?)", ['%' . $value . '%']);
        if ($operator === 'bt') return $this->getQuery()->whereRaw("JSON_UNQUOTE(JSON_EXTRACT($jsonColumn, '$jsonPath')) BETWEEN ? AND ?", [$value, $value2]);
        if ($operator === 'bt_ex') return $this->getQuery()->whereRaw("JSON_UNQUOTE(JSON_EXTRACT($jsonColumn, '$jsonPath')) > ? AND JSON_UNQUOTE(JSON_EXTRACT($jsonColumn, '$jsonPath')) < ?", [$value, $value2]);

        // Handle other operators (">", "<", , "=", "!=", ">=", "<=")
        return $this->getQuery()->whereRaw("LOWER(JSON_UNQUOTE(JSON_EXTRACT($jsonColumn, '$jsonPath'))) $operator LOWER(?)", [$value]);
    }

    /**
     * Parse Json column and path.
     *
     * Input: metadata->user->credits
     * Output: ["metadata", "$.user.credits"]
     *
     * @param string $column
     * @return array [jsonColumn, jsonPath]
     */
    protected function parseJsonColumnAndPath(string $column): array
    {
        // Split the column based on '->' to extract the base column and JSON path parts
        $parts = explode('->', $column);

        // The first part is the actual database column (e.g., 'platforms')
        $jsonColumn = $parts[0];

        // Remaining parts form the JSON path (e.g., 'user.address' becomes '$.user.address')
        $jsonPath = '$.' . implode('.', array_slice($parts, 1));

        return [$jsonColumn, $jsonPath];
    }

    /**
     * Apply eager loading on query.
     *
     * @return self
     */
    protected function applyEagerLoadingOnQuery(): self
    {
        $relationships = $this->getRequestRelationships();
        $countableRelationships = $this->getRequestCountableRelationships();

        if( !empty($relationships) || !empty($countableRelationships) ) {
            $this->setQuery($this->query->with($relationships)->withCount($countableRelationships));
        }

        return $this->setQuery($this->query);
    }

    /**
     * Apply eager loading on model.
     *
     * @param Model $model
     * @return Model
     */
    protected function applyEagerLoadingOnModel(Model $model): Model
    {
        $relationships = $this->getRequestRelationships();
        $countableRelationships = $this->getRequestCountableRelationships();

        if( !empty($relationships) || !empty($countableRelationships) ) {
            return $model->loadMissing($relationships)->loadCount($countableRelationships);
        }

        return $model;
    }

    /**
     * Has request relationships.
     *
     * @return bool
     */
    protected function hasRequestRelationships(): bool
    {
        return count($this->getRequestRelationships());
    }

    /**
     * Has request countable relationships.
     *
     * @return bool
     */
    protected function hasRequestCountableRelationships(): bool
    {
        return count($this->getRequestCountableRelationships());
    }

    /**
     * Get request relationships.
     *
     * @return array
     */
    protected function getRequestRelationships(): array
    {
        return array_filter(array_map('trim', explode(',', request()->input('_relationships') ?? '')));
    }

    /**
     * Get request countable relationships.
     *
     * @return array
     */
    protected function getRequestCountableRelationships(): array
    {
        return array_filter(array_map('trim', explode(',', request()->input('_countable_relationships') ?? '')));
    }

    /**
     * Check if should count resources.
     *
     * @param Model $model
     * @return Model
     */
    protected function checkIfShouldCountResources()
    {
        return $this->isTruthy(request()->input('count'));
    }

    /**
     * Check if should return resource.
     *
     * @param Model $model
     * @return Model
     */
    protected function checkIfShouldReturnResource()
    {
        return $this->isTruthy(request()->input('return'));
    }

    /**
     * Check if should count resources.
     *
     * @param string $relation
     * @return bool
     */
    protected function checkIfHasRelationOnRequest(string $relation): bool
    {
        return Collect($this->getRequestRelationships())->contains($relation);
    }

    /**
     * Get or count resources.
     *
     * @return array|ResourceCollection
     */
    protected function getOrCountResources(): array|ResourceCollection
    {
        $this->applySearchOnQuery();
        $this->applyEagerLoadingOnQuery();

        if($this->checkIfShouldCountResources()) {
            return $this->countResources();
        }else{
            return $this->getResources();
        }
    }

    /**
     * Count resources.
     *
     * @return array
     */
    protected function countResources(): array
    {
        return ['total' => $this->query->count()];
    }

    /**
     * Get resources.
     *
     * @return ResourceCollection
     */
    protected function getResources(): ResourceCollection
    {
        $perPage = request()->filled('per_page') ? (int) request()->input('per_page') : $this->perPage;
        $perPage = ($perPage <= $this->maxPerPage) ? $perPage : $this->perPage;

        $resourceCollectionClassName = $this->getResourceCollectionClassName();
        return new $resourceCollectionClassName($this->query->paginate($perPage));
    }

    /**
     * Show created resource.
     *
     * @param Model|null $model
     * @param string|null $message
     * @return Model|array
     */
    protected function showCreatedResource(Model $model, string|null $message = null): Model|array
    {
        return $this->showSavedResource($model, 'created', $message);
    }

    /**
     * Show updated resource.
     *
     * @param Model|null $model
     * @param string $status
     * @param string|null $message
     * @return Model|array
     */
    protected function showUpdatedResource(Model $model, string|null $message = null): Model|array
    {
        return $this->showSavedResource($model, 'updated', $message);
    }

    /**
     * Show saved resource.
     *
     * @param Model|null $model
     * @param string $status
     * @param string|null $message
     *
     * @return Model|array
     */
    protected function showSavedResource(Model $model, string $status, string|null $message = null): Model|array
    {
        if($this->returnType == ReturnType::MODEL) return $model;

        $resourseName = $this->getResourceName();
        $message ??= "$resourseName $status";

        if($this->checkIfShouldReturnResource()) {

            $resourceClassName = $this->getResourceClassName();
            $resourceKeyName = Str::snake($resourseName);

            if($this->hasRequestRelationships() || $this->hasRequestCountableRelationships()) {
                $model = $this->applyEagerLoadingOnModel($model);
            }

            return [
                $status => true,
                'message' => $message,
                $resourceKeyName => new $resourceClassName($model)
            ];

        }else{
            return [$status => true, 'message' => $message];
        }
    }

    /**
     * Show resource existence.
     *
     * @param Model|null $model
     * @return Model|array|null
     */
    protected function showResourceExistence(Model|null $model): Model|array|null
    {
        if($this->returnType == ReturnType::MODEL) return $model;

        if($this->returnType == ReturnType::ARRAY) {
            $resourceClassName = $this->getResourceClassName();
            $resourceKeyName = Str::snake($this->getResourceName());

            return [
                'exists' => !is_null($model),
                $resourceKeyName => $model ? new $resourceClassName($model) : null
            ];
        }
    }

    /**
     * Show bulk created resources.
     *
     * @param array $models
     * @param string $status
     * @return array
     */
    protected function showBulkCreatedResources(array $models, string $status = 'created'): Collection|array
    {
        if($this->returnType == ReturnType::MODEL) return $models;

        $totalModels = count($models);
        $resourseName = $this->getResourceName();
        $resourseNameInPlural = Str::plural($resourseName);
        $message = $totalModels == 1 ? "$resourseName $status" : $totalModels ." $resourseNameInPlural $status";

        if($this->checkIfShouldReturnResource()) {

            $resourceKeyName = Str::snake($resourseNameInPlural);

            if($this->hasRequestRelationships() || $this->hasRequestCountableRelationships()) {
                foreach($models as $key => $model) {
                    $models[$key] = $this->applyEagerLoadingOnModel($model);
                }
            }

            return [
                $status => true,
                'message' => $message,
                $resourceKeyName => $models
            ];

        }else{
            return [$status => true, 'message' => $message];
        }
    }

    protected function getAuthRepository(): AuthRepository
    {
        return app(AuthRepository::class);
    }

    protected function getCartRepository(): CartRepository
    {
        return app(CartRepository::class);
    }

    protected function getUserRepository(): UserRepository
    {
        return app(UserRepository::class);
    }

    protected function getStoreRepository(): StoreRepository
    {
        return app(StoreRepository::class);
    }

    protected function getOrderRepository(): OrderRepository
    {
        return app(OrderRepository::class);
    }

    protected function getCouponRepository(): CouponRepository
    {
        return app(CouponRepository::class);
    }

    protected function getProductRepository(): ProductRepository
    {
        return app(ProductRepository::class);
    }

    protected function getAddressRepository(): AddressRepository
    {
        return app(AddressRepository::class);
    }

    protected function getOccasionRepository(): OccasionRepository
    {
        return app(OccasionRepository::class);
    }

    protected function getCustomerRepository(): CustomerRepository
    {
        return app(CustomerRepository::class);
    }

    protected function getAiMessageRepository(): AiMessageRepository
    {
        return app(AiMessageRepository::class);
    }

    protected function getShoppingCartService(): ShoppingCartService
    {
        return app(ShoppingCartService::class);
    }

    protected function getMediaFileRepository(): MediaFileRepository
    {
        return app(MediaFileRepository::class);
    }

    protected function getTransactionRepository(): TransactionRepository
    {
        return app(TransactionRepository::class);
    }

    protected function getPricingPlanRepository(): PricingPlanRepository
    {
        return app(PricingPlanRepository::class);
    }

    protected function getFriendGroupRepository(): FriendGroupRepository
    {
        return app(FriendGroupRepository::class);
    }

    protected function getAiAssistantRepository(): AiAssistantRepository
    {
        return app(AiAssistantRepository::class);
    }

    protected function getSubscriptionRepository(): SubscriptionRepository
    {
        return app(SubscriptionRepository::class);
    }

    protected function getPaymentMethodRepository(): PaymentMethodRepository
    {
        return app(PaymentMethodRepository::class);
    }

    protected function getDeliveryAddressRepository(): DeliveryAddressRepository
    {
        return app(DeliveryAddressRepository::class);
    }
}
