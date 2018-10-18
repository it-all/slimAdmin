# slim-postgres  
slim-postgres is a <a target="_blank" href="https://www.php.net">PHP</a> skeleton framework based on <a target="_blank" href="https://www.slimframework.com/">Slim Micro-Framework</a> and <a target="_blank" href="https://www.postgresql.org/">PostgreSQL</a>.  
  
slim-postgres has a built-in administrative interface and other tools to allow rapid web app development.  
  
INSTALLATION  
composer create-project it-all/slim-postgres 1.*  
<a href="#createDb">Create your PostgreSQL database</a> and <a href="#restoreDb">restore pg_schema.sql and pg_data.sql to it</a>  
Copy/rename .env.example to .env then edit .env  
Edit then run cliScripts/insertAdministrator.php  
Create a website with /public as the home directory  
Navigate to your site to see the default homepage  
Navigate to your site /private (the admin directory defined in settings['adminPath']) and login!  
You may need to composer dump-autoload -o  
You may need to set write permissions on /storage  
  
FEATURES  
<a target="_blank" href="https://www.php.net">PHP</a> 7.1+  
Built on <a target="_blank" href="https://slimframework.com">Slim framework</a>, a front-controller micro-framework for PHP  
<a target="_blank" href="https://postgresql.org">PostgreSQL Database</a> Integration  
<a target="_blank" href="#admin">Administrative User Interface and Navigation</a>  
<a href="#authe">Authentication</a> (Log In/Out)  
<a href="#autho">Authorization</a> (Role Based Access Control for Resource and Functionality Access)   
<a href="#se">Built-in Database Logging/Reporting of system events, login attempts, and errors</a>  
<a href="#eh">Error Handling</a>  
<a href="emailing">Emailing</a> with <a target="_blank" href="https://github.com/PHPMailer/PHPMailer">PHPMailer</a>    
<a href="#csrf">CSRF Checking</a>  
<a href="https://github.com/slimphp/PHP-View">Slim's PHP-View Templates</a>  
HTML Form Generation using <a target="_blank" href="https://github.com/it-all/FormFormer">FormFormer</a>   
Data Validation with <a target="_blank" href="https://github.com/vlucas/valitron">Valitron</a> (NOTE: If you are comparing floating-point numbers with min/max validators, you should install the PHP <a target="_blank" href="http://php.net/manual/en/book.bc.php">BCMath extension</a> for greater accuracy and reliability. The extension is not required for Valitron to work, but Valitron will use it if available, and it is highly recommended.)  
<a href="#xss">XSS Prevention</a>  
<a href="https://github.com/vlucas/phpdotenv">PHP dotenv</a> for configuring server environments  
<a href="#errLog">PHP Error Logging with Stack Trace</a> for debugging  
  
CODING NEW FUNCTIONALITY  
*work in progress*  
Create a new directory under domain and create a Model/View/Controller (or whatever code structure you desire) as necessary. You can model these files after existing functionality such as SlimPostgres/Administrators/Roles (single database table model) or SlimPostgres/Administrators (joined database tables).  
Add and configure your new route to the system by:  
- Adding a new route name constant in config/constants.php  
- Adding the new route in config/routes.php  
- For new administrative resources, add AuthenticationMiddleware to the route (see existing examples in the routes file)  
- For new administrative resources, if authorization is required at a resource or functionality level, add them to the resources / permissions section in config/constants.php, then add AuthorizationMiddleware to the route (see existing examples in the routes file)   
- For new administrative resources, you can add a link in the administrative navigation menu by editing SlimPostgres/AdminNavigation.php, or config/settings.php ['adminNav']. 

<a name="admin">Administrative Interface and Navigation</a>  
Upon browsing to the administrative directory set in $config['adminPath'] authenticating, the appropriate resource is loaded based on $config['slim']['authentication']['administratorHomeRoutes'], if found for the administrator, otherwise from ROUTE_ADMIN_HOME_DEFAULT in constants.php.  
  
The following administrative functionalities are already coded:  
- View System Events
- View Login Attempts
- View, Create, Edit, Delete Administrators (with role assignment)  
- View, Create, Edit, Delete Roles  
- View, Create, Edit, Delete Permissions (with role assignment)  
- Logout  
These options are found in the navigation menu at top left. Once other options are coded, they can be added to the menu by uncommenting/adding to $config['slim']['adminNav'].  

<a name="authe">Authentication</a>  
Administrative resources can require authentication (login) to access. See config/routes.php admin home for adding Authentication Middleware to a route.  

<a name="autho">Authorization</a>  
Administrative resources and functionality can be protected against unauthorized use based on role based administrative permissions. Permissions to access system resources/functionality can be inserted through the administrative interface, then assigned to one or more roles. Administrators are also assigned roles, which then grants them the permissions assigned to those roles. Authorization checks are done by a simple AuthorizationService::isAuthorized(string $resource): bool call, where the $resource string must match the permission title that has been inserted. Checks are also performed in AdminNavigation to determine whether or not to display navigation options. Authorization failures result in alerts being written to the SystemEvents table and the user redirected to the admin homepage with a red alert message displayed. 
  
<a name="se">System Event Database Logging</a>  
Certain events such as logging in, logging out, inserting, updating, and deleting database records are automatically logged into the SystemEvents table. You can choose other events to insert as you write your application. For usage examples and help, search "systemEvents->insert" and see SystemEventsMapper.php. Note that PHP errors are also logged to the SystemEvents table by default (this can be turned off in $config['errors']['logToDatabase']).  

<a name="eh">Error Handling</a>  

Slim's built in error handling has been disabled, and custom error handling implemented, in order to handle any errors encountered prior to running the Slim application, as well as to be able to email an administrator that an error occured, and to log the error to the system_events database table, which is viewable in list form in the administrative interface.  

Reporting Methods:

1. Database Log  
    If the database and system events services have been set as properties in the ErrorHandler class, all errors are logged to the SystemEvents table. The stack trace is not provided, instead, a reference is made to view the log file for complete details.
    
2. File Log  
    All error details are logged to $config['storage']['logs']['pathPhpErrors'].

3. Echo  
    Live Sites*
    Error details are never echoed, rather, a generic error message is echoed. For fatal errors, this message is set in $config['errors']['fatalMessage'].

    Dev Sites*
    Error details are echoed unless the environmental variable ERRORS_ECHO_DEV is set to 0.  
    
4. Email  
    For security, error details are never emailed.

    Live Sites*
    All errors cause generic error notifications to be emailed to $config['errors']['emailTo'].
    
    Dev Sites*
    Generic error notifications are emailed to $config['errors']['emailTo'] if the environmental variable ERRORS_EMAIL_DEV is set to 1.  
    
* The current site is considered a production (live) site unless the environmental variable IS_LIVE is set to 0.  
  
See ErrorHandler.php for further info.  
  
<a name="emailing">Emailing with phpMailer</a>  
Verify mailer service exists (it may not on dev servers, depending on $config['sendEmailsOnDevServer']).  
// magic method to access mailer inside container  
if ($this->mailer !== null) {  
&nbsp;&nbsp;&nbsp;&nbsp;$this->mailer->send(...)  
}  
                
<a name="csrf">CSRF</a>   
The <a href="https://github.com/slimphp/Slim-Csrf" target="_blank">Slim Framework CSRF</a> protection middleware is used to check CSRF form fields. The CSRF key/value generators are added to the container for form field creation. They are also made available to Twig. A failure is logged to SystemEvents as an error, the user's session is unset, and the user is redirected to the (frontend) homepage with an error message.  
  
<a name="xss">XSS Prevention</a>  
<a href="http://us2.php.net/htmlentities" target="_blank">htmlentities()</a> is used to escape output.  
  
<a name="errLog">PHP Error Log</a>  
PHP Errors with stack trace are logged to the file set in config['storage']['errors']['phpErrorLogPath']  
   
<a name="createDb">Create PostgreSQL Database (One Method)</a>  
* $ psql -U postgres (note you may have to edit your pg_hba.conf file to allow local md5 or trust login https://stackoverflow.com/questions/45632463/peer-authentication-failed-for-user-in-postgresql)  
* postgres=# create role myrolename with login; (creating the role with the same name as the database name allows easy psql access)  
* postgres=# alter role myrolename with encrypted password 'mypassword';  
* postgres=# create database mydbname with owner myrolename;  
  
<a name="restoreDb">Import pg_schema.sql and pg_data.sql</a>
* pg_restore -U myrolename -O -c --if-exists -n public -d mydbname pg_schema.dump
* pg_restore -U myrolename -O -n public -d mydbname pg_data.dump
  
===========================================================>Thank you.