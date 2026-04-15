<?php
namespace app\controllers;

use Exception;
use TelegramBot\Api\BotApi;
use TelegramBot\Api\Types\Inline\InlineKeyboardMarkup;
use Yii;
use yii\web\Controller;

class GeniusController extends Controller
{
    const START_COMMAND = '/start';

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
            if (array_key_exists('text', $message) && $message['text'] === self::START_COMMAND) {
                $this->start($chatId);
            }
        }
    }

    private function start($chatId)
    {
        $keyboard = new InlineKeyboardMarkup([
            [
                [
                    'text' => 'Посмотреть состав',
                    'callback_data' => 'select_user',
                ],
            ],
        ]);
        $this->send($chatId, 'Выберите вариант:', $keyboard);
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
