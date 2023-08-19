<?php

use yii\db\Migration;

class m230723_185600_create_table_match extends Migration
{
    public function up()
    {
        $this->createTable('match', [
            'id' => $this->primaryKey(),
            'status' => $this->string(),
            'started_at' => $this->dateTime(),
        ]);
    }

    public function down()
    {
        $this->dropTable('match');
    }
}
