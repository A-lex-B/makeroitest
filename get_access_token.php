<?php

define('TOKEN_FILE', 'tmp' . DIRECTORY_SEPARATOR . 'token_info.json');

require_once(__DIR__ . '/vendor/autoload.php');
require_once(__DIR__ . '/keys.php');

$apiClient = new \AmoCRM\Client\AmoCRMApiClient($clientId, $clientSecret, $redirectUri);

$apiClient->setAccountBaseDomain('bastest.amocrm.ru');

$accessToken = $apiClient->getOAuthClient()->getAccessTokenByCode('def50200c3787f22bfde2bf2aeb245d836e74d523385793ac6c4b95b32821b2fe5c3ae75e18e8c7bd9c9bd6cf0e94058010bfff05b64e27c24206214f1e0101738de561f0ab4c253d5fe19d114621d8d050b0a4ee46487cf5fbd3b3015a55c2e55089cee09248c7c59c7cffdd98504253c14b875f83753730d6cdb0fb03d4fa68f9a5b2bad65a617009fa84a6d82aa399c7d4dfd9484e53e820f735a5bd784374afa315fbf88e0af720b6651b281dab6b792c099f91c7b53ec10b3e0c922820f05a6600761f4b47df8e5bc61ba5514bb6613d1dcaa6dc2804c5cec13835e4f27b15b51357e5debd9d7855ccc0f87c6d8dbfa6cbcc3baa58eb394476153637adbeaedeaa5ea7db4d6ba73fc92d3764c5756eaf2a1d718219dc63ebfb88f8073872197c409d284e81cab3ecda2636b1461ba0ee09bfad789e3dc2b1c9b4352a940bb363d94ac1902cee571bb2d2fe10046d4c72aa34bbc09c3e1294cc3633fdcd540ed165f51bb6bffd5e4867e3e685c18ea2606128b20e54dc780e13aed599e649e358d1248be88c0643baf56d8951633b01e071cfb378c323a06908a213b4fb1b94729d32bae6cded73d7dc1b867b0a9f31772d77eecca7227cfaedd76efbe95646bbae93a1c03e5dbfad9e5338cc562b3dea2f8ee8c64038300f6788de2');

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