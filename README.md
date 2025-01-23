# Voyager Bread Package - TPlus
[![N|Solid](http://bit.ly/2Uh06oP)](https://facebook.com/lnht2101)

--------

Description: 
- Generate DATABASE and BREAD from a model
- Remove DATABASE and BREAD
- Auto set Roles (for Admin) and create Menu Builder

# Installation Steps
### 1. Require the Package and install Voyager
After creating your new Laravel application you can include the Voyager package with the following command:
```sh
$ composer require tplus/voyager-bread
```
The command already includes requesting ```tcg/voyager```
### 2. Add the DB Credentials & APP_URL
Next make sure to create a new database and add your database credentials to your .env file:
```sh
DB_HOST=localhost
DB_DATABASE=homestead
DB_USERNAME=homestead
DB_PASSWORD=secret
```
You will also want to update your website URL inside of the APP_URL variable inside the .env file:
```sh
APP_URL=http://localhost:8000
```
### 3. Run The Installer of Voyager
Lastly, we can install voyager. You can do this either with or without dummy data. The dummy data will include 1 admin account (if no users already exists), 1 demo page, 4 demo posts, 2 categories and 7 settings.

To install Voyager without dummy simply run
```sh
php artisan voyager:install
```
If you prefer installing it with dummy run
```sh
php artisan voyager:install --with-dummy
```
> Troubleshooting: Specified key was too long error. If you see this error message you have an outdated version of MySQL, use the following solution: https://laravel-news.com/laravel-5-4-key-too-long-error

And we're all good to go!
Start up a local development server with php artisan serve And, visit http://localhost:8000/admin.

# Using the Package
### 1. Introduce Voyager Bread Command
This is command to generate the DATABASE and BREAD
```sh
$ php artisan voyager:bread [options] [--] [<model>]
```
Arguments: ```Model```


| Options | Description |
| ------ | ------ |
| **--ns[=NS]** | The namespace of model. ```[default: "App\Models"]``` |
| **--db** | Create or update database |
| **--del** | Delete bread and database |


Run ```php artisan voyager:bread -h``` to see help message
### 2. Generate Examples 
Run the command below to see examples
```sh
$ php artisan voyager:bread ExampleModel --db
```
There are 4 example models in the namespace ```App\Models\Examples```

- **Formfields.php** structure to generate field types
- **Employee.php** structure of model, used to generate belongTo relationship
- **Company.php** structure of model, used to generate belongToMany and hasMany relationships
- **Services.php** structure of model

### 3. Generate Database and Bread for a Model
Example:
```sh
$ php artisan voyager:bread Company
```
This command will generate Bread of ```Company``` model in the default namespace ```App\Models```
If you want to change the namespace then you just need to add ```--ns``` option to the command
Example:
```sh
$ php artisan voyager:bread Company --ns=App
```
Be careful, you need to create the Database first before generating the Bread, then you use the ```--db``` option
Example:
```sh
$ php artisan voyager:bread Company --ns=App --db
```
### 4. Generate Database and Bread for ALL Models
You do not need to declare the ```argument``` in the command, then all models in the namespace will be generated database and bread

- All models in the namespace ```App\Models```  will generate ```database``` and ```bread```:
```sh
$ php artisan voyager:bread --db
```
- All models in the namespace ```App\Models```  will generate ```bread```:
```sh
$ php artisan voyager:bread
```
- All models in the namespace ```App```  will generate ```database``` and ```bread```:
```sh
$ php artisan voyager:bread --ns=App --db
```

### 5. Remove Database and Bread for model (and for all models)

- Remove BREAD for all example models 
```sh
$ php artisan voyager:bread ExampleModel --del
```
- Remove BREAD and DATABASE for all example models 
```sh
$ php artisan voyager:bread ExampleModel --db --del
```
- Remove BREAD and DATABASE for all models in the namespace ```App\Models``` 
```sh
$ php artisan voyager:bread --db --del
```
- Remove BREAD and DATABASE for all models in the namespace ```App``` 
```sh
$ php artisan voyager:bread --ns=App --db --del
```

# See more:
- Laravel Document: [https://laravel.com/docs/5.8](https://laravel.com/docs/5.8)
- Voyager Document: [https://voyager-docs.devdojo.com](https://voyager-docs.devdojo.com)
