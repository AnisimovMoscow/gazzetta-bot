<?php

namespace app\commands;

use app\models\Application;
use app\models\MatchItem;
use app\models\Tour;
use yii\console\Controller;
use yii\console\ExitCode;

class ApplicationsController extends Controller
{
    /**
     * Очищает матчи, туры и заявки
     */
    public function actionClean()
    {
        Application::deleteAll();
        Tour::deleteAll();
        MatchItem::deleteAll();

        echo "Done\n";
        return ExitCode::OK;
    }
}
