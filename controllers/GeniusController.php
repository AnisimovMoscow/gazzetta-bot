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
                } elseif (preg_match('/squad_(\d+)_(\d+)/', $data, $matches)) {
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
        $players = Sports::getSquad($squadId, $tourId);
        $text = '';
        foreach (['GOALKEEPER', 'DEFENDER', 'MIDFIELDER', 'FORWARD'] as $role) {
            if ($player->seasonPlayer->role != $role) {
                continue;
            }

            foreach ($players as $player) {
                if (!$player->isStarting) {
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

    private function send($chatId, $text, $keyboard = null)
    {
        $token = Yii::$app->params['token-genius'];
        $bot = new BotApi($token);

        try {
            $bot->sendMessage($chatId, $text, null, false, null, $keyboard);
        } catch (Exception $e) {
            Yii::error('Send error. Message: ' . $e->getMessage() . ' Code: ' . $e->getCode(), 'send');
        }
    }
}
