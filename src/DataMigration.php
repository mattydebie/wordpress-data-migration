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
    $files = [];

    foreach (glob(DataMigration::$path . '*.php') as $file) {
      preg_match('|^/?.*/(\d+)-(.*)\.php$|', $file, $match);
      $files[$match[1]] = (object)[
        'path' => $file,
        'class' => $match[2],
      ];
    }

    if ($reverse)
      krsort($files, SORT_NATURAL);
    else
      ksort($files, SORT_NATURAL);


    foreach ($files as $version => $migration) {
      if ($filter($version)) {
        include_once "" . $migration->path;
        $classname = 'Madebit\\WordpressDataMigration\\' . $migration->class;

        /**
         * @var AbstractMigration
         */
        $class = new $classname();
        error_log('Executing migration: ' . $version);
        $cb($class);
        update_option('madebit_migration_version', $version);
      }
    }
  }

  /**
   * Rest api endpoint
   */
  public function migrate(\WP_Rest_Request $request)
  {
    if ($request->has_param('version')) {
      $version = intval($request->get_param('version'));
      DataMigration::forMigrations(
        fn($v) => $v == $version,
        fn($class) => $class->up()
      );

      return 'OK, version ' . $version;
    }

    DataMigration::check_migration();

    return 'OK';
  }
}
