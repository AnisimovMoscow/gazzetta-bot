<?php

use yii\db\Migration;

class m240204_161430_add_match_tournament extends Migration
{
    public function up()
    {
        $this->addColumn('match', 'tournament', $this->string());
        $this->update('match', ['tournament' => 'italy']);
    }

    public function down()
    {
        $this->dropColumn('match', 'tournament');
    }
}
