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
    const APPLICATIONS_COMMAND = '/applications@fangazzettabot';
    const SELECT_COMMAND = '/select@fangazzettabot';
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
                    case self::APPLICATIONS_COMMAND:
                        $this->getApplications($update);
                        break;
                    case self::SELECT_COMMAND:
                        $this->selectApplication($update);
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
            $this->send('Уже идёт приём заявок. Если вы хотите остановить, отправьте ' . self::END_TOUR_COMMAND, $update['message']['chat']['id']);
            return;
        }

        $tour = new Tour([
            'active' => true,
        ]);
        $tour->save();
        $this->send('Запущен приём заявок. Чтоб его остановить, отправьте ' . self::END_TOUR_COMMAND, $update['message']['chat']['id']);
    }

    private function endTour($update)
    {
        $tour = Tour::findOne(['active' => true]);
        if ($tour === null) {
            $this->send('Сейчас закрыт приём заявок. Если вы хотите открыть, отправьте ' . self::NEW_TOUR_COMMAND, $update['message']['chat']['id']);
            return;
        }

        $tour->active = false;
        $tour->ended_at = new Expression('NOW()');
        $tour->save();
        $this->send('Приём заявок завершён. Чтоб выбрать участника, отправьте ' . self::SELECT_COMMAND, $update['message']['chat']['id']);
    }

    private function getApplications($update)
    {
        $tour = Tour::findOne(['active' => true]);
        if ($tour === null) {
            $tour = Tour::find()->orderBy('started_at DESC')->one();
            if ($tour === null) {
                $this->send('Приём заявок не открывался. Если вы хотите открыть, отправьте ' . self::NEW_TOUR_COMMAND, $update['message']['chat']['id']);
                return;
            }
        }

        $applications = Application::findAll(['tour_id' => $tour->id]);
        if (count($applications) === 0) {
            $this->send('Нет ни одной заявки.', $update['message']['chat']['id']);
            return;
        }

        $list = '';
        foreach ($applications as $i => $application) {
            $name = "{$application->first_name} {$application->last_name}";
            if (!empty($application->username)) {
                $name .= " @{$application->username}";
            }
            $name = trim($name);

            $n = $i + 1;
            $list .= "{$n}. {$name}";
            if ($application->selected) {
                $list .= ' - выбран';
            }
            $list .= "\n";
        }

        $this->send("Заявки пользователей:\n{$list}\nЕсли хотите выбрать, отправьте " . self::SELECT_COMMAND, $update['message']['chat']['id']);
    }

    private function selectApplication($update)
    {
        $tour = Tour::findOne(['active' => true]);
        if ($tour !== null) {
            $this->send('Открыт приём заявок. Необходимо сначала закрыть приём заявок ' . self::END_TOUR_COMMAND, $update['message']['chat']['id']);
            return;
        }

        $tour = Tour::find()->orderBy('started_at DESC')->one();
        if ($tour === null) {
            $this->send('Приём заявок не открывался. Если вы хотите открыть, отправьте ' . self::NEW_TOUR_COMMAND, $update['message']['chat']['id']);
            return;
        }

        $application = Application::findOne(['tour_id' => $tour->id, 'selected' => true]);
        if ($application !== null) {
            $application->selected = false;
            $application->save();
        }

        $selected = Application::find()->select('user_id')->where(['selected' => true]);
        $applications = Application::find()->where(['tour_id' => $tour->id])->andWhere(['not in', 'user_id', $selected])->all();
        if (count($applications) === 0) {
            $this->send('Нет ни одной заявки в этом туре или все участники уже ранее выбирались.', $update['message']['chat']['id']);
            return;
        }

        $n = array_rand($applications);
        $application = Application::findOne($applications[$n]['id']);
        $application->selected = true;
        $application->save();

        $name = "{$application->first_name} {$application->last_name}";
        if (!empty($application->username)) {
            $name .= " @{$application->username}";
        }
        $name = trim($name);

        $this->send('Выбран пользователь - ' . $name, $update['message']['chat']['id']);
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
            $this->forward($update['message']['chat']['id'], $update['message']['message_id']);
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
