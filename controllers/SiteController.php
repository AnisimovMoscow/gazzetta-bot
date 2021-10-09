<?php
namespace app\controllers;

use app\models\Tour;
use Exception;
use TelegramBot\Api\BotApi;
use Yii;
use yii\db\Expression;
use yii\web\Controller;

class SiteController extends Controller
{
    public function actionHook()
    {
        $update = Yii::$app->request->post();
        Yii::info(print_r($update, true), 'send');

        if (!array_key_exists('message', $update)) {
            Yii::info('no message', 'send');
            return;
        }
        $message = $update['message'];
        $chat = $message['chat'];

        if ($chat['type'] === 'group' && $chat['id'] === Yii::$app->params['group']) {
            if (array_key_exists('text', $message)) {
                switch ($message['text']) {
                    case '/newtour':
                        $this->createTour($update);
                        break;
                    case '/endtour':
                        $this->endTour($update);
                        break;
                }
            } else {
                Yii::info('no text', 'send');
            }
        } else {
            Yii::info('not group', 'send');
        }
    }

    private function createTour($update)
    {
        $tour = Tour::findOne(['active' => true]);
        if ($tour !== null) {
            $this->send('Уже идёт текущий тур. Если вы хотите остановить, отправьте /endtour', $update['message']['chat']['id']);
            return;
        }

        $tour = new Tour([
            'active' => true,
        ]);
        $tour->save();
        $this->send('Запущен тур. Чтоб его остановить, отправьте /endtour', $update['message']['chat']['id']);
    }

    private function endTour($update)
    {
        $tour = Tour::findOne(['active' => true]);
        if ($tour === null) {
            $this->send('Сейчас нет тура. Если вы хотите запустить, отправьте /newtour', $update['message']['chat']['id']);
            return;
        }

        $tour->active = false;
        $tour->ended_at = new Expression('NOW()');
        $tour->save();
        $this->send('Тур заверщён.', $update['message']['chat']['id']);
    }

    private function send($text, $chatId)
    {
        $token = Yii::$app->params['token'];
        $bot = new BotApi($token);

        try {
            $bot->sendMessage($chatId, $text);
        } catch (Exception $e) {
            Yii::error('Send error. Message: ' . $e->getMessage() . ' Code: ' . $e->getCode(), 'send');
        }
    }
}
