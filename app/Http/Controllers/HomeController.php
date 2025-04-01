<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Enums\CacheName;
use Illuminate\Support\Str;
use App\Helpers\CacheManager;
use App\Traits\Base\BaseTrait;
use App\Enums\SortResourceType;
use App\Enums\FilterResourceType;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Services\Ussd\UssdService;
use App\Http\Resources\UserResource;
use App\Http\Resources\HomeResource;
use App\Repositories\UserRepository;
use App\Services\Money\MoneyService;
use App\Services\Filter\FilterService;
use App\Services\Sorting\SortingService;
use App\Services\Country\CountryService;
use App\Services\Language\LanguageService;
use App\Http\Controllers\Base\BaseController;
use App\Http\Requests\Home\ShowSortingRequest;
use App\Http\Requests\Home\ShowFiltersRequest;
use App\Http\Requests\Home\ShowApiHomeRequest;
use App\Http\Requests\Home\ShowCountriesRequest;
use App\Http\Requests\Home\ShowLanguagesRequest;
use App\Http\Requests\Home\ShowCurrenciesRequest;
use App\Http\Requests\Home\ConvertCurrencyRequest;
use App\Services\CountryAddress\CountryAddressService;
use App\Http\Requests\Home\ShowCountryAddressOptionsRequest;

class HomeController extends BaseController
{
    use BaseTrait;

    public function showApiHome(ShowApiHomeRequest $request): HomeResource
    {
        /**
         *  Since the api home endpostring does not require an authenticated user,
         *  i.e withoutMiddleware('auth:sanctum'), this means that the endpoint
         *  is open to everyone with or without the bearer token. This means
         *  that we never check the bearer token to block access on the
         *  condition of an invalid bearer token.
         *
         *  Note that the "auth_user" and "auth_user_exists" are set by our custom
         *  SetAuthUserOnRequest middleware class to retrieve the current
         *  authenticated user (if exists)
         */
        $authUser = $request->auth_user;
        $authUserExists = $request->auth_user_exists;
        $authUserDoesNotExist = $authUserExists == false;
        $hasProvidedMobileNumber = $request->filled('mobile_number');

        /**
         *  Performance Measure:
         *
         *  In order to reduce the number of API requests e.g sending one request to get this showApiHome()
         *  response and then another request to check if the user account exists if the authenticated
         *  user was not found, we can allow for an automatic check of account existence when the
         *  authentication fails, given that the mobile number is provided.
         */
        if($authUserDoesNotExist && $hasProvidedMobileNumber) {

            //  Get the mobile if provided (Normally provided on X-Platform = USSD)
            $mobileNumber = $request->input('mobile_number');

            //  Cache manager
            $accountExists = (new CacheManager(CacheName::ACCOUNT_EXISTS))->append($mobileNumber)->remember(now()->addMinutes(10), function() use ($mobileNumber) {

                /**
                 *  Check if the account using the provided mobile number exists in the database.
                 *
                 *  We could use User::searchMobileNumber($mobileNumber)->exists();
                 *
                 *  However, Query Builder was prefered for performance reasons.
                 */
                return DB::table('users')->where('mobile_number', $mobileNumber)->exists();

            });

        }else{
            $accountExists = $authUserExists;
        }

        $data = [
            'reserved_shortcode_range' => UssdService::getReservedShortcodeRange(),
            'user' => $authUserExists ? new UserResource($authUser) : null,
            'account_exists' => $accountExists,
            'authenticated' => $authUserExists,
        ];

        //  If the user exists and the response must include the user's resource totals
        if( $authUserExists && $this->isTruthy($request->filled('_include_resource_totals')) ) {

            $filter = collect(explode(',', $request->input('_include_resource_totals')))->map(fn($field) => Str::camel(trim($field)))->filter()->toArray();
            $data['resource_totals'] = (new UserRepository())->showUserResourceTotals($authUser, $filter);

            //  If the request has set the "_include_fields"
            if( $request->filled('_include_fields') ) {

                //  Add the resource totals to the request "_include_fields"
                request()->merge(['_include_fields' => $request->input('_include_fields') .',resource_totals']);

            }

        }

        return (new HomeResource($data));
    }

    /**
     * Show filters.
     *
     * @param ShowFiltersRequest $request
     * @return JsonResponse
     */
    public function showFilters(ShowFiltersRequest $request): JsonResponse
    {
        $type = FilterResourceType::tryFrom($request->input('type'));
        $store = $request->has('store_id') ? Store::find($request->input('store_id')) : null;

        $filterService = new FilterService;
        if(!empty($store)) $filterService->setStore($store);

        return $this->prepareOutput($filterService->getFiltersByResourceType($type));
    }

    /**
     * Show sorting.
     *
     * @param ShowSortingRequest $request
     * @return JsonResponse
     */
    public function showSorting(ShowSortingRequest $request): JsonResponse
    {
        $sortingService = new SortingService;
        $type = SortResourceType::tryFrom($request->input('type'));
        return $this->prepareOutput($sortingService->getSortingOptionsByResourceType($type));
    }

    /**
     * Show countries.
     *
     * @param ShowCountriesRequest $request
     * @param string|null $storeId
     * @return JsonResponse
     */
    public function showCountries(ShowCountriesRequest $request): JsonResponse
    {
        return $this->prepareOutput(CountryService::getCountries());
    }

    /**
     * Show currencies.
     *
     * @param ShowCurrenciesRequest $request
     * @return JsonResponse
     */
    public function showCurrencies(ShowCurrenciesRequest $request): JsonResponse
    {
        return $this->prepareOutput(MoneyService::getCurrencies());
    }

    /**
     * Convert currency.
     *
     * @param ConvertCurrencyRequest $request
     * @return JsonResponse
     */
    public function convertCurrency(ConvertCurrencyRequest $request): JsonResponse
    {
        return $this->prepareOutput(MoneyService::convertCurrency(
            $request->input('amount'),
            $request->input('from'),
            $request->input('to')
        ));
    }

    /**
     * Show languages.
     *
     * @param ShowLanguagesRequest $request
     * @return JsonResponse
     */
    public function showLanguages(ShowLanguagesRequest $request): JsonResponse
    {
        return $this->prepareOutput(LanguageService::getLanguages());
    }

    /**
     * Show country address options.
     *
     * @param ShowCountryAddressOptionsRequest $request
     * @param string|null $storeId
     * @return JsonResponse
     */
    public function showCountryAddressOptions(ShowCountryAddressOptionsRequest $request): JsonResponse
    {
        return $this->prepareOutput(CountryAddressService::getCountryAddressOptions());
    }

    /**
     * Show social media icons.
     *
     * @return JsonResponse
     */
    public function showSocialMediaIcons(): JsonResponse
    {
        $socialMediaPlatforms = [
            'Whatsapp', 'Telegram', 'Messenger', 'Facebook', 'Instagram',
            'LinkedIn', 'YouTube', 'Snapchat', 'TikTok', 'Twitch', 'X'
        ];

        $socialMediaLinks = collect($socialMediaPlatforms)->map(function($socialMediaPlatform) {
            return [
                'name' => $socialMediaPlatform,
                'icon' => asset("/images/social-icons/$socialMediaPlatform.png")
            ];
        })->toArray();

        return $this->prepareOutput($socialMediaLinks);
    }
}
