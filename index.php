<?php
use League\OAuth2\Client\Token\AccessToken;
use AmoCRM\Client\AmoCRMApiClient;
use League\OAuth2\Client\Token\AccessTokenInterface;
use AmoCRM\Filters\EventsFilter;
use AmoCRM\Filters\LeadsFilter;
use AmoCRM\Models\CustomFieldsValues\NumericCustomFieldValuesModel;
use \AmoCRM\Models\CustomFieldsValues\ValueCollections\NumericCustomFieldValueCollection;
use \AmoCRM\Models\CustomFieldsValues\ValueModels\NumericCustomFieldValueModel;
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

$clientId = '2c582496-c102-496d-97f2-327b6fa1eea0';
$clientSecret = 'WAjAy8JYoMuZ0xvmp5fDaSemHh61yhczSRrescDGaOFjzRwzLvBMhUCnzoENIaxS';
$redirectUri = 'https://ya.ru';

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

//Массив ID отслеживаемых дополнительных полей сделок
$customFieldsIDs = ['1331471'];

//Получение событий
$eventService = $apiClient->events();
$eventsFilter = new EventsFilter();
$eventsFilter->setTypes(['lead_added', 'sale_field_changed', 'custom_field_value_changed'])
    ->setCreatedAt($lastExucutionTime);

try {
    $events = $eventService->get($eventsFilter);
} catch(AmoCRMApiNoContentException) {
    echo 'Нет измененных полей или новых сделок';
    exit;
} catch (AmoCRMApiException $e) {
    printError($e);
    die;
}

try {
    $leadsToCount = [];
    while (true) {
        $eventsArray = $events->toArray();
        foreach ($eventsArray as $event) {
            if (
                !preg_match('/custom_field_(\d+)_value_changed/', $event['type'], $matches) ||
                in_array($matches[1], $customFieldsIDs)
            ) {
                if (!in_array($event['entity_id'], $leadsToCount)) {
                    $leadsToCount[] = $event['entity_id'];
                }
            }
        }
        usleep(200000);
        $events = $eventService->nextPage($events);
    }
} catch(AmoCRMApiPageNotAvailableException | AmoCRMApiNoContentException) {
} catch (AmoCRMApiException $e) {
    printError($e);
    die;
}

//Получение и редактирование сделок
$leadsService = $apiClient->leads();
$leadsFilter = new LeadsFilter();
$leadsFilter->setIds($leadsToCount);
$leads = $leadsService->get($leadsFilter);

try {
    while(true) {
        foreach ($leads as $lead) {
            $costPriceValue = null;
            $price = $lead->getPrice();
            $customFields = $lead->getCustomFieldsValues();
            if (!empty($customFields)) {
                $costPrice = $customFields->getBy('fieldId', 1331471);
                if ($costPrice) {
                    $costPriceValue = $costPrice->getValues()->first()->getValue();
                }
            }
            if (empty($price) || empty($costPriceValue))
                continue;

            $incom = $customFields->getBy('fieldId', 1331527);
            if (!$incom) {
                $incom = (new NumericCustomFieldValuesModel())->setFieldId(1331527);
                $incomValueCollection = new NumericCustomFieldValueCollection();
                $incomValueModel = new NumericCustomFieldValueModel();
                $incomValueCollection->add($incomValueModel);
                $incom->setValues($incomValueCollection);
                $customFields->add($incom);
            }
            
            $incomValue = $price - $costPriceValue;
            if ($incomValue < 0) {
                $incomValue = 0;
            }
            $incom->getValues()->first()->setValue($incomValue);
        }

        $leadsService->update($leads);

        usleep(300000);
        $leads = $leadsService->nextPage($leads);
    }
} catch (AmoCRMApiPageNotAvailableException | AmoCRMApiNoContentException) {
} catch (AmoCRMApiException $e) {
    printError($e);
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