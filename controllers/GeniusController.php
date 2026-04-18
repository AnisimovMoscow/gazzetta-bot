<?php
namespace app\controllers;

use app\components\Sports;
use Exception;
use TelegramBot\Api\BotApi;
use TelegramBot\Api\Types\Inline\InlineKeyboardMarkup;
use Yii;
use yii\web\Controller;

class GeniusController extends Controller
{
    const COMMAND_START = '/start';

    const BUTTON_SELECT_USER = 'select_user';
    const BUTTON_START = 'start';
    const BUTTON_SQUAD = 'squad';
    const BUTTON_CAPTAINS = 'captains';
    const BUTTON_PLAYERS = 'players';

    const LEAGUE = 37741;
    const SEASON = 72;

    public function actionHook()
    {
        $update = Yii::$app->request->post();
        Yii::info(print_r($update, true), 'send');

        if (!array_key_exists('message', $update) && !array_key_exists('callback_query', $update)) {
            Yii::info('not message', 'send');
            return;
        }

        $message = $update['message'] ?? $update['callback_query']['message'];
        $chat = $message['chat'];
        $chatId = $chat['id'];

        if ($chat['type'] === 'private') {
            if (array_key_exists('text', $message) && $message['text'] === self::COMMAND_START) {
                $this->start($chatId);
            }

            if (array_key_exists('callback_query', $update) && array_key_exists('data', $update['callback_query'])) {
                $data = $update['callback_query']['data'];
                if ($data === self::BUTTON_SELECT_USER) {
                    $this->selectUser($chatId);
                } elseif ($data === self::BUTTON_START) {
                    $this->start($chatId);
                } elseif ($data === self::BUTTON_CAPTAINS) {
                    $this->viewCaptains($chatId);
                } elseif ($data === self::BUTTON_PLAYERS) {
                    $this->viewPlayers($chatId);
                } elseif (preg_match('/' . self::BUTTON_SQUAD . '_(\d+)_(\d+)/', $data, $matches)) {
                    $this->viewSquad($chatId, $matches[1], $matches[2]);
                }
            }
        }
    }

    private function start($chatId)
    {
        Yii::info('start', 'send');
        $keyboard = new InlineKeyboardMarkup([
            [
                [
                    'text' => 'Посмотреть состав',
                    'callback_data' => self::BUTTON_SELECT_USER,
                ],
                [
                    'text' => 'Капитаны',
                    'callback_data' => self::BUTTON_CAPTAINS,
                ],
            ],
            [
                [
                    'text' => 'Популярные игроки',
                    'callback_data' => self::BUTTON_PLAYERS,
                ],
            ],
        ]);
        $this->send($chatId, 'Выберите что хотите сделать:', $keyboard);
    }

    private function selectUser($chatId)
    {
        Yii::info('selectUser', 'send');
        $squads = Sports::getLeagueSquads(self::SEASON, self::LEAGUE);
        $chunks = array_chunk($squads, 2);
        $rows = [];
        foreach ($chunks as $chunk) {
            $row = [];
            foreach ($chunk as $squad) {
                $row[] = [
                    'text' => $squad->squad->user->nick,
                    'callback_data' => sprintf('%s_%s_%s', self::BUTTON_SQUAD, $squad->squad->id, $squad->squad->currentTourInfo->tour->id),
                ];
            }
            $rows[] = $row;
        }
        $rows[] = [
            [
                'text' => 'Назад',
                'callback_data' => self::BUTTON_START,
            ],
        ];
        $keyboard = new InlineKeyboardMarkup($rows);
        $this->send($chatId, 'Выберите команду:', $keyboard);
    }

    private function viewSquad($chatId, $squadId, $tourId)
    {
        Yii::info('viewSquad', 'send');
        $squad = Sports::getSquad($squadId, $tourId);
        $text = "{$squad->squad->user->nick}:\n\n";
        foreach (['GOALKEEPER', 'DEFENDER', 'MIDFIELDER', 'FORWARD'] as $role) {
            foreach ($squad->players as $player) {
                if (!$player->isStarting) {
                    continue;
                }
                if ($player->seasonPlayer->role != $role) {
                    continue;
                }

                $text .= $player->seasonPlayer->statObject->lastName . " ";

                if ($player->isCaptain) {
                    $text .= '(к) ';
                }

                $text .= ': ';
                $text .= ($player->score === null) ? '–' : $player->score;
                $text .= "\n";
            }

            $text .= "\n";
        }

        $keyboard = new InlineKeyboardMarkup([
            [
                [
                    'text' => 'Назад',
                    'callback_data' => self::BUTTON_SELECT_USER,
                ],
            ],
        ]);
        $this->send($chatId, $text, $keyboard);
    }

    private function viewCaptains($chatId)
    {
        Yii::info('viewCaptains', 'send');
        $text = "Капитаны:\n\n";
        $count = [];
        $squads = Sports::getLeagueSquads(self::SEASON, self::LEAGUE);
        foreach ($squads as $squad) {
            $text .= $squad->squad->user->nick . ': ';
            $info = Sports::getSquad($squad->squad->id, $squad->squad->currentTourInfo->tour->id);
            $cap = '';
            $viceCap = '';
            foreach ($info->players as $player) {
                if ($player->isCaptain) {
                    $cap = $player->seasonPlayer->statObject->lastName;
                }
                if ($player->isViceCaptain) {
                    $viceCap = $player->seasonPlayer->statObject->lastName;
                }
            }
            $text .= "{$cap} ({$viceCap})\n\n";

            if (!array_key_exists($cap, $count)) {
                $count[$cap] = [
                    'cap' => 1,
                    'viceCap' => 0, 
                ];
            } else {
                $count[$cap]['cap']++;
            }

            if (!array_key_exists($viceCap, $count)) {
                $count[$viceCap] = [
                    'cap' => 0,
                    'viceCap' => 1, 
                ];
            } else {
                $count[$viceCap]['viceCap']++;
            }
        }

        uasort ($count, function ($a, $b) {
            if ($a['cap'] > $b['cap']) {
                return -1;
            }

            if ($a['cap'] < $b['cap']) {
                return 1;
            }

            if ($a['viceCap'] > $b['viceCap']) {
                return -1;
            }

            if ($a['viceCap'] < $b['viceCap']) {
                return 1;
            }

            return 0;
        });

        foreach ($count as $name => $value) {
            $text .= "{$name}: {$value['cap']} ({$value['viceCap']})\n";
        }

        $keyboard = new InlineKeyboardMarkup([
            [
                [
                    'text' => 'Назад',
                    'callback_data' => self::BUTTON_START,
                ],
            ],
        ]);
        $this->send($chatId, $text, $keyboard);
    }

    private function viewPlayers($chatId)
    {
        Yii::info('viewPlayers', 'send');
        $text = "Популярные игроки:\n\n";
        $count = [];
        $squads = Sports::getLeagueSquads(self::SEASON, self::LEAGUE);
        foreach ($squads as $squad) {
            $info = Sports::getSquad($squad->squad->id, $squad->squad->currentTourInfo->tour->id);
            foreach ($info->players as $player) {
                if (!$player->isStarting) {
                    continue;
                }

                $name = $player->seasonPlayer->statObject->lastName;
                if (!array_key_exists($name, $count)) {
                    $count[$name] = 1;
                } else {
                    $count[$name]++;
                }
            }
        }

        uasort ($count, function ($a, $b) {
            if ($a > $b) {
                return -1;
            }

            if ($a < $b) {
                return 1;
            }

            return 0;
        });

        $count = array_slice($count, 0, 20, true);
        foreach ($count as $name => $value) {
            $text .= "{$name}: {$value}\n";
        }

        $keyboard = new InlineKeyboardMarkup([
            [
                [
                    'text' => 'Назад',
                    'callback_data' => self::BUTTON_START,
                ],
            ],
        ]);
        $this->send($chatId, $text, $keyboard);
    }

    private function send($chatId, $text, $keyboard = null)
    {
        $token = Yii::$app->params['token-genius'];
        $proxy = Yii::$app->params['proxy'];
        $bot = new BotApi($token);
        $bot->setProxy($proxy, true);

        try {
            $bot->sendMessage($chatId, $text, null, false, null, $keyboard);
        } catch (Exception $e) {
            Yii::error('Send error. Message: ' . $e->getMessage() . ' Code: ' . $e->getCode(), 'send');
        }
    }
}
