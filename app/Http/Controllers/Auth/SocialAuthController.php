<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use App\Repositories\AuthRepository;
use Laravel\Socialite\Facades\Socialite;
use App\Http\Controllers\Base\BaseController;

class SocialAuthController extends BaseController
{
    /**
     * Redirect the user to the Google authentication page.
     *
     * @return \Illuminate\Http\Response
     */
    public function redirectToGoogle()
    {
        return Socialite::driver('google')->redirect();
    }

    /**
     * Handle the Google callback.
     *
     * @return \Illuminate\Http\Response
     */
    public function handleGoogleCallback()
    {
        $params = [
            'provider' => 'google',
            'logo_url' => asset("/images/social-login-icons/google.png"),
        ];

        if (request()->has('error')) {

            $params = array_merge($params, [
                'error' => request()->get('error')
            ]);

            return redirect()->away(
                config('app.FRONTEND_SOCIAL_AUTH_REDIRECT_URI') . '?' . http_build_query($params)
            );

        }

        try {

            $googleUser = Socialite::driver('google')->user();

            $email = $googleUser->getEmail();
            $googleId = $googleUser->getId();
            $lastName = $googleUser->user['family_name'] ?? null;
            $firstName = $googleUser->user['given_name'] ?? 'Unknown';

            $user = User::firstOrCreate(
                !empty($email)
                    ? ['email' => $email]
                    : ['google_id' => $googleId],
                [
                    'email' => $email,
                    'google_id' => $googleId,
                    'last_name' => $lastName,
                    'first_name' => $firstName,
                    'email_verified_at' => !empty($email) ? now() : null,
                ]
            );

            if(!$user->google_id) {
                $user->update(['google_id' => $googleId]);
            }

            $response = (new AuthRepository())->socailLogin($user);

            $params = array_merge($params, [
                'token' => $response['access_token'],
            ]);

            return redirect()->away(
                config('app.FRONTEND_SOCIAL_AUTH_REDIRECT_URI') . '?' . http_build_query($params)
            );

        } catch (\GuzzleHttp\Exception\ClientException $e) {

            // Handle errors returned by Google
            $errorResponse = json_decode($e->getResponse()->getBody()->getContents(), true);

            $params = array_merge($params, [
                'error' => $errorResponse['error'] ?? 'unknown_error',
                'error_description' => $errorResponse['error_description'] ?? 'An unknown error occurred while communicating with Google.',
            ]);

            return redirect()->away(
                config('app.FRONTEND_SOCIAL_AUTH_REDIRECT_URI') . '?' . http_build_query($params)
            );

        } catch (\Exception $e) {

            // Handle unexpected errors
            $params = array_merge($params, [
                'error' => 'server_error',
                'error_description' => $e->getMessage()
            ]);

            return redirect()->away(
                config('app.FRONTEND_SOCIAL_AUTH_REDIRECT_URI') . '?' . http_build_query($params)
            );

        }
    }

    /**
     * Redirect the user to the Facebook authentication page.
     *
     * @return \Illuminate\Http\Response
     */
    public function redirectToFacebook()
    {
        return Socialite::driver('facebook')->redirect();
    }

    /**
     * Handle the Facebook callback.
     *
     * @return \Illuminate\Http\Response
     */
    public function handleFacebookCallback()
    {
        $params = [
            'provider' => 'facebook',
            'logo_url' => asset("/images/social-login-icons/facebook.png"),
        ];

        if (request()->has('error')) {

            $params = array_merge($params, [
                'error' => request()->get('error'),
                'error_reason' => request()->get('error_reason'),
                'error_description' => request()->get('error_description')
            ]);

            return redirect()->away(
                config('app.FRONTEND_SOCIAL_AUTH_REDIRECT_URI') . '?' . http_build_query($params)
            );

        }

        try {

            $facebookUser = Socialite::driver('facebook')->user();

            $name = $facebookUser->user['name'] ?? '';
            $nameParts = explode(' ', $name, 2);

            $lastName = $nameParts[1] ?? null;
            $email = $facebookUser->getEmail();
            $facebookId = $facebookUser->getId();
            $firstName = $nameParts[0] ?? 'Unknown';

            $user = User::firstOrCreate(
                !empty($email)
                    ? ['email' => $email]
                    : ['facebook_id' => $facebookId],
                [
                    'email' => $email,
                    'last_name' => $lastName,
                    'first_name' => $firstName,
                    'facebook_id' => $facebookId,
                    'email_verified_at' => !empty($email) ? now() : null,
                ]
            );

            if(!$user->facebook_id) {
                $user->update(['facebook_id' => $facebookId]);
            }

            $response = (new AuthRepository())->socailLogin($user);

            $params = array_merge($params, [
                'token' => $response['access_token'],
            ]);

            return redirect()->away(
                config('app.FRONTEND_SOCIAL_AUTH_REDIRECT_URI') . '?' . http_build_query($params)
            );

        } catch (\GuzzleHttp\Exception\ClientException $e) {

            // Handle errors returned by Facebook
            $errorResponse = json_decode($e->getResponse()->getBody()->getContents(), true);

            $params = array_merge($params, [
                'error' => $errorResponse['error'] ?? 'unknown_error',
                'error_description' => $errorResponse['error_description'] ?? 'An unknown error occurred while communicating with Facebook.',
            ]);

            return redirect()->away(
                config('app.FRONTEND_SOCIAL_AUTH_REDIRECT_URI') . '?' . http_build_query($params)
            );

        } catch (\Exception $e) {

            // Handle unexpected errors
            $params = array_merge($params, [
                'error' => 'server_error',
                'error_description' => $e->getMessage()
            ]);

            return redirect()->away(
                config('app.FRONTEND_SOCIAL_AUTH_REDIRECT_URI') . '?' . http_build_query($params)
            );

        }
    }

    /**
     * Redirect the user to the LinkedIn authentication page.
     *
     * @return \Illuminate\Http\Response
     */
    public function redirectToLinkedIn()
    {
        return Socialite::driver('linkedin-openid')->redirect();
    }

    /**
     * Handle the LinkedIn callback.
     *
     * @return \Illuminate\Http\Response
     */
    public function handleLinkedInCallback()
    {
        $params = [
            'provider' => 'linkedin',
            'logo_url' => asset("/images/social-login-icons/linkedin.png"),
        ];

        if (request()->has('error')) {

            $params = array_merge($params, [
                'error' => request()->get('error'),
                'error_description' => request()->get('error_description')
            ]);

            return redirect()->away(
                config('app.FRONTEND_SOCIAL_AUTH_REDIRECT_URI') . '?' . http_build_query($params)
            );

        }

        try{

            $linkedinUser = Socialite::driver('linkedin-openid')->user();

            $email = $linkedinUser->getEmail();
            $linkedinId = $linkedinUser->getId();
            $lastName = $linkedinUser->user['family_name'] ?? null;
            $firstName = $linkedinUser->user['given_name'] ?? 'Unknown';

            $user = User::firstOrCreate(
                !empty($email)
                    ? ['email' => $email]
                    : ['linkedin_id' => $linkedinId],
                [
                    'email' => $email,
                    'last_name' => $lastName,
                    'first_name' => $firstName,
                    'linkedin_id' => $linkedinId,
                    'email_verified_at' => !empty($email) ? now() : null,
                ]
            );

            if(!$user->linkedin_id) {
                $user->update(['linkedin_id' => $linkedinId]);
            }

            $response = (new AuthRepository())->socailLogin($user);

            $params = array_merge($params, [
                'token' => $response['access_token'],
            ]);

            return redirect()->away(
                config('app.FRONTEND_SOCIAL_AUTH_REDIRECT_URI') . '?' . http_build_query($params)
            );

        } catch (\GuzzleHttp\Exception\ClientException $e) {

            // Handle errors returned by Linkedin
            $errorResponse = json_decode($e->getResponse()->getBody()->getContents(), true);

            $params = array_merge($params, [
                'error' => $errorResponse['error'] ?? 'unknown_error',
                'error_description' => $errorResponse['error_description'] ?? 'An unknown error occurred while communicating with Linkedin.',
            ]);

            return redirect()->away(
                config('app.FRONTEND_SOCIAL_AUTH_REDIRECT_URI') . '?' . http_build_query($params)
            );

        } catch (\Exception $e) {

            // Handle unexpected errors
            $params = array_merge($params, [
                'error' => 'server_error',
                'error_description' => $e->getMessage()
            ]);

            return redirect()->away(
                config('app.FRONTEND_SOCIAL_AUTH_REDIRECT_URI') . '?' . http_build_query($params)
            );

        }
    }

}
