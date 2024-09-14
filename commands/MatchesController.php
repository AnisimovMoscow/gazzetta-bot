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
    const TOURNAMENTS = [
        'italy' => [
            'chat_id' => '@fangazzettachat',
            'sports_id' => 'seria_a',
        ],
        'germany' => [
            'chat_id' => '@fantasybundesligachat',
            'sports_id' => 'bundesliga',
        ],
        'england' => [
            'chat_id' => -1002191305116,
            'sports_id' => 'epl',
        ],
    ];

    /**
     * Обновляет расписание
     */
    public function actionUpdate()
    {
        foreach (self::TOURNAMENTS as $id => $tournament) {
            $matches = Sports::getMatches($tournament['sports_id']);

            foreach ($matches as $statMatch) {
                $match = MatchItem::findOne($statMatch->id);
                $date = Yii::$app->formatter->asDatetime($statMatch->scheduledAt, 'php:Y-m-d H:i:s');
                if ($match === null) {
                    $match = new MatchItem([
                        'id' => $statMatch->id,
                        'status' => $statMatch->matchStatus,
                        'started_at' => $date,
                        'tournament' => $id,
                    ]);
                    $match->save();
                } elseif ($match->status != $statMatch->matchStatus || $match->started_at != $date) {
                    $match->status = $statMatch->matchStatus;
                    $match->started_at = $date;
                    $match->save();
                }
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
            if ($statMatch === null) {
                continue;
            }

            // составы
            self::checkLineup($statMatch, $match->tournament);

            // события
            self::checkEvents($statMatch, $match->tournament);
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
    private static function checkLineup($statMatch, $id)
    {
        $cache = Yii::$app->cache;
        $key = "match_lineup_{$statMatch->id}";

        $send = $cache->get($key);
        if ($send !== false) {
            return;
        }

        if ($statMatch->home->isPreviewLineup || $statMatch->away->isPreviewLineup) {
            return;
        }

        $home = [];
        foreach ($statMatch->home->lineup as $player) {
            if (!$player->lineupStarting) {
                continue;
            }

            $home[] = $player->player->lastName;
        }
        if (count($home) < 11) {
            return;
        }
        $home = implode(', ', $home);

        $away = [];
        foreach ($statMatch->away->lineup as $player) {
            if (!$player->lineupStarting) {
                continue;
            }

            $away[] = $player->player->lastName;
        }
        if (count($away) < 11) {
            return;
        }
        $away = implode(', ', $away);

        $message = "👤 Стартовые составы {$statMatch->home->team->name} – {$statMatch->away->team->name}\n\n";
        $message .= "{$statMatch->home->team->name}: {$home}.\n\n";
        $message .= "{$statMatch->away->team->name}: {$away}.";

        self::sendMessage($message, $id);
        $cache->set($key, true, 24 * 60 * 60);
    }

    /**
     * Проверяет события
     */
    private static function checkEvents($statMatch, $id)
    {
        if (empty($statMatch->events)) {
            return;
        }

        foreach ($statMatch->events as $event) {
            switch ($event->type) {
                case 'SCORE_CHANGE':
                    self::eventGoal($event, $statMatch, $id);
                    break;
                case 'RED_CARD':
                    self::eventRedCard($event, $statMatch, $id);
                    break;
                case 'YELLOW_RED_CARD':
                    self::eventRedCard($event, $statMatch, $id);
                    break;
                case 'PENALTY_MISSED':
                    self::eventPenalty($event, $statMatch, $id);
                    break;
                case 'MATCH_ENDED':
                    self::eventEnd($statMatch, $id);
                    break;
            }
        }
    }

    /**
     * Гол
     */
    private static function eventGoal($event, $statMatch, $id)
    {
        $cache = Yii::$app->cache;
        $key = "event_{$event->id}";

        $send = $cache->get($key);
        if ($send !== false) {
            return;
        }

        if ($event->value->goalScorer === null) {
            return;
        }

        if ($event->value->team == 'HOME') {
            $message = "⚽️ {$statMatch->home->team->name} [{$event->value->homeScore}]:{$event->value->awayScore} {$statMatch->away->team->name}\n";
        } else {
            $message = "⚽️ {$statMatch->home->team->name} {$event->value->homeScore}:[{$event->value->awayScore}] {$statMatch->away->team->name}\n";
        }

        if ($event->value->typeScore == 'PENALTY') {
            $message .= "{$event->value->matchTime}' {$event->value->goalScorer->lastName} (п)";
        } elseif ($event->value->typeScore == 'OWN_GOAL') {
            $message .= "{$event->value->matchTime}' {$event->value->goalScorer->lastName} (а)";
        } else {
            if ($event->value->assist === null) {
                $message .= "{$event->value->matchTime}' {$event->value->goalScorer->lastName}";
            } else {
                $message .= "{$event->value->matchTime}' {$event->value->goalScorer->lastName} ({$event->value->assist->lastName})";
            }
        }

        self::sendMessage($message, $id);
        $cache->set($key, true, 24 * 60 * 60);
    }

    /**
     * Удаление
     */
    private static function eventRedCard($event, $statMatch, $id)
    {
        $cache = Yii::$app->cache;
        $key = "event_{$event->id}";

        $send = $cache->get($key);
        if ($send !== false) {
            return;
        }

        $name = $event->value->player->lastName ?? $event->value->player->name ?? '';

        $message = "🟥 Удаление {$statMatch->home->team->name} – {$statMatch->away->team->name}\n";
        $message .= "{$event->value->matchTime}' {$name} ";

        if ($event->value->team == 'HOME') {
            $message .= "({$statMatch->home->team->name})";
        } else {
            $message .= "({$statMatch->away->team->name})";
        }

        self::sendMessage($message, $id);
        $cache->set($key, true, 24 * 60 * 60);
    }

    /**
     * Незабитый пенальти
     */
    private static function eventPenalty($event, $statMatch, $id)
    {
        $cache = Yii::$app->cache;
        $key = "event_{$event->id}";

        $send = $cache->get($key);
        if ($send !== false) {
            return;
        }

        $message = "❌ Незабитый пенальти {$statMatch->home->team->name} – {$statMatch->away->team->name}\n";
        $message .= "{$event->value->matchTime}' {$event->value->player->lastName} ";

        if ($event->value->team == 'HOME') {
            $message .= "({$statMatch->home->team->name})";
        } else {
            $message .= "({$statMatch->away->team->name})";
        }

        self::sendMessage($message, $id);
        $cache->set($key, true, 24 * 60 * 60);
    }

    /**
     * Конец матча
     */
    private static function eventEnd($statMatch, $id)
    {
        $cache = Yii::$app->cache;
        $key = "match_end_{$statMatch->id}";

        $send = $cache->get($key);
        if ($send !== false) {
            return;
        }

        $message = "⚡️ {$statMatch->home->team->name} {$statMatch->home->score}:{$statMatch->away->score} {$statMatch->away->team->name}\n";
        $message .= "Матч завершён";

        self::sendMessage($message, $id);
        $cache->set($key, true, 24 * 60 * 60);
    }

    /**
     * Отправляет сообщение
     */
    private static function sendMessage($message, $id)
    {
        $token = Yii::$app->params["token-{$id}"];
        $bot = new BotApi($token);

        try {
            $bot->sendMessage(self::TOURNAMENTS[$id]['chat_id'], $message);
        } catch (Exception $e) {
            Yii::error('Send error. Message: ' . $e->getMessage() . ' Code: ' . $e->getCode(), 'send');
        }
    }
}
