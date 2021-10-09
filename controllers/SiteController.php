<?php
namespace app\controllers;

use app\models\Application;
use app\models\Tour;
use Exception;
use TelegramBot\Api\BotApi;
use Yii;
use yii\db\Expression;
use yii\web\Controller;

class SiteController extends Controller
{
    const NEW_TOUR_COMMAND = '/newtour@fangazzettabot';
    const END_TOUR_COMMAND = '/endtour@fangazzettabot';
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

        if ($chat['type'] === 'group' && $chat['id'] === Yii::$app->params['group']) {
            if (array_key_exists('text', $message)) {
                switch ($message['text']) {
                    case self::NEW_TOUR_COMMAND:
                        $this->createTour($update);
                        break;
                    case self::END_TOUR_COMMAND:
                        $this->endTour($update);
                        break;
                }
            }
        } elseif ($chat['type'] === 'private') {
            if (array_key_exists('text', $message) && $message['text'] === self::START_COMMAND) {
                $this->start($update);
            } else {
                $this->createApplication($update);
            }
        }
    }

    private function createTour($update)
    {
        $tour = Tour::findOne(['active' => true]);
        if ($tour !== null) {
            $this->send('Уже идёт текущий тур. Если вы хотите остановить, отправьте ' . self::END_TOUR_COMMAND, $update['message']['chat']['id']);
            return;
        }

        $tour = new Tour([
            'active' => true,
        ]);
        $tour->save();
        $this->send('Запущен тур. Чтоб его остановить, отправьте ' . self::END_TOUR_COMMAND, $update['message']['chat']['id']);
    }

    private function endTour($update)
    {
        $tour = Tour::findOne(['active' => true]);
        if ($tour === null) {
            $this->send('Сейчас нет тура. Если вы хотите запустить, отправьте ' . self::NEW_TOUR_COMMAND, $update['message']['chat']['id']);
            return;
        }

        $tour->active = false;
        $tour->ended_at = new Expression('NOW()');
        $tour->save();
        $this->send('Тур заверщён.', $update['message']['chat']['id']);
    }

    private function start($update)
    {
        $tour = Tour::findOne(['active' => true]);
        if ($tour === null) {
            $this->send('В настоящее время приём заявок закрыт. Об открытии будет сообщено в канале @fangazzetta.', $update['message']['chat']['id']);
        } else {
            $this->send('Если вы хотите получить совет по заменам - просто отправьте ссылку на свою команду.', $update['message']['chat']['id']);
        }
    }

    private function createApplication($update)
    {
        $tour = Tour::findOne(['active' => true]);
        if ($tour === null) {
            $this->send('В настоящее время приём заявок закрыт. Об открытии будет сообщено в канале @fangazzetta.', $update['message']['chat']['id']);
            return;
        }

        $application = Application::findOne([
            'tour_id' => $tour->id,
            'user_id' => $update['message']['from']['id'],
        ]);
        if ($application !== null) {
            $this->send('Вы уже добавлены в список желающих.', $update['message']['chat']['id']);
            return;
        }

        $application = new Application([
            'tour_id' => $tour->id,
            'user_id' => $update['message']['from']['id'],
            'first_name' => $update['message']['from']['first_name'] ?? '',
            'last_name' => $update['message']['from']['last_name'] ?? '',
            'username' => $update['message']['from']['username'] ?? '',
            'selected' => false,
        ]);
        $application->save();
        $this->send('Ваша заявка принята. Вы добавлены в список желающих.', $update['message']['chat']['id']);

        $this->forward($update['message']['chat']['id'], $update['message']['message_id']);
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

    private function forward($chatId, $messageId)
    {
        $token = Yii::$app->params['token'];
        $bot = new BotApi($token);

        try {
            $bot->forwardMessage(Yii::$app->params['group'], $chatId, $messageId);
        } catch (Exception $e) {
            Yii::error('Forward error. Message: ' . $e->getMessage() . ' Code: ' . $e->getCode(), 'send');
        }
    }
}
