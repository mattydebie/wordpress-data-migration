<?php

namespace Madebit\WordpressDataMigration;

class DataMigration
{
  static private $BUSY = false;
  static private $path;

  public function __construct($path)
  {
    DataMigration::$path = $path;
    register_rest_route('m8b/v1', '/migrate', [
      'method' => 'GET',
      'callback' => '\Madebit\WordpressDataMigration\DataMigration::migrate'
    ]);
  }

  private static function check_migration()
  {
    if (DataMigration::$BUSY) {
      error_log('Already checking migrations');
      return;
    }

    error_log('Checking migrations');
    DataMigration::$BUSY = true;

    try {
      $last_version = get_option('madebit_migration_version');
      $current_version = intval(file_get_contents(DataMigration::$path . 'CURRENT_VERSION'));

      if ($last_version < $current_version) {
        DataMigration::upgrade_from_to($last_version, $current_version);
      } else if ($last_version > $current_version) {
        DataMigration::downgrade_from_to($current_version, $last_version);
      }

    } finally {
      DataMigration::$BUSY = false;
    }

  }

  static private function upgrade_from_to($from, $to)
  {
    error_log(sprintf("Migrate version %s to %s", $from, $to));
    DataMigration::forMigrations(
      fn($version) => $version > $from && $version <= $to,
      fn($class) => $class->up()
    );
  }

  static private function downgrade_from_to($from, $to)
  {
    error_log(sprintf("Migrate version %s to %s", $from, $to));
    DataMigration::forMigrations(
      fn($v) => $v < $from && $v >= $to,
      fn($class) => $class->down(),
      true
    );
  }

  static private function forMigrations(callable $filter, callable $cb, $reverse = false)
  {
    $files = glob(DataMigration::$path . '*.php');
    if ($reverse) $files = array_reverse($files);

    foreach ($files as $file) {
      include_once "$file";
      preg_match('/.*\/\d+-(.*)\.php/', $file, $matches);
      $classname = 'Madebit\\WordpressDataMigration\\' . $matches[1];

      /**
       * @var AbstractMigration
       */
      $class = new $classname();
      error_log($class->version());

      if ($filter($class->version())) {
        error_log('Executing migration: ' . $class->version());
        $cb($class);
        update_option('madebit_migration_version', $class->version());
      }
      }
    }

    /**
     * Rest api endpoint
     */
    public
    function migrate(\WP_Rest_Request $request)
    {
      if ($request->has_param('version')) {
        $version = intval($request->get_param('version'));
        DataMigration::forMigrations(
          fn($v) => $v == $version,
          fn($class) => $class->up()
        );

        return 'OK';
      }

      DataMigration::check_migration();

      return 'OK';
    }
  }
