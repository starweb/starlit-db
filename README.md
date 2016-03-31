# Starlit Db

[![Build Status](https://travis-ci.org/starweb/starlit-db.svg?branch=master)](https://travis-ci.org/starweb/starlit-db)
[![Code Coverage](https://scrutinizer-ci.com/g/starweb/starlit-db/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/starweb/starlit-db/?branch=master)

A lightweight database/PDO abstraction layer with an ORM like system for mapping data.

Currently only tested with MySQL.

## Installation
Add the package as a requirement to your `composer.json`:
```bash
$ composer require starlit/db
```

## Usage example
```php
<?php
// Adding a user using SQL
$db = new Db('localhost', 'db_user', '****', 'database_name');
$db->insert('users_table', ['name' => 'John Doe']);

// Adding a user using object mapping
$service = new BasicDbEntityService($db);
$user = new User();
$user->setName('John Doe');
$service->save($user);

```


## Requirements
- Requires PHP 5.6 or above.

## License
This software is licensed under the BSD 3-Clause License - see the `LICENSE` file for details.
