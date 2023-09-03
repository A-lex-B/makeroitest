<?php

use League\OAuth2\Client\Token\AccessToken;
use AmoCRM\Client\AmoCRMApiClient;
use League\OAuth2\Client\Token\AccessTokenInterface;
use AmoCRM\Models\CustomFieldsValues\ValueCollections\NullCustomFieldValueCollection;
use AmoCRM\Models\CustomFieldsValues\NumericCustomFieldValuesModel;
use AmoCRM\Models\CustomFieldsValues\ValueCollections\NumericCustomFieldValueCollection;
use AmoCRM\Models\CustomFieldsValues\ValueModels\NumericCustomFieldValueModel;

define('TOKEN_FILE', 'tmp' . DIRECTORY_SEPARATOR . 'token_info.json');

require_once(__DIR__ . '/vendor/autoload.php');
require_once(__DIR__ . '/keys.php');

//ID поля "Себестоимость"
$costPriceFieldID = 1331471;
//ID поля "Прибыль"
$incomeFieldID = 1331527;

//Получение массива параметров сделки из вебхука
if (isset($_POST) && isset($_POST['leads'])) {
    if (isset($_POST['leads']['add'])) {
        $leadFields = $_POST['leads']['add'][0];
    } elseif (isset($_POST['leads']['update'])) {
        $leadFields = $_POST['leads']['update'][0];
    }
} else exit;

//Получение значений полей
$price = $leadFields['price'] ?? null;
if (isset($leadFields['custom_fields'])) {
    foreach ($leadFields['custom_fields'] as $customField) {
        if (in_array($costPriceFieldID, $customField)) {
            $costPrice = $customField['values'][0]['value'];
        } elseif (in_array($incomeFieldID, $customField)) {
            $income = $customField['values'][0]['value'];
        }
    }
}

if (
    (isset($price) && isset($costPrice) && isset($income) && ($income == $price - $costPrice))
    || (!isset($income) && (empty($price) || !isset($costPrice)))
) {
    exit;
}

//Получение и обновление сделки
usleep(300000);

$apiClient = new AmoCRMApiClient($clientId, $clientSecret, $redirectUri);
$accessToken = getToken();
$apiClient->setAccessToken($accessToken)
    ->setAccountBaseDomain($accessToken->getValues()['baseDomain'])
    ->onAccessTokenRefresh(
        function (AccessTokenInterface $accessToken, string $baseDomain) {
            saveToken(
                [
                    'accessToken' => $accessToken->getToken(),
                    'refreshToken' => $accessToken->getRefreshToken(),
                    'expires' => $accessToken->getExpires(),
                    'baseDomain' => $baseDomain,
                ]
            );
        }
    );


$leadsService = $apiClient->leads();
$lead = $leadsService->getOne($leadFields['id']);

$customFields = $lead->getCustomFieldsValues();
$incomeField = $customFields->getBy('fieldId', $incomeFieldID);

if (isset($income) && (empty($price) || !isset($costPrice))) {
    $incomeField->setValues(new NullCustomFieldValueCollection());
} else {
    if (!$incomeField) {
        $incomeField = (new NumericCustomFieldValuesModel())->setFieldId($incomeFieldID);
        $incomeFieldValueCollection = new NumericCustomFieldValueCollection();
        $incomeFieldValueModel = new NumericCustomFieldValueModel();
        $incomeFieldValueCollection->add($incomeFieldValueModel);
        $incomeField->setValues($incomeFieldValueCollection);
        $customFields->add($incomeField);
    }
    $income = $price - $costPrice;
    if ($income < 0) {
        $income = 0;
    }
    $incomeField->getValues()->first()->setValue($income);
}

$leadsService->updateOne($lead);

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

function getToken()
{
    $accessToken = json_decode(file_get_contents(TOKEN_FILE), true);

    if (
        isset($accessToken)
        && isset($accessToken['accessToken'])
        && isset($accessToken['refreshToken'])
        && isset($accessToken['expires'])
        && isset($accessToken['baseDomain'])
    ) {
        return new AccessToken([
            'access_token' => $accessToken['accessToken'],
            'refresh_token' => $accessToken['refreshToken'],
            'expires' => $accessToken['expires'],
            'baseDomain' => $accessToken['baseDomain'],
        ]);
    } else {
        exit('Invalid access token ' . var_export($accessToken, true));
    }
}