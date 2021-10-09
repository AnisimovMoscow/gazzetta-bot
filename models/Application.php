<?php
namespace app\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;
use yii\db\Expression;

class Application extends ActiveRecord
{
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'tour_id' => 'ID тура',
            'user_id' => 'ID пользователя',
            'first_name' => 'Имя пользователя',
            'last_name' => 'Фамилия пользователя',
            'username' => 'Никнейм пользователя',
            'selected' => 'Выбран ли пользователь',
            'created_at' => 'Дата создания',
        ];
    }
    
    public function behaviors()
    {
        return [
            [
                'class' => TimestampBehavior::class,
                'createdAtAttribute' => 'created_at',
                'updatedAtAttribute' => false,
                'value' => new Expression('NOW()'),
            ],
        ];
    }
}
