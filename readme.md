# This lib is still under heavy development
## How to use

create a folder in your working directory for the migrations.
```bash
mkdir migrations
```

Create a file called CURRENT_VERSION in this folder and insert the current version number
```bash
touch migrations/CURRENT_VERSION
echo '0' > migrations/CURRENT_VERSION
```

Include the DataMigration into your project.
```php
add_action('init', function () {
   new DataMigration(get_template_directory() . '/migrations/');
});
```

In the migrations folder, create a migration file. These files should be ordened chronologically followed by a dash `-`
and the classname.
```bash
touch migrations/20210118-DoThisAndThat.php
```

The file should look like this
```php
<?php

namespace Madebit\WordpressDataMigration;

class DoThisAndThat extends \Madebit\WordpressDataMigration\AbstractMigration {

    public function version() {
      return 1;
    }

    public function up()
    {
      // migrate data when migrating up
    }

    public function down()
    {
      // migrate data when migrating down
    }
}
?>
```


To migrate, visit the Rest endpoint `<< YOUR INSTALL >>/wp-json/m8b/v1/migrate`.
To test or execute a specific version add the `?version=<INT>` parameter.
