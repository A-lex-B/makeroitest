<?php

use League\OAuth2\Client\Token\AccessToken;
use AmoCRM\Client\AmoCRMApiClient;
use League\OAuth2\Client\Token\AccessTokenInterface;
use AmoCRM\Filters\EventsFilter;
use AmoCRM\Filters\LeadsFilter;
use AmoCRM\Models\CustomFieldsValues\ValueCollections\NullCustomFieldValueCollection;
use AmoCRM\Models\CustomFieldsValues\NumericCustomFieldValuesModel;
use AmoCRM\Models\CustomFieldsValues\ValueCollections\NumericCustomFieldValueCollection;
use AmoCRM\Models\CustomFieldsValues\ValueModels\NumericCustomFieldValueModel;
use AmoCRM\Exceptions\AmoCRMApiException;
use AmoCRM\Exceptions\AmoCRMApiPageNotAvailableException;
use AmoCRM\Exceptions\AmoCRMApiNoContentException;

if (file_exists('lastExecutionTime.txt')) {
    $lastExucutionTime = file_get_contents('lastExecutionTime.txt');
}
file_put_contents('lastExecutionTime.txt', time());
if (empty($lastExucutionTime)) {
    $lastExucutionTime = time() - 3600 * 24 * 3;
}

define('TOKEN_FILE', 'tmp' . DIRECTORY_SEPARATOR . 'token_info.json');

require_once(__DIR__ . '/vendor/autoload.php');
require_once(__DIR__ . '/keys.php');

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

//ID поля "Себестоимость"
$costPriceFieldID = 1331471;
//ID поля "Прибыль"
$incomeFieldID = 1331527;

//Получение событий
$eventService = $apiClient->events();
$eventsFilter = new EventsFilter();
$eventsFilter->setTypes(['lead_added', 'sale_field_changed', 'custom_field_value_changed'])
    ->setCreatedAt($lastExucutionTime);

try {
    $events = $eventService->get($eventsFilter);
} catch (AmoCRMApiNoContentException) {
    echo 'Нет измененных полей или новых сделок';
    exit;
} catch (AmoCRMApiException $e) {
    errorInfo($e);
    die;
}

try {
    $leadsToCount = [];
    while (true) {
        $eventsArray = $events->toArray();
        foreach ($eventsArray as $event) {
            if (
                !preg_match('/custom_field_(\d+)_value_changed/', $event['type'], $matches) ||
                $matches[1] == $costPriceFieldID
            ) {
                if (!in_array($event['entity_id'], $leadsToCount)) {
                    $leadsToCount[] = $event['entity_id'];
                }
            }
        }
        usleep(200000);
        $events = $eventService->nextPage($events);
    }
} catch (AmoCRMApiPageNotAvailableException | AmoCRMApiNoContentException) {
} catch (AmoCRMApiException $e) {
    errorInfo($e);
    die;
}
if(!$leadsToCount) {
    echo 'Нет измененных полей или новых сделок';
    exit;
}

//Получение и редактирование сделок
$leadsService = $apiClient->leads();
$leadsFilter = new LeadsFilter();
$leadsFilter->setIds($leadsToCount);

try {
    $leads = $leadsService->get($leadsFilter);
    while(true) {
        foreach ($leads as $lead) {
            $costPrice = null;
            $income = null;
            $price = $lead->getPrice();
            $customFields = $lead->getCustomFieldsValues();
            if (!empty($customFields)) {
                $costPriceField = $customFields->getBy('fieldId', $costPriceFieldID);
                if ($costPriceField) {
                    $costPrice = $costPriceField->getValues()->first()->getValue();
                }
                $incomeField = $customFields->getBy('fieldId', $incomeFieldID);
                if ($incomeField) {
                    $income = $incomeField->getValues()->first()->getValue();
                }
            }

            if (
                (isset($price) && isset($costPrice) && isset($income) && ($income == $price - $costPrice))
                || (!isset($income) && (empty($price) || !isset($costPrice)))
            ) {
                $leads->removeBy('id', $lead->getId());
                continue;
            } elseif (isset($income) && (empty($price) || !isset($costPrice))) {
                $incomeField->setValues(new NullCustomFieldValueCollection());
                continue;
            }

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

        if (!$leads->isEmpty()) {
            $leadsService->update($leads);
        }
        
        usleep(300000);
        $leads = $leadsService->nextPage($leads);
    }
} catch (AmoCRMApiPageNotAvailableException | AmoCRMApiNoContentException) {
} catch (AmoCRMApiException $e) {
    errorInfo($e);
    die;
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

function errorInfo(AmoCRMApiException $e) {
    $errorTitle = $e->getTitle();
    $code = $e->getCode();
    $debugInfo = var_export($e->getLastRequestInfo(), true);
        
    $error = <<<EOF
    Error: $errorTitle
    Code: $code
    Debug: $debugInfo
    EOF;
    
    echo '<pre>' . $error . '</pre>';
}