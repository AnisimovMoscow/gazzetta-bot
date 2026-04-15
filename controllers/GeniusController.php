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

    const LEAGUE = 37741;
    const SEASON = 72;

    public function actionHook()
    {
        $update = Yii::$app->request->post();
        Yii::info(print_r($update, true), 'send');

        if (!array_key_exists('message', $update)) {
            return;
        }

        $message = $update['message'];
        $chat = $message['chat'];
        $chatId = $chat['id'];

        if ($chat['type'] === 'private') {
            if (array_key_exists('text', $message) && $message['text'] === self::COMMAND_START) {
                $this->start($chatId);
            }

            if (array_key_exists('data', $update)) {
                if ($update['data'] === self::BUTTON_SELECT_USER) {
                    $this->selectUser($chatId);
                } elseif ($update['data'] === self::BUTTON_START) {
                    $this->start($chatId);
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
        Yii::info(print_r($squads, true), 'send');
        $chunks = array_chunk($squads, 2);
        $rows = [];
        foreach ($chunks as $chunk) {
            $row = [];
            foreach ($chunk as $squad) {
                $row[] = [
                    'text' => $squad->squad->user->nick,
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
