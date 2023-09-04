<?php

define('TOKEN_FILE', 'tmp' . DIRECTORY_SEPARATOR . 'token_info.json');

require_once(__DIR__ . '/vendor/autoload.php');
require_once(__DIR__ . '/keys.php');

$apiClient = new \AmoCRM\Client\AmoCRMApiClient($clientId, $clientSecret, $redirectUri);

$apiClient->setAccountBaseDomain('bastest.amocrm.ru');

$accessToken = $apiClient->getOAuthClient()->getAccessTokenByCode('def50200d712810efc900b10e357e3b93fbe7c027dad2f2e8eca72d9989471ea377cc82bb4cd8e3e3cfa34fdf33ae0364f29af3110ae77b79fce27e6069e53b1cbc1f5f2c5a6a3d220f0c827173712aa50e39f6d0fea7fde74d8a6c35120845b7b819cd74c3f79b80bc06d584ccd657a2edf4054121da918e4e48536d88eec696c63e5c929f1aee70a8200442d20c68a60a3d562b664ef5de4053a52ee0147c01db5faabb92a5bbdb35977e916cd78495e4c321746dfbba6949a2f7f701e0b3ceb54b01749df142a7bf4aaa72e97584109768c9b93e00f1b0fcb6ca57f389a9221214e5990b5d128b963fd6ddce44aca59fab7e77660961798caf65eb7b4652334c8075c5879e4f01afe4e2b66de706282e4119c6dee4a647145e848a0751c3cc02614db62419bffe1f18d2e16c8d3e04724d8235d5e042dc7ead47da6a3a50cc38e298fd12b3da7112f60cab14ed57aff07b2e5ae710cdeb4f3dd2efe5fdf08ed58108fa1feddaffe192c70611fd99a25c9a71fe525f191e275917ea446e3906ac4988b25cf9378d0a9d123ae72c545208a7161c1590e257d5f9fb1bdd9c2823a7521755e954dea9fc9b5dab5f0707be5c0ee7fcf27ac935bdc09334105ddf1416b05cfc696b191c5adb6e523a54f8a7f571837303fecf50609df99b02ff2b5839f36c08439936015beda82867092731d23d761c275482db251');

if (!$accessToken->hasExpired()) {
    saveToken([
        'accessToken' => $accessToken->getToken(),
        'refreshToken' => $accessToken->getRefreshToken(),
        'expires' => $accessToken->getExpires(),
        'baseDomain' => $apiClient->getAccountBaseDomain(),
    ]);
}

function saveToken($accessToken)
{
    if (
        isset($accessToken)
        && isset($accessToken['accessToken'])
        && isset($accessToken['refreshToken'])
        && isset($accessToken['expires'])
        && isset($accessToken['baseDomain'])
    ) {
        $data = [
            'accessToken' => $accessToken['accessToken'],
            'expires' => $accessToken['expires'],
            'refreshToken' => $accessToken['refreshToken'],
            'baseDomain' => $accessToken['baseDomain'],
        ];

        file_put_contents(TOKEN_FILE, json_encode($data));
    } else {
        exit('Invalid access token ' . var_export($accessToken, true));
    }
}