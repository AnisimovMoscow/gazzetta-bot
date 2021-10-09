<?php

use yii\db\Migration;

class m211009_180708_create_table_application extends Migration
{
    public function up()
    {
        $this->createTable('application', [
            'id' => $this->primaryKey(),
            'tour_id' => $this->integer(),
            'user_id' => $this->text(),
            'first_name' => $this->text(),
            'last_name' => $this->text(),
            'username' => $this->text(),
            'selected' => $this->boolean(),
            'created_at' => $this->dateTime(),
        ]);
        $this->createIndex('application_tour', 'application', 'tour_id');
    }

    public function down()
    {
        $this->dropTable('application');
    }
}
