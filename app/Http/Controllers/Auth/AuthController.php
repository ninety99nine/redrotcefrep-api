<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Http\JsonResponse;
use App\Repositories\AuthRepository;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Controllers\Base\BaseController;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\AccountExistsRequest;
use App\Http\Requests\Models\User\CreateUserRequest;
use App\Http\Requests\Auth\ValidateResetPasswordRequest;
use App\Http\Requests\Models\User\ValidateCreateUserRequest;
use App\Http\Requests\Auth\VerifyMobileVerificationCodeRequest;
use App\Http\Requests\Auth\GenerateMobileVerificationCodeRequest;

class AuthController extends BaseController
{
    protected AuthRepository $repository;

    /**
     * AuthController constructor.
     *
     * @param AuthRepository $repository
     */
    public function __construct(AuthRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Handle login request.
     *
     * @param LoginRequest $loginRequest
     * @return JsonResponse
     */
    public function login(LoginRequest $request): JsonResponse
    {
        return $this->prepareOutput($this->repository->login($request->all()));
    }

    /**
     * Handle user registration request.
     *
     * @param CreateUserRequest $createUserRequest
     * @return JsonResponse
     */
    public function register(CreateUserRequest $request): JsonResponse
    {
        return $this->prepareOutput($this->repository->register($request->all()));
    }

    /**
     * Check if an account exists.
     *
     * @param AccountExistsRequest $accountExistsRequest
     * @return JsonResponse
     */
    public function accountExists(AccountExistsRequest $request): JsonResponse
    {
        return $this->prepareOutput($this->repository->accountExists($request->all()));
    }

    /**
     * Handle reset password request.
     *
     * @param ResetPasswordRequest $resetPasswordRequest
     * @return JsonResponse
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        return $this->prepareOutput($this->repository->resetPassword($request->all()));
    }

    /**
     * Validate user registration data.
     *
     * @param ValidateCreateUserRequest $_
     * @return JsonResponse
     */
    public function validateRegistration(ValidateCreateUserRequest $_): JsonResponse
    {
        return $this->prepareOutput(null);
    }

    /**
     * Validate reset password request data.
     *
     * @param ValidateResetPasswordRequest $_
     * @return JsonResponse
     */
    public function validateResetPassword(ValidateResetPasswordRequest $_): JsonResponse
    {
        return $this->prepareOutput(null);
    }

    /**
     * Verify mobile verification code.
     *
     * @param VerifyMobileVerificationCodeRequest $verifyMobileVerificationCodeRequest
     * @return JsonResponse
     */
    public function verifyMobileVerificationCode(VerifyMobileVerificationCodeRequest $request): JsonResponse
    {
        return $this->prepareOutput($this->repository->verifyMobileVerificationCode($request->input('mobile_number'), $request->input('verification_code')));
    }

    /**
     * Generate mobile verification code.
     *
     * @param GenerateMobileVerificationCodeRequest $generateMobileVerificationCodeRequest
     * @return JsonResponse
     */
    public function generateMobileVerificationCode(GenerateMobileVerificationCodeRequest $request): JsonResponse
    {
        return $this->prepareOutput($this->repository->generateMobileVerificationCode($request->input('mobile_number')));
    }

    /**
     * Show terms and conditions.
     *
     * @return JsonResponse
     */
    public function showTermsAndConditions(): JsonResponse
    {
        return $this->prepareOutput($this->repository->showTermsAndConditions());
    }

    /**
     * Show terms and conditions takeaways.
     *
     * @return JsonResponse
     */
    public function showTermsAndConditionsTakeaways(): JsonResponse
    {
        return $this->prepareOutput($this->repository->showTermsAndConditionsTakeaways());
    }

    /**
     * Show social login links.
     *
     * @return JsonResponse
     */
    public function showSocialLoginLinks(): JsonResponse
    {
        return $this->prepareOutput($this->repository->showSocialLoginLinks());
    }

    /**
     * Show auth user.
     *
     * @return JsonResponse
     */
    public function showAuthUser(): JsonResponse
    {
        return $this->prepareOutput($this->repository->showAuthUser());
    }
}
