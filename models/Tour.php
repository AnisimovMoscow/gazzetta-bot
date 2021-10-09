<?php
namespace app\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;
use yii\db\Expression;

class Tour extends ActiveRecord
{
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'active' => 'Активный ли тур',
            'started_at' => 'Начало тура',
            'ended_at' => 'Конец тура',
        ];
    }
    
    public function behaviors()
    {
        return [
            [
                'class' => TimestampBehavior::class,
                'createdAtAttribute' => 'started_at',
                'updatedAtAttribute' => false,
                'value' => new Expression('NOW()'),
            ],
        ];
    }
}
