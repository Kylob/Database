# use BootPress\Database\Component as Database;

[![Packagist][badge-version]][link-packagist]
[![License MIT][badge-license]](LICENSE.md)
[![HHVM Tested][badge-hhvm]][link-travis]
[![PHP 7 Supported][badge-php]][link-travis]
[![Build Status][badge-travis]][link-travis]
[![Code Climate][badge-code-climate]][link-code-climate]
[![Test Coverage][badge-coverage]][link-coverage]

A PDO wrapper with lazy connections, query profiling, and convenience methods that simplify and speed up your queries.

## Installation

Add the following to your ``composer.json`` file.

``` bash
{
    "require": {
        "bootpress/database": "^1.0"
    }
}
```

## Example Usage

``` php
<?php

use BootPress\Database\Component as Database;

$dsn = 'mysql:dbname=test;host=127.0.0.1';
$username = 'localhost';
$password = 'root';

$pdo = new PDO($dsn, $username, $password);

$db = new Database($pdo);
```

If you already have the PDO connection then you can just pass it along to the constructor, but that has a few disadvantages.  For starters, you've already connected to the database, whether you're going to use it or not.  That may be time and resource consuming, not to mention needless.  If you pass the parameters to us directly, then we will only connect to the database if and when you use it.

``` php
$db = new Database($dsn, $username, $password, array(), array(
    "SET timezone = 'GMT'",
));
```

Now you have your ``$db`` object, but we haven't done anything yet.  Once you crank out a query, then we'll connect to the database, and in this case we'll set the timezone for you as well.  Let's do that now.

```php
// First we'll create a table
$db->exec(array(
    'CREATE TABLE employees (',
    '  id INTEGER PRIMARY KEY,',
    '  name TEXT NOT NULL DEFAULT "",',
    '  title TEXT NOT NULL DEFAULT ""',
    ')',
));

// Insert some records
if ($stmt = $db->insert('employees', array('id', 'name', 'title'))) {
    $db->insert($stmt, array(101, 'John Smith', 'CEO'));
    $db->insert($stmt, array(102, 'Raj Reddy', 'Sysadmin'));
    $db->insert($stmt, array(103, 'Jason Bourne', 'Developer'));
    $db->insert($stmt, array(104, 'Jane Smith', 'Sales Manager'));
    $db->insert($stmt, array(105, 'Rita Patel', 'DBA'));
    $db->close($stmt); // The records will be inserted all at once
}

// You can also try this
if ($db->insert('OR IGNORE INTO employees', array(
    'id' => 106,
    'name' => "Little Bobby'); DROP TABLE employees;--",
    'title' => 'Intern',
))) {
    echo $db->log('count'); // 1 - It worked!
}

// Make some updates
if (!$db->update('employees SET id = 101', 'id', array(
    106 => array(
        'name' => 'Roberto Cratchit',
        'title' => 'CEO',
    )
))) {
    echo $db->log('error'); // A unique id constraint
}

if ($stmt = $db->update('employees', 'id', array('title'))) {
    $db->update($stmt, 103, array('Janitor'));
    $db->update($stmt, 99, array('Quality Control'));
    $db->close($stmt);
}

// And upsert more
if ($stmt = $db->upsert('employees', 'id', array('name', 'title'))) {
    $db->upsert($stmt, 101, array('Roberto Cratchit', 'CEO'));
    $db->upsert($stmt, 106, array('John Smith', 'Developer'));
    $db->close($stmt);
}

$db->upsert('employees', 'id', array(
    107 => array(
        'name' => 'Ella Minnow Pea',
        'title' => 'Executive Assistant',
    ),
));

// Check to see who all is on board
if ($result = $db->query('SELECT name, title FROM employees', '', 'assoc')) {
    while ($row = $db->fetch($result)) {
        print_r($row);
        /*
        array('name'=>'Roberto Cratchit', 'title'=>'CEO')
        array('name'=>'Raj Reddy', 'title'=>'Sysadmin')
        array('name'=>'Jason Bourne', 'title'=>'Janitor')
        array('name'=>'Jane Smith', 'title'=>'Sales Manager')
        array('name'=>'Rita Patel', 'title'=>'DBA')
        array('name'=>'John Smith', 'title'=>'Developer')
        array('name'=>'Ella Minnow Pea', 'title'=>'Executive Assistant')
        */
    }
    $db->close($result);
}

foreach ($db->all('SELECT id, name, title FROM employees') as $row) {
    list($id, $name, $title) = $row;
}

if ($ids = $db->ids('SELECT id FROM employees WHERE title = ?', 'Intern')) {
    // Then Little Bobby Tables isn't as good as we thought.
}

// Find someone to clean things up around here
if ($janitor = $db->row('SELECT id, name FROM employees WHERE title = ?', 'Janitor', 'assoc')) {
    // array('id'=>103, 'name'=>'Jason Bourne')
}

// Get a total head count
echo $db->value('SELECT COUNT(*) FROM employees'); // 7

// Prepare for the worst
$db->exec('DELETE FROM employees WHERE id = ?', 102);
```

For kicks you can ``print_r(Database::logs())`` or ``print_r(Database::errors())`` and see what you get.  If you ever need to access the PDO instance directly, it is at ``$db->connection()`` to use as you see fit.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

[badge-version]: https://img.shields.io/packagist/v/bootpress/database.svg?style=flat-square&label=Packagist
[badge-license]: https://img.shields.io/badge/License-MIT-blue.svg?style=flat-square
[badge-hhvm]: https://img.shields.io/badge/HHVM-Tested-8892bf.svg?style=flat-square
[badge-php]: https://img.shields.io/badge/PHP%207-Supported-8892bf.svg?style=flat-square
[badge-travis]: https://img.shields.io/travis/Kylob/Database/master.svg?style=flat-square
[badge-code-climate]: https://img.shields.io/codeclimate/github/Kylob/Database.svg?style=flat-square
[badge-coverage]: https://img.shields.io/codeclimate/coverage/github/Kylob/Database.svg?style=flat-square

[link-packagist]: https://packagist.org/packages/bootpress/database
[link-travis]: https://travis-ci.org/Kylob/Database
[link-code-climate]: https://codeclimate.com/github/Kylob/Database
[link-coverage]: https://codeclimate.com/github/Kylob/Database/coverage
