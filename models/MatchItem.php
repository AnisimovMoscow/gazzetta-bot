<?php
namespace app\models;

use yii\db\ActiveRecord;

class MatchItem extends ActiveRecord
{
    public static function tableName()
    {
        return 'match';
    }

    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'status' => 'Статус',
            'started_at' => 'Время начала',
            'tournament' => 'Турнир',
        ];
    }
}
