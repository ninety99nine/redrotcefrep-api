<?php

namespace App\Repositories;

use App\Models\Product;
use App\Models\Variable;
use App\Traits\AuthTrait;
use App\Traits\Base\BaseTrait;
use App\Http\Resources\VariableResources;

class VariableRepository extends BaseRepository
{
    use AuthTrait, BaseTrait;

    /**
     * Show variables.
     *
     * @param array $data
     * @return VariableResources|array
     */
    public function showVariables(array $data = []): VariableResources|array
    {
        if($this->getQuery() == null) {

            $productId = isset($data['product_id']) ? $data['product_id'] : null;

            if($productId) {
                $product = Product::find($productId);
                if($product) {
                    $this->setQuery($product->variables());
                }else{
                    return ['message' => 'This product does not exist'];
                }
            }else {
                if(!$this->isAuthourized()) return ['message' => 'You do not have permission to show variables'];
                $this->setQuery(Variable::query());
            }
        }

        return $this->getOutput();
    }

    /**
     * Show variable.
     *
     * @param string $variableId
     * @return Variable|array|null
     */
    public function showVariable(string $variableId): Variable|array|null
    {
        $query = Variable::whereId($variableId);
        $this->setQuery($query)->applyEagerLoadingOnQuery();
        $variable = $this->query->first();

        return $this->showResourceExistence($variable);
    }

    /***********************************************
     *             MISCELLANEOUS METHODS           *
     **********************************************/
}
