# Technical test

This repository holds a program that imports job offers to a SQLite database from the France Travail API.

## Setup
- Install PHP >=8.1 at https://www.php.net/manual/en/install.php and add it to your PATH variables
- Install Symfony at https://symfony.com/download and add it to your PATH variables
- Install Composer at https://getcomposer.org/download/ and add it to your PATH variables
- Open your terminal at project root
- Run command ```composer install``` to install dependencies
- Run command ```php bin/console doctrine:schema:update --force``` to create database
- Create a .env.local file manually or via command ```composer dump-env dev```
- Add your FRANCE_TRAVAIL_CLIENT_ID (client ID) and FRANCE_TRAVAIL_CLIENT_SECRET (client secret) variables, available after being registered to this API : https://francetravail.io/data/api/offres-emploi
- Launch the server with command ```symfony server:start```

## Usage
I have made two options available to import job offers : the first one is a command line, the second is a web API.

### Import data by using the command line
- Run command ```php bin/console app:import-jobs```
- Optionnally, you can pass two options :
    - option 1 is the city you want to import job offers from. To use is, use ```--city [city_name]```.  Available options for ```city_name``` are ```bordeaux```, ```rennes```, ```paris```. If no city name is passed, job offers from all three cities will be imported
    - option 2 is the date of creation you want to import job offers from. To use is, use ```--date [selected_date]```. The ```selected_date``` must follow the following format : ```YYY-mm-dd```. If no date is passed, the current date will be used by default.
- If the import was successful, the log file name will be displayed in the console, you will find it in the ```/public/logs``` folder. If not, a message will inform you that something went wrong

### Import data by using the web API
- Go to ```https://localhost:8000/import-jobs```
- You can also pass two optional options as query parameters:
    - option 1 is ```city=[city_name]```.  Available options for ```city_name``` are ```bordeaux```, ```rennes```, ```paris```. If no city name is passed, job offers from all three cities will be imported
    - option 2 is ```date=[selected_date]```. The ```selected_date``` must follow the following format : ```YYY-mm-dd```. If no date is passed, the current date will be used by default.
- If the import was successful, you will be redirected to the newly created log file displaying information on the imported data. Otherwise you will be redirected to the homepage with an error message.