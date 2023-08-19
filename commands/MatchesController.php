<?php

namespace app\commands;

use app\components\Sports;
use app\models\MatchItem;
use DateTime;
use Exception;
use TelegramBot\Api\BotApi;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;

class MatchesController extends Controller
{
    const CHAT_ID = '@fangazzettachat';
    const TOURNAMENT = 'seria_a';

    /**
     * Обновляет расписание
     */
    public function actionUpdate()
    {
        $matches = Sports::getMatches(self::TOURNAMENT);

        foreach ($matches as $statMatch) {
            $match = MatchItem::findOne($statMatch->id);
            $date = Yii::$app->formatter->asDatetime($statMatch->scheduledAt, 'php:Y-m-d H:i:s');
            if ($match === null) {
                $match = new MatchItem([
                    'id' => $statMatch->id,
                    'status' => $statMatch->matchStatus,
                    'started_at' => $date,
                ]);
                $match->save();
            } elseif ($match->status != $statMatch->matchStatus || $match->started_at != $date) {
                $match->status = $statMatch->matchStatus;
                $match->started_at = $date;
                $match->save();
            }
        }

        return ExitCode::OK;
    }

    /**
     * Проверяет активные матчи
     */
    public function actionEvents()
    {
        $matches = self::getActiveMatches();

        foreach ($matches as $match) {
            $statMatch = Sports::getMatch($match->id);

            // составы
            self::checkLineup($statMatch);

            // события
            self::checkEvents($statMatch);
        }

        return ExitCode::OK;
    }

    /**
     * Возвращает матчи из интервала +/- 2 часа
     */
    private static function getActiveMatches()
    {
        $start = new DateTime('now');
        $start->modify('-4 hours');
        $start = Yii::$app->formatter->asDatetime($start, 'php:Y-m-d H:i:s');

        $end = new DateTime('now');
        $end->modify('+2 hours');
        $end = Yii::$app->formatter->asDatetime($end, 'php:Y-m-d H:i:s');

        return MatchItem::find()->where(['>=', 'started_at', $start])->andWhere(['<=', 'started_at', $end])->all();
    }

    /**
     * Проверяет составы
     */
    private static function checkLineup($statMatch)
    {
        $cache = Yii::$app->cache;
        $key = "match_lineup_{$statMatch->id}";

        $send = $cache->get($key);
        if ($send !== false) {
            return;
        }

        if (empty($statMatch->home->lineup) || empty($statMatch->away->lineup)) {
            return;
        }

        $home = [];
        foreach ($statMatch->home->lineup as $player) {
            if (!$player->lineupStarting) {
                continue;
            }

            $home[] = $player->player->lastName;
        }
        $home = implode(', ', $home);

        $away = [];
        foreach ($statMatch->away->lineup as $player) {
            if (!$player->lineupStarting) {
                continue;
            }

            $away[] = $player->player->lastName;
        }
        $away = implode(', ', $away);

        $message = "👤 Стартовые составы {$statMatch->home->team->name} – {$statMatch->away->team->name}\n\n";
        $message .= "{$statMatch->home->team->name}: {$home}.\n\n";
        $message .= "{$statMatch->away->team->name}: {$away}.";

        self::sendMessage($message);
        $cache->set($key, true, 24 * 60 * 60);
    }

    /**
     * Проверяет события
     */
    private static function checkEvents($statMatch)
    {
        foreach ($statMatch->events as $event) {
            switch ($event->type) {
                case 'SCORE_CHANGE':
                    self::eventGoal($event, $statMatch);
                    break;
                case 'RED_CARD':
                    self::eventRedCard($event, $statMatch);
                    break;
                case 'YELLOW_RED_CARD':
                    self::eventRedCard($event, $statMatch);
                    break;
                case 'MATCH_ENDED':
                    self::eventEnd($statMatch);
                    break;
            }
        }
    }

    /**
     * Гол
     */
    private static function eventGoal($event, $statMatch)
    {
        $cache = Yii::$app->cache;
        $key = "event_{$event->id}";

        $send = $cache->get($key);
        if ($send !== false) {
            return;
        }

        if ($event->value->team == 'HOME') {
            $message = "⚽️ {$statMatch->home->team->name} [{$statMatch->home->score}]:{$statMatch->away->score} {$statMatch->away->team->name}\n";
        } else {
            $message = "⚽️ {$statMatch->home->team->name} {$statMatch->home->score}:[{$statMatch->away->score}] {$statMatch->away->team->name}\n";
        }

        if ($event->value->methodScore == 'penalty') {
            $message .= "{$event->value->matchTime}' {$event->value->goalScorer->lastName} (п)";
        } elseif ($event->value->methodScore == 'own_goal') {
            $message .= "{$event->value->matchTime}' {$event->value->goalScorer->lastName} (а)";
        } else {
            if ($event->value->assist === null) {
                $message .= "{$event->value->matchTime}' {$event->value->goalScorer->lastName}";
            } else {
                $message .= "{$event->value->matchTime}' {$event->value->goalScorer->lastName} ({$event->value->assist->lastName})";
            }
        }

        self::sendMessage($message);
        $cache->set($key, true, 24 * 60 * 60);
    }

    /**
     * Удаление
     */
    private static function eventRedCard($event, $statMatch)
    {
        $cache = Yii::$app->cache;
        $key = "event_{$event->id}";

        $send = $cache->get($key);
        if ($send !== false) {
            return;
        }

        $message = "🟥 Удаление {$statMatch->home->team->name} – {$statMatch->away->team->name}\n";
        $message .= "{$event->value->matchTime}' {$event->value->player->lastName} ";

        if ($event->value->team == 'HOME') {
            $message .= "({$statMatch->home->team->name})";
        } else {
            $message .= "({$statMatch->away->team->name})";
        }

        self::sendMessage($message);
        $cache->set($key, true, 24 * 60 * 60);
    }

    /**
     * Конец матча
     */
    private static function eventEnd($statMatch)
    {
        $cache = Yii::$app->cache;
        $key = "match_end_{$statMatch->id}";

        $send = $cache->get($key);
        if ($send !== false) {
            return;
        }

        $message = "⚡️ {$statMatch->home->team->name} {$statMatch->home->score}:{$statMatch->away->score} {$statMatch->away->team->name}\n";
        $message .= "Матч завершён";

        self::sendMessage($message);
        $cache->set($key, true, 24 * 60 * 60);
    }

    /**
     * Отправляет сообщение
     */
    private static function sendMessage($message)
    {
        $token = Yii::$app->params['token'];
        $bot = new BotApi($token);

        try {
            $bot->sendMessage(self::CHAT_ID, $message);
        } catch (Exception $e) {
            Yii::error('Send error. Message: ' . $e->getMessage() . ' Code: ' . $e->getCode(), 'send');
        }
    }
}
