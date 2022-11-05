# MAXQDA HTML to Ecel
The aim of this project is to create a propper excel-file from the html output of the paraphrases.

The project does not need a database-server. It is able to work ith SQLite.

## stack
- Laravel 8
- spatie/simple-excel
- PHP 8.1
- SQLite (or any other database)

## How to use
1. clone the project
2. setup your environment (no server needed)
3. Export MAXQDA Paraphrases as HTML ([see MAXQDA-FAQ](https://www.maxqda.de/hilfe-mx20/12-paraphrasieren/paraphrasen-matrix))
4. copy the HTML-file(s) into the `storage/sources`-folder
5. run `php artisan maxqda:import`
6. run `php artisan maxqda:export`
7. the excel-file is in the `storage/exports`-folder

## tested with
- MAXQDA 2022

## Licence
This package is free to use as stated by the [LICENCE.md](LICENSE.md) under the MIT License, but you can [buy me a coffee](https://www.buymeacoffee.com/redFreak) if you want :D.
