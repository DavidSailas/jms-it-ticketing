-- create-database.sql
-- Import this in Herd's Adminer (SQL command / Import) while logged in as root.
-- It only creates the empty database. The tables and the login accounts are
-- created afterwards by "php artisan migrate:fresh --seed".

CREATE DATABASE IF NOT EXISTS `jms_it_ticketing`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
