<?php

namespace App\Helpers;

use App\Models\User;
use GuzzleHttp\HandlerStack;
use HubSpot\Client\Crm\Contacts\ApiException;
use HubSpot\Delay;
use HubSpot\Http\Auth;
use HubSpot\RetryMiddlewareFactory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Helper
{

    /**
     * Create a HubSpot factory with the given user
     * @param User $user
     * @return \HubSpot\Discovery\Discovery
     * @throws \Exception
     */
    public static function createHubspotFactory(?User $user): \HubSpot\Discovery\Discovery
    {
        $handlerStack = HandlerStack::create();
        $handlerStack->push(
            RetryMiddlewareFactory::createRateLimitMiddleware(
                Delay::getExponentialDelayFunction(2),
                10
            )
        );
        $handlerStack->push(
            RetryMiddlewareFactory::createInternalErrorsMiddleware(
                Delay::getExponentialDelayFunction(4),
                10
            )
        );

        $client = new \GuzzleHttp\Client(['handler' => $handlerStack]);

        $auth_token = User::find($user->id)->hs_auth_token;

        if(str_contains($auth_token, 'api_key:')) {
            $auth_token = str_replace('api_key:', '', $auth_token);
            Log::debug('Using API key to create HubSpot factory');
            return \HubSpot\Factory::createWithAccessToken($auth_token, $client);
        }

        $tokenDataResponse = Http::get('https://api.hubapi.com/oauth/v1/access-tokens/' . $auth_token);

        $tokenData = $tokenDataResponse->json();

        if($tokenData == null) {
            throw new \Exception('Could not get token data from HubSpot');
        }

        if(!isset($tokenData['expires_in']) || $tokenData['expires_in'] < 60) {
            $newAuthTokenResponse = Http::asForm()->post('https://api.hubapi.com/oauth/v1/token', [
                'grant_type' => 'refresh_token',
                'refresh_token' => $user->hs_refresh_token,
                'redirect_uri' => env("HUBSPOT_REDIRECT_URI"),
                'client_id' => env("HUBSPOT_CLIENT_ID"),
                'client_secret' => env("HUBSPOT_CLIENT_SECRET")
            ]);

            $newAuthToken = $newAuthTokenResponse->json();

            if($newAuthToken == null) {
                throw new \Exception('Could not get new auth token from HubSpot');
            }

            $tokenData['access_token'] = $newAuthToken['access_token'];

            //same as above result but as model
            $user->hs_auth_token = $newAuthToken['access_token'];
            $user->save();

            $auth_token = $tokenData['access_token'];
        }

        return \HubSpot\Factory::createWithAccessToken($auth_token, $client);
    }

    public static function getAuthToken(string $code) {

        $response = Http::asForm()->post('https://api.hubapi.com/oauth/v1/token', [
            'grant_type' => 'authorization_code',
            'client_id' => env('HUBSPOT_CLIENT_ID'),
            'client_secret' => env('HUBSPOT_CLIENT_SECRET'),
            'redirect_uri' => env('HUBSPOT_REDIRECT_URI'),
            'code' => $code
        ]);

        if($response->status() !== 200) {
            Log::error('Could not get auth token from HubSpot', [
                'response' => $response->json(),
                'client_id' => env('HUBSPOT_CLIENT_ID'),
                'client_secret' => env('HUBSPOT_CLIENT_SECRET'),
                'redirect_uri' => env('HUBSPOT_REDIRECT_URI'),
                'code' => $code
            ]);
        }

        return $response->json();
    }

    /**
     * Get the HubSpot portal ID of the user
     * @param User $user
     * @return int
     * @throws ApiException
     */
    public static function getHubspotPortalId(?User $user) {
        // https://api.hubapi.com/integrations/v1/me

        $result = Http::withToken($user->hs_auth_token)->get('https://api.hubapi.com/integrations/v1/me');

        $portalId = $result->json()['portalId'];

        return $portalId;
    }
}
