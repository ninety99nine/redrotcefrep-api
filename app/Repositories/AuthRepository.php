<?php

namespace App\Repositories;

use Carbon\Carbon;
use App\Models\User;
use App\Jobs\SendSms;
use App\Traits\AuthTrait;
use App\Events\LoginSuccess;
use App\Traits\Base\BaseTrait;
use App\Enums\ReturnAccessToken;
use App\Helpers\RequestAuthUser;
use App\Models\MobileVerification;
use App\Services\Ussd\UssdService;
use Illuminate\Support\Facades\Hash;
use App\Http\Resources\UserResource;
use Illuminate\Validation\ValidationException;
use App\Exceptions\ResetPasswordFailedException;
use App\Exceptions\UpdatePasswordFailedException;
use App\Services\CodeGenerator\CodeGeneratorService;

/**
 * Class AuthRepository
 *
 * Handles authentication-related tasks such as login, registration, and password reset.
 */
class AuthRepository extends BaseRepository
{
    use AuthTrait, BaseTrait;

    protected string|null $resourceName = 'User';
    protected string|null $modelClassName = 'App\Models\User';
    protected string|null $resourceClassName = 'App\Http\Resources\UserResource';
    protected string|null $resourceCollectionClassName = 'App\Http\Resources\User\Resources';

    /**
     * Login.
     *
     * @param array $data
     * @return array
     */
    public function login(array $data): array
    {
        $user = $this->getUserByMobileNumber($data['mobile_number']);

        if (UssdService::verifyIfRequestFromUssdServer()) {
            return $this->getUserAndAccessToken($user);
        }

        if ($user->password) {
            if (Hash::check($data['password'], $user->password)) {
                $userAndAccessToken = $this->getUserAndAccessToken($user);
                broadcast(new LoginSuccess($userAndAccessToken['access_token']))->toOthers();
                return $userAndAccessToken;
            }
            throw ValidationException::withMessages(['password' => 'The password provided is incorrect.']);
        }

        $this->updateAccountPassword($user, $data['password']);
        return $this->getUserAndAccessToken($user);
    }

    /**
     * Social login.
     *
     * @param User $user
     * @return array
     */
    public function socailLogin(User $user): array
    {
        return $this->getUserAndAccessToken($user);
    }

    /**
     * Register.
     *
     * @param array $data
     * @param ReturnAccessToken $returnAccessToken
     * @return UserResource|array
     */
    public function register(array $data, ReturnAccessToken $returnAccessToken = ReturnAccessToken::YES): UserResource|array
    {
        if ($this->hasAuthUser()) $data['registered_by_user_id'] = $this->getAuthUser()->id;
        if (isset($data['password'])) $data['password'] = $this->getEncryptedRequestPassword($data['password']);

        $createdUser = User::create($data);
        $this->revokeUserMobileVerificationCode($createdUser);

        if ($returnAccessToken == ReturnAccessToken::YES) {
            $result = $this->getUserAndAccessToken($createdUser);
        } else {
            $result = new UserResource($createdUser);
        }

        return $result;
    }

    /**
     * Check account exists.
     *
     * @param array $data
     * @return array
     */
    public function accountExists(array $data): array
    {
        $user = $this->getUserByMobileNumber($data['mobile_number']);

        return [
            'exists' => !is_null($user),
            'account_summary' => $user ? collect($user)->only(['mobile_number', 'requires_password']) : null
        ];
    }

    /**
     * Reset password.
     *
     * @param array $data
     * @return array
     */
    public function resetPassword(array $data): array
    {
        $user = $this->getUserByMobileNumber($data['mobile_number']);

        try {
            $this->updateAccountPassword($user, $data['password']);
            return $this->getUserAndAccessToken($user);
        } catch (UpdatePasswordFailedException $e) {
            throw new ResetPasswordFailedException();
        }
    }

    /**
     * Show terms and conditions.
     *
     * @return array
     */
    public function showTermsAndConditions(): array
    {
        return [
            'title' => 'Terms & Conditions',
            'instruction' => 'Accept the following terms of service to continue using Perfect Order services',
            'confirmation' => 'I agree and accept these terms of service by proceeding to use this service.',
            'button' => 'Accept',
            'items' => [
                [
                    'name' => 'Accept Terms Of Service',
                    'href' => route('show.terms.and.conditions')
                ]
            ]
        ];
    }

    /**
     * Show terms and conditions takeaways.
     *
     * @return array
     */
    public function showTermsAndConditionsTakeaways(): array
    {

        return [
            'buyer' => [
                'title' => 'Buyer Takeaways',
                'instruction' => 'As a buyer on '.config('app.name').', here are the key terms you must accept:',
                'takeaways' => 'As a buyer on '.config('app.name').', you must create an account with accurate details and keep your login information secure. Before purchasing, carefully review product descriptions, images, and store details, and ensure you buy from trusted sellers. It is crucial to provide a correct delivery address and contact information, as '.config('app.name').' is not responsible for undelivered or misrepresented orders. Please note that '.config('app.name').' does not offer refunds. Payments include the agreed price and any applicable fees. If issues arise, communicate with sellers respectfully, and they are expected to resolve matters promptly. You can leave honest reviews to help future buyers. Your data is protected, and we comply with applicable privacy laws. For disputes, you should first try to resolve them directly with the seller, but '.config('app.name').' can mediate if necessary. Additionally, always adhere to our platform\'s code of conduct, which emphasizes respectful and lawful behavior. For the full terms and conditions, please visit our website at '.route('show.terms.and.conditions')
            ],
            'seller' => [
                'title' => 'Seller Takeaways',
                'instruction' => 'As a seller on '.config('app.name').', here are the key terms you must accept:',
                'takeaways' => 'As a seller on '.config('app.name').', you need to register with accurate and authorized business details. Ensure your product listings are accurate, up-to-date, and comply with relevant laws. Update your product availability regularly to avoid disappointing customers. Fulfill orders promptly and ensure the quality of your products before delivery. Set transparent and fair prices, and note that '.config('app.name').' may deduct fees—review the fee structure on our platform. Respond to customer inquiries promptly and professionally. You are responsible for safeguarding customer data and using it solely for order fulfillment or communication via '.config('app.name').'. Improper use of data can lead to penalties. Customer feedback through reviews and ratings is essential for improving your business. In case of disputes, try to resolve them directly with the buyer, but '.config('app.name').' can mediate if necessary. Unethical behavior or violation of the terms may lead to the suspension or termination of your account. Additionally, the shortcodes provided by '.config('app.name').' remain our property and may be reassigned if your subscription ends. For the full terms and conditions, please visit our website at '.route('show.terms.and.conditions')
            ]
        ];
    }

    /**
     * Show social login links.
     *
     * @return User|array|null
     */
    public function showSocialLoginLinks(): array
    {
        $platforms = ['google', 'facebook', 'linkedin'];

        return collect($platforms)->map(function($platform) {

            $platformCapitalized = ucfirst($platform);
            $label = 'Continue with '.$platformCapitalized;

            return [
                'platform' => $platformCapitalized,
                'label' => $label,
                'url' => route("social-auth-$platform"),
                'logo_url' => asset("/images/social-login-icons/$platform.png")
            ];
        })->toArray();
    }

    /**
     * Show auth user.
     *
     * @return User|array|null
     */
    public function showAuthUser(): User|array|null
    {
        return $this->showResourceExistence(request()->auth_user);
    }

    /**
     * Generate mobile verification code.
     *
     * @param string $mobileNumber
     * @return array
     */
    public function generateMobileVerificationCode(string $mobileNumber): array
    {
        $code = CodeGeneratorService::generateRandomSixDigitNumber();

        $successful = MobileVerification::updateOrCreate(
            ['mobile_number' => $mobileNumber],
            ['mobile_number' => $mobileNumber, 'code' => $code]
        );

        if ($successful) {
            return [
                'message' => 'Mobile verification code generated',
                'generated' => true,
                'code' => $code
            ];
        }else{
            return [
                'message' => 'The mobile verification code could not be generated',
                'generated' => false,
            ];
        }
    }

    /**
     * Verify mobile verification code.
     *
     * @param string $mobileNumber
     * @param string $code
     * @return array
     */
    public function verifyMobileVerificationCode(string $mobileNumber, string $code): array
    {
        $isValid = MobileVerification::where('mobile_number', $mobileNumber)->where('code', $code)->exists();

        return ['is_valid' => $isValid];
    }

    /**
     * Revoke user mobile verification code.
     *
     * @param User $user
     * @return void
     */
    public static function revokeUserMobileVerificationCode(User $user): void
    {
        self::revokeMobileVerificationCode($user->mobile_number->formatE164());
    }

    /**
     * Revoke mobile verification code.
     *
     * @param string $mobileNumber
     * @return void
     */
    public static function revokeMobileVerificationCode(string $mobileNumber): void
    {
        MobileVerification::where('mobile_number', $mobileNumber)->update(['code' => null]);
    }

    /**
     * Logout authenticated user.
     *
     * @param User $user
     * @param array $data
     * @return array
     */
    public function logout(User $user, array $data = []): array
    {
        $superAdminIsLoggingOutSomeoneElse = ($this->getAuthUser()->isSuperAdmin() && $this->getAuthUser()->id != $user->id);
        $logoutAllDevices = isset($data['everyone']) && $this->isTruthy($data['everyone']);
        $logoutOtherDevices = isset($data['others']) && $this->isTruthy($data['others']);

        if ($logoutAllDevices || $superAdminIsLoggingOutSomeoneElse) {
            if ($superAdminIsLoggingOutSomeoneElse && $user->isSuperAdmin()) {
                return ['logout' => false, 'message' => 'You do not have permissions to logout this Super Admin'];
            }

            $this->revokeAllAccessTokens($user);
        } elseif ($logoutOtherDevices) {
            $this->revokeAllAccessTokensExceptTheCurrentAccessToken($user);
        } else {
            $this->revokeCurrentAccessToken($user);
        }

        return [
            'message' => 'Signed out successfully',
            'logout' => true
        ];
    }

    /**
     * Update account password.
     *
     * @param User $user
     * @param string $password
     * @return void
     */
    private function updateAccountPassword(User $user, string $password): void
    {
        $data = [
            'password' => $this->getEncryptedRequestPassword($password),
            'mobile_number_verified_at' => Carbon::now()
        ];

        if (!$user->update($data)) {
            throw new UpdatePasswordFailedException();
        }

        $this->revokeUserMobileVerificationCode($user);
    }

    /**
     * Get encrypted request password.
     *
     * @param string $password
     * @return string
     */
    public static function getEncryptedRequestPassword(string $password): string
    {
        return bcrypt($password);
    }

    /**
     * Show user tokens.
     *
     * @param User $user
     * @return array
     */
    public function showTokens(User $user): array
    {
        $tokens = $user->tokens->map(function ($token) {
            return $token->only(['name', 'last_used_at', 'created_at']);
        })->toArray();

        return ['tokens' => $tokens];
    }

    /**
     * Get user and access token.
     *
     * @param User $user
     * @return array
     */
    private function getUserAndAccessToken(User $user): array
    {
        $newAccessTokenInstance = UssdService::verifyIfRequestFromUssdServer()
            ? $this->createAccessToken($user)
            : $this->createAccessTokenWhileRevokingPreviousAccessTokens($user);

        $plainTextToken = $newAccessTokenInstance->plainTextToken;
        $accessToken = $newAccessTokenInstance->accessToken;

        request()->headers->set('Authorization', 'Bearer ' . $plainTextToken);
        (new RequestAuthUser($user))->setAuthUserOnCache($plainTextToken, $accessToken)->setAuthUserOnRequest();

        return [
            'access_token' => $plainTextToken,
            'message' => 'Signed in successfully',
            'user' => new UserResource($user),
        ];
    }

    /**
     * Get user by mobile number.
     *
     * @param string $mobileNumber
     * @return User|null
     */
    private function getUserByMobileNumber(string $mobileNumber)
    {
        return User::searchMobileNumber($mobileNumber)->first();
    }

    /**
     * Create access token while revoking previous access tokens.
     *
     * @param User $user
     * @return \Laravel\Sanctum\NewAccessToken
     */
    private function createAccessTokenWhileRevokingPreviousAccessTokens(User $user)
    {
        $this->revokeAllAccessTokens($user);
        return $this->createAccessToken($user);
    }

    /**
     * Revoke all tokens.
     *
     * @param User $user
     * @return void
     */
    private function revokeAllAccessTokens(User $user): void
    {
        $this->removeTokensFromDatabaseAndCache($user->tokens());
    }

    /**
     * Revoke current access token.
     *
     * @param User $user
     * @return void
     */
    private function revokeCurrentAccessToken(User $user): void
    {
        $this->removeTokensFromDatabaseAndCache($user->currentAccessToken());
    }

    /**
     * Revoke all tokens except the current access token.
     *
     * @param User $user
     * @return void
     */
    private function revokeAllAccessTokensExceptTheCurrentAccessToken(User $user): void
    {
        $this->removeTokensFromDatabaseAndCache(
            $user->tokens()->where('id', '!=', $user->currentAccessToken()->id)
        );
    }

    /**
     * Remove tokens from database and cache.
     *
     * @param mixed $tokenInstance
     * @return void
     */
    private function removeTokensFromDatabaseAndCache($tokenInstance): void
    {
        foreach ($tokenInstance->get() as $accessToken) {
            (new RequestAuthUser())->forgetAuthUserOnCacheUsingAccessToken($accessToken);
        }

        $tokenInstance->delete();
    }

    /**
     * Create a new personal access token.
     *
     * @param User $user
     * @return \Laravel\Sanctum\NewAccessToken
     */
    private function createAccessToken(User $user)
    {
        $tokenName = $user->name;
        return $user->createToken($tokenName);
    }
}
