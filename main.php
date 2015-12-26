<?php

$loader = require __DIR__ . '/vendor/autoload.php';

use Longman\TelegramBot\Request;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

use Longman\TelegramBot\Telegram;
use Longman\TelegramBot\Exception\TelegramException;

use ZabbixApi\ZabbixApi;


$log = new Logger('drudeskbot');
$log->pushHandler(new StreamHandler('logs/app.log', Logger::DEBUG));

// Load settings.
$settings = require __DIR__ . '/settings.php';
//print $settings;
$COMMANDS_FOLDER = __DIR__ . '/Commands/';



$error_hosts = array();
try {
  // connect to Zabbix API
  $api = new ZabbixApi($ZABBIX_HOST . 'api_jsonrpc.php', $ZABBIX_USER, $ZABBIX_PASSWORD);
  $api->setDefaultParams(array(
    'output' => 'extend'
  ));
  $hosts = $api->hostGet();
  foreach ($hosts as $host) {
    if (0 == $host->status && $host->error) {
      $error_hosts[] = $host;
    }
  }
  
}
catch(Exception $e)
{
  // Exception in ZabbixApi catched
  echo $e->getMessage();
}


try {
  // create Telegram API object
  $telegram = new Telegram($API_KEY, $BOT_NAME);
  $telegram->enableMySQL($credentials);


  $telegram->addCommandsPath($COMMANDS_FOLDER);

  $telegram->setLogRequests(true);
  $telegram->setLogPath('logs/' . $BOT_NAME.'.log');
  $telegram->setLogVerbosity(3);


  if (!empty($error_hosts)) {
    foreach ($error_hosts as $host) {
      $results = Request::sendToActiveChats(
        'Error in host' . $host->name, //callback function to execute (see Request.php methods)
        array('text' => Request::getInput()), //Param to evaluate the request
        TRUE, //Send to chats (group chat)
        TRUE, //Send to users (single chat)
        NULL, //'yyyy-mm-dd hh:mm:ss' date range from
        NULL  //'yyyy-mm-dd hh:mm:ss' date range to
      );
    }
  }

//  print_r($telegram->getVersion());

  $ServerResponse = $telegram->handleGetUpdates();
  if ($ServerResponse->isOk()) {
    $n_update = count($ServerResponse->getResult());

    print(date('Y-m-d H:i:s', time()).' - Processed '.$n_update." updates\n");
  } else {
    print(date('Y-m-d H:i:s', time())." - Fail fetch updates\n");
    print $ServerResponse->printError()."\n";
  }

} catch (TelegramException $e) {
  // log telegram errors
  print $e->getMessage();
  $log->addError($e->getMessage());
}

