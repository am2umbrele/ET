# ET

## Requirements

PHP 7.2 or greater


## Setup

**Run the following command**

```
git clone git@github.com:am2umbrele/ET.git
```
**Run**
```
composer install
```
Create tables
```
CREATE TABLE `elevators` (
	`id` INT(11) NOT NULL AUTO_INCREMENT,
	`floor` INT(11) NOT NULL,
	`status` TINYINT(1) NOT NULL DEFAULT '0' COMMENT '0 - idle, 1 - moving',
	PRIMARY KEY (`id`) USING BTREE,
	INDEX `floor` (`floor`) USING BTREE
)
COLLATE='latin1_swedish_ci'
ENGINE=InnoDB
AUTO_INCREMENT=3
;
CREATE TABLE `elevator_move_log` (
	`id` INT(11) NOT NULL AUTO_INCREMENT,
	`elevator_id` INT(11) NOT NULL,
	`floor_from` INT(11) NOT NULL,
	`floor_to` INT(11) NOT NULL,
	`created` TIMESTAMP NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
	PRIMARY KEY (`id`) USING BTREE,
	INDEX `elevator_id` (`elevator_id`) USING BTREE
)
COLLATE='latin1_swedish_ci'
ENGINE=InnoDB
;

```
**Add your database info to `src\DB.php`** 
```
'hostname' => '',
'username' => '',
'password' => '',
'database' => '',
```
**Change .env variables** 
```
ELEVATOR_CAR_COUNT=1
FLOOR_COUNT=2
baseUrl=''
```
## Send request to elevators
Make a POST request to
```
yourBaseUrl/scan
```
with the raw json body ex:
```
{
    "floors": [5, 3, 7, 4, 9, 11, -1]
}
```


## Run tests
```
./vendor/bin/phpunit tests
```
