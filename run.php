<?php

/*
 * This file is a part of the D.PHP project.
 *
 * Copyright (c) 2020-present David Cole <david.cole1340@gmail.com>
 *
 * This source file is subject to the GNU Affero General Public License v3.0 or later
 * that is bundled with this source code in the LICENSE.md file.
 */

ini_set('memory_limit', '-1');

/*
 * This file is a part of the D.PHP project.
 *
 * Copyright (c) 2020-present David Cole <david.cole1340@gmail.com>
 *
 * This source file is subject to the GNU Affero General Public License v3.0 or later
 * that is bundled with this source code in the LICENSE.md file.
 */

use Discord\Discord;
use Discord\Parts\Channel\Message;
use Discord\Parts\Embed\Embed;
use Discord\WebSockets\Intents;
use Dotenv\Dotenv;
use DPHP\Commands\Events;
use DPHP\Commands\Reflect;
use DPHP\Commands\Stats;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use React\EventLoop\Factory;
use React\Sh\Shell;
use React\Sh\StdioHandler;

include __DIR__ . '/vendor/autoload.php';

// load environment file
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();
$dotenv->required(['TOKEN', 'LOG_FILE']);
$dotenv->required('LOGGER_LEVEL')->allowedValues(array_keys(Logger::getLevels()));

$logger = new Logger('D.PHP');

$loop = Factory::create();

var_dump($_ENV['TOKEN']);

$discord = new Discord([
  'token' => $_ENV['TOKEN'],
  'loop' => $loop,
  'logger' => $logger,
  'loadAllMembers' => true,
  'intents' => Intents::getDefaultIntents() | Intents::GUILD_MEMBERS | Intents::GUILD_PRESENCES,
]);

$shell = new Shell($loop);

if (strtolower($_ENV['LOG_FILE']) == 'stdout') {
  $logger->pushHandler(new StdioHandler($shell->getStdio()));
} else {
  $logger->pushHandler(new StreamHandler($_ENV['LOG_FILE'], Logger::getLevels()[$_ENV['LOGGER_LEVEL']]));
}

/**
 * Generates help for the bot.
 *
 * @param  Discord $discord
 * @param  array   $commands
 * @return Embed
 */
function generateHelpCommand(Discord $discord, array $commands): Embed
{
  $embed = new Embed($discord);
  $embed->setTitle('DiscordPHP');

  foreach ($commands as $name => $command) {
    $embed->addFieldValues("@{$discord->username} " . $name, $command->getHelp());
  }

  return $embed;
}

$commands = [
  'reflect' => new Reflect($discord),
  'info' => new Stats($discord),
  'events' => new Events($discord),
];

$prefix = "!";

$discord->on('ready', function (Discord $discord) use ($commands, $shell, $prefix) {
  $discord->on('message', function (Message $message, Discord $discord) use ($commands, $prefix) {
    // check if message starts with mention
    var_dump(str_starts_with($message->content, $prefix));
    if (str_starts_with($message->content, $prefix)) {
      $args = explode(' ', $message->content);
      $command = array_shift($args);
      $command = str_replace($prefix, '', $command);

      var_dump($command);


      if (isset($commands[$command])) {
        $commands[$command]->handle($message, $args);
      } else {
        $embed = generateHelpCommand($discord, $commands);
        $message->channel->sendEmbed($embed);
      }
    }
  });

  $shell->setScope(get_defined_vars());
});

$loop->run();
