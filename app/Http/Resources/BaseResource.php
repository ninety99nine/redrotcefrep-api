<?php

namespace App\Http\Resources;

use App\Helpers\PayloadLimiter;
use Illuminate\Support\Str;
use App\Models\Base\BasePivot;
use Illuminate\Database\Eloquent\Model;
use App\Http\Resources\Helpers\ResourceLink;
use App\Models\Base\BaseModel;
use App\Models\Pivots\UserStoreAssociation;
use App\Traits\Base\BaseTrait;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Resources\Json\JsonResource;

class BaseResource extends JsonResource
{
    use BaseTrait;

    /**
     *  Check if this user is a Super Admin
     */
    protected $isSuperAdmin;

    /**
     *  Check if this user is an Authourized User
     */
    protected $isAuthourizedUser;

    /**
     *  Check if this user is an Public User
     */
    protected $isPublicUser;

    /**
     *  Fields to overide the default provided fillable fields of the model
     */
    protected $customFields = null;

    /**
     *  Fields to add to be part of the final transformed fields
     */
    protected $customIncludeFields = null;

    /**
     *  Fields to remove from being part of the final transformed fields
     */
    protected $customExcludeFields = null;

    /**
     *  Attributes to overide the default provided appends of the model
     */
    protected $customAttributes = null;

    /**
     *  Attributes to add to be part of the final transformed attributes
     */
    protected $customIncludeAttributes = null;

    /**
     *  Attributes to remove from being part of the final transformed attributes
     */
    protected $customExcludeAttributes = null;

    /**
     *  Relationships to overide the default provided relationships of the model
     */
    protected $customRelationships = null;

    /**
     *  Relationships to add to be part of the final relationships
     */
    protected $customIncludeRelationships = null;

    /**
     *  Relationships to remove from being part of the final relationships
     */
    protected $customExcludeRelationships = null;

    /**
     *  Whether to show the attributes payload
     */
    protected $showAttributes = true;

    /**
     *  The resource links
     */
    protected $resourceLinks = [];

    /**
     *  Whether to show the links payload
     */
    protected $showLinks = true;

    /**
     *  The resource relationships
     */
    protected $resourceRelationships = [];

    /**
     *  Whether to show the relationships payload
     */
    protected $showRelationships = true;

    public function __construct($resource)
    {
        /**
         *  Run the normal Laravel JsonResource __construct() method
         *  so that Laravel can do the usual procedure before we
         *  run our additional logic.
         */
        parent::__construct($resource);

        /**
         *  If this action is performed by a user to their own profile,
         *  then this is considered an authourized user
         *
         *  The following is checking if this action was performed
         *  under the route names:
         *
         *  "auth.login", "auth.register", "auth.login", "auth.reset.password", e.t.c
         */
        $this->isAuthourizedUser = request()->routeIs('auth.*');
        $this->isSuperAdmin = ($user = request()->auth_user) ? $user->isSuperAdmin() : false;
        $this->isPublicUser = !($this->isAuthourizedUser || $this->isSuperAdmin);

        //  If the request does not intend to disable casting completely
        if( !$this->isTruthy(request()->input('_no_casting')) ) {


            /**
             *  Some Models such as the Laravel Based Models e.g Illuminate\Notifications\DatabaseNotification
             *  which is the Laravel Notification Model do not implement our custom getTranformableCasts(),
             *  therefore we need to check whether or not this resource contains this method before we
             *  attempt to call it.
             */
            if( ($this->resource instanceof Model) && method_exists($this->resource, 'getTranformableCasts') ) {

                /**
                 *  Cast the fields of this resource
                 *
                 *  Apply the temporary cast at runtime using the mergeCasts method.
                 *  These cast definitions will be added to any of the casts already
                 *  defined on the model
                 *
                 *  Refer to: https://laravel.com/docs/8.x/eloquent-mutators
                 */
                $this->mergeCasts($this->getTranformableCasts());

            }

        }
    }

    /**
     *  Transform the resource into an array.
     */
    public function toArray($request)
    {
        return $this->transformedStructure();
    }

    /**
     *  Transform the resource into an array from transformable
     *  fields and attributes of the model instance
     */
    public function transformedStructure()
    {
        //  If the request does not intend to hide the fields completely
        if( request()->input('_no_fields') ) {

            $data = [];

        }else{

            //  Set the transformable model fields
            $data = $this->extractFields();

        }

        //  If the request does not intend to hide the links completely
        if( !request()->input('_no_links') ) {

            //  Set the transformable model links (If permitted)
            $links = $this->showLinks ? $this->getLinks() : [];

            if (count($links) > 0) {

                $data['_links'] = $links;

            }

        }

        //  If the request does not intend to hide the attributes completely
        if( !request()->input('_no_attributes') ) {

            if( $this->resource instanceof Model ) {

                //  Set the transformable model attributes (If permitted)
                $attributes = $this->showAttributes ? $this->extractAttributes() : [];

                if (count($attributes) > 0) {

                    $data['_attributes'] = $attributes;

                }

            }

        }

        //  If the request does not intend to hide the relationships completely
        if( !request()->input('_no_relationships') ) {

            if( $this->resource instanceof Model ) {

                //  Set the transformable model relationships (If permitted)
                $relationships = $this->showRelationships ? $this->getRelationships() : [];

                if (count($relationships) > 0) {

                    $data['_relationships'] = $relationships;

                }

            }

        }

        return $data;
    }

    /**
     *  Return the transformable model fields
     */
    public function extractFields()
    {
        if( $this->resource instanceof Model ) {

            /**
             *  The withoutRelations() returns a clone $this->resource without its loaded relationships.
             *  We implement this method so that relationships loaded on it are not returned as fields.
             *  We will return relationships as paylaod of the "_relationships" part of this transformed
             *  resource. Refer to the HasRelationships to class see how the withoutRelations() works.
             *
             *  Illuminate\Database\Eloquent\Concerns\HasRelationships
             */
            $data = $this->resource->withoutRelations();

            /**
             * The setAppends([]) helps us to exclude any appended attributes.
             * These are the non database fields.
             */
            $data->setAppends([]);

            $data = $data->toArray();

        }else{

            $data = $this->resource;

            /**
             *  We need to iterate over the values of the $data array and make sure that
             *  each and every instance of BaseResource is first transformed before we
             *  proceed any further since the PayloadLimiter will convert any object
             *  into an array, which would not allow these resource objects to be
             *  transformed, but will be simply converted to array format.
             *
             *  We can iterate over each element and confirm if each element is an instance of
             *  BaseResource and therefore run transformedStructure() to covert that value
             *  from its resource object format to its transformed array format before
             *  we proceed any further. This functionality is required for instance in
             *  the HomeController.php showApiHome() method. We return the following:
             *
             *  return (new HomeResource([
             *      'user' => new UserResource($authUser)
             *      ...
             *  ]));
             *
             *  We need to transform the result of UserResource($authUser) before
             *  the PayloadLimiter() method below:
             *
             *  Convert:
             *
             *  [
             *      'user' => new UserResource($authUser)
             *      ...
             *  ]
             *
             *  Into an array. We should have something like this:
             *
             *  [
             *      'user' => [
             *          'first_name' => 'John',
             *          'last_name' => 'Doe',
             *          _links => [
             *              'show.user' =>
             *          ]
             *          ...
             *      ]
             *      ...
             *  ]
             *
             *  Now we may proceed
             */
            foreach($data as $key => $value) {

                if($value instanceof BaseResource) {

                    $data[$key] = $value->transformedStructure();

                }

            }

        }

        //  If the request intends to specify specific fields to show
        if( request()->filled('_include_fields') == true ) {

            $data = (new PayloadLimiter($data, request()->input('_include_fields')))->getLimitedPayload();

        }

        //  If the request intends to specify specific fields to exclude
        if( request()->filled('_exclude_fields') == true ) {

            //  Capture the fields that we must exclude
            $fieldsToExclude = Str::of( request()->input('_exclude_fields') )->explode(',')->map(function($field) {

                return Str::replace(' ', '', Str::snake($field));

            })->toArray();

            $data = collect($data)->filter(function($value, $key) use ($fieldsToExclude) {

                return !in_array($key, $fieldsToExclude);

            })->toArray();

        }

        return $data;
    }

    /**
     *  Return the transformable model attributes
     */
    public function extractAttributes()
    {
        /**
         *  Some Models such as the Laravel Based Models e.g Illuminate\Notifications\DatabaseNotification
         *  which is the Laravel Notification Model do not implement our custom getTransformableAppends(),
         *  therefore we need to check whether or not this resource contains this method before we
         *  attempt to call it.
         */
        if( $this->customAttributes === null && method_exists($this->resource, 'getTransformableAppends') ) {

            //  Get model attributes (Method defined in the App\Models\Traits\BaseTrait)
            $attributes = $this->getTransformableAppends();

        }else if($this->customAttributes !== null) {

            //  Get model attributes (Method defined by this Resource Class)
            $attributes = $this->customAttributes;

        }else{

            $attributes = [];

        }

        //  Retun an empty array on empty attributes
        if(empty($attributes)) return [];

        //  Include additional custom attributes
        $attributes = $this->customIncludeAttributes === null ? $attributes : collect($attributes)->merge($this->customIncludeAttributes)->toArray();

        //  Exclude additional custom attributes
        $attributes = $this->customExcludeAttributes === null ? $attributes : collect($attributes)->filter(fn($field) => !in_array($field, $this->customExcludeAttributes))->toArray();

        //  Return the attributes as key-value pairs
        $attributes = collect($attributes)->mapWithKeys(fn($key) => [$key => $this->$key])->all();

        //  Retun an empty array on empty attributes
        if( collect($attributes)->count() == 0 ) return [];

        //  Foreach attribute
        foreach($attributes as $attribute) {

            //  If the attribute represents a Model as Pivot
            if($attribute instanceof BasePivot) {

                //  If the request does not intend to disable casting completely
                if( !$this->isTruthy(request()->input('_no_casting')) ) {

                    /**
                     *  Cast the fields of this resource
                     *
                     *  Apply the temporary cast at runtime using the mergeCasts method.
                     *  These cast definitions will be added to any of the casts already
                     *  defined on the model
                     *
                     *  Refer to: https://laravel.com/docs/8.x/eloquent-mutators
                     */
                    $attribute->mergeCasts($attribute->getTranformableCasts());

                }

            }

        }

        //  Convert attributes to an array
        $attributes = collect($attributes)->toArray();

        //  If the request intends to specify specific attributes to show
        if( request()->filled('_include_attributes') == true ) {

            $attributes = (new PayloadLimiter($attributes, request()->input('_include_attributes')))->getLimitedPayload();

        }

        //  If the request intends to specify specific attributes to exclude
        if( request()->filled('_exclude_attributes') == true ) {

            //  Capture the attributes that we must exclude
            $fieldsToExclude = Str::of( request()->input('_exclude_attributes') )->explode(',')->map(function($field) {

                return Str::replace(' ', '', Str::snake($field));

            })->toArray();

            $attributes = collect($attributes)->filter(function($value, $key) use ($fieldsToExclude) {

                return !in_array($key, $fieldsToExclude);

            })->toArray();

        }

        return $attributes;
    }

    /**
     *  Overide to provide the links
     */
    public function setLinks()
    {
        $this->resourceLinks = [];
    }

    /**
     *  Return the transformable model links
     */
    public function getLinks()
    {
        $this->setLinks();

        //  If the request intends to specify specific links to show
        if( request()->filled('_include_links') == true ) {

            //  Capture the links that we must include
            $linksToInclude = Str::of( strtolower( Str::replace(' ', '', request()->input('_include_links') ) ) )->explode(',')->toArray();

            $this->resourceLinks = collect($this->resourceLinks)->filter(function($link) use ($linksToInclude) {

                /**
                 *  Note that since the link name can be represented in dot notation we need to check different cases
                 */
                return in_array(str_replace('.', '', $link->name), $linksToInclude);

            })->toArray();

        //  If the request intends to specify specific links to exclude
        }elseif( request()->filled('_exclude_links') == true ) {

            //  Capture the links that we must exclude
            $linksToExclude = Str::of( strtolower( Str::replace(' ', '', request()->input('_exclude_links') ) ) )->explode(',')->toArray();

            $this->resourceLinks = collect($this->resourceLinks)->filter(function($link) use ($linksToExclude) {

                /**
                 *  Note that since the link name can be represented in dot notation we need to check different cases
                 */
                return in_array(str_replace('.', '', $link->name), $linksToExclude);

            })->toArray();

        }

        return collect($this->resourceLinks)->map(fn(ResourceLink $resourceLink) => $resourceLink->getLink())->collapse();
    }

    /**
     *  Return the transformable model relationships
     */
    public function getRelationships()
    {
        $resourceRelationships = $this->resource->getRelations();

        /**
         *  Capture the model relationships that are permitted for sharing.
         *  Foreach relationship available on the current model resource,
         *  we must check if that relationship is permitted for sharing.
         */
        if($this->customExcludeRelationships !== null) {
            $resourceRelationships = collect($resourceRelationships)->filter(function ($relationship, $relationshipName) {
                return !in_array($relationshipName, $this->customExcludeRelationships);
            });
        }

        /**
         *  The $relationship may be a single Eloquent Model or it may
         *  be an Eloquent Collection. The $relationshipName is the
         *  name of that relationship e.g orders, products, e.t.c
         *
         *  Each relationship must be transformed according to its
         *  corresponding model repository class
         */
        $relationships = collect($resourceRelationships)->mapWithKeys(function($relationship, $relationshipName) {

            $transformedRelationship = null;

            //  If the nested relationship is a single BasePivot
            if($relationship instanceof BaseModel || $relationship instanceof BasePivot) {

                $resourceClass = $this->getResourceClass($relationship);
                $transformedRelationship = new $resourceClass($relationship);

            //  If the nested relationship is a Collection or Array of single Models
            }else if($relationship instanceof Collection || is_array($relationship)) {

                //  If the relationship is an Array, convert to a Collection
                if( is_array($relationship) ) $relationship = collect($relationship);

                if($relationship->count() == 0) {

                    $transformedRelationship =  [];

                }else{

                    $resourceCollectionClass = $this->getResourceCollectionClass($relationship->first());
                    $transformedRelationship = new $resourceCollectionClass($relationship);

                }

            }

            //  Remove this relationship from the resource
            $this->resource->unsetRelation($relationshipName);

            return [$relationshipName => $transformedRelationship];

        })->all();

        //  Return the relationships
        return $relationships;

    }

    private function getResourceClass($model) {
        return '\App\Http\Resources\\' . class_basename($model).'Resource';
    }

    private function getResourceCollectionClass($model) {
        return '\App\Http\Resources\\' . class_basename($model).'Resources';
    }
}
