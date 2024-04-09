# custom-cms
Efutures Custom CMS

Setting Up
1. edit config.php with your desired parameters
2. run setup.php. Upon completion, it will show a username and passowrd
3. Enjoy!

Endpoints - if you have don't have rewrite module enabled, add .php at the end
User - api/user
Content - api/content

Folder and Files Description
api folder contains necessary scripts for the handling of api requests. Each endpoint has it's own file.
Database folder contains 2 files. ConnectionManager is a file that is used for stablishing and handling database connection. Db structure is a file that is used to create database and tables.
logs folder contains application level log for both informational and error logs.
Utility folder contains supporting utilities such as the logger functionality, pagination handler, response generator and more.
config.php contains configuration variables.
setup.php is a script used to setup the system.
cms.json is the postman documentation for the project

make sure that the log files have necessary permissions as they would need write permission.