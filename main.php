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



$errors = array();
try {
  // Connect to Zabbix API.
  $api = new ZabbixApi($ZABBIX_HOST . 'api_jsonrpc.php', $ZABBIX_USER, $ZABBIX_PASSWORD);
  $api->setDefaultParams(array(
    'output' => 'extend'
  ));
  $triggers = $api->triggerGet(array('filter' => array('value' => 1)));
  foreach ($triggers as $trigger) {
    $errors[] = $trigger->description;
  }
}
catch(Exception $e)
{
  // Exception in ZabbixApi catched.
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


  if (!empty($errors)) {

    $results = Request::sendToActiveChats(
      'sendMessage', //callback function to execute (see Request.php methods)
      array('text'=>"[Zabbix]\nWe have a problem\n" . implode(', ', $errors)), //Param to evaluate the request
      false, //Send to chats (group chat)
      true, //Send to users (single chat)
      null, //'yyyy-mm-dd hh:mm:ss' date range from
      null  //'yyyy-mm-dd hh:mm:ss' date range to
    );
  }


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

