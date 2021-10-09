<?php

use yii\db\Migration;

class m211009_133320_create_table_tour extends Migration
{
    public function up()
    {
        $this->createTable('tour', [
            'id' => $this->primaryKey(),
            'active' => $this->boolean(),
            'started_at' => $this->dateTime(),
            'ended_at' => $this->dateTime(),
        ]);
    }

    public function down()
    {
        $this->dropTable('tour');
    }
}
