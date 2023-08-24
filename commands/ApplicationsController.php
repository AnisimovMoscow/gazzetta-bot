<?php

namespace app\commands;

use app\models\Application;
use app\models\Tour;
use yii\console\Controller;
use yii\console\ExitCode;

class ApplicationsController extends Controller
{
    /**
     * Очищает туры и заявки
     */
    public function actionClean()
    {
        Application::deleteAll();
        Tour::deleteAll();

        echo "Done\n";
        return ExitCode::OK;
    }
}
