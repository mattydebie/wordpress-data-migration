<?php


namespace Madebit\WordpressDataMigration;


abstract class AbstractMigration
{
  abstract public function up();
  abstract public function down();
}
