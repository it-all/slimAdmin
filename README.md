# slim-postgres  
WORK IN PROGRESS  
  
slim-postgres is a PHP 7.1+, PostgreSQL RESTful web platform with built-in administration and other features, based on <a target="_blank" href="https://www.slimframework.com/">Slim Framework</a>.  

FEATURES  
Built on <a target="_blank" href="https://slimframework.com">Slim framework</a>, a front-controller micro-framework for PHP 
<a target="_blank" href="https://postgresql.org">PostgreSQL Database</a> Integration  
<a href="#authe">Authentication</a> (Log In/Out)  
<a href="#autho">Authorization</a> (Permissions for Resource and Functionality Access)   
<a target="_blank" href="#admin">Administrative User Interface and Navigation</a>  
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
  
INSTALLATION  
coming soon  
  
CODING NEW FUNCTIONALITY  
*work in progress*  
Create a new directory under domain and create a Model/View/Controller (or whatever code structure you desire) as necessary. You can model these files after existing functionality such as SlimPostgres/Administrators/Roles (single database table model) or SlimPostgres/Administrators (joined database tables).  
Add and configure your new route to the system by:  
- Adding a new route name constant in config/constants.php  
- Adding the new route in config/routes.php  
- For new administrative resources, add AuthenticationMiddleware to the route (see existing examples in the routes file)  
- For new administrative resources, if authorization is required at a resource or functionality level, add them to the 'administratorPermissions' key in config/settings.php, then add AuthorizationMiddleware to the route (see existing examples in the routes file)   
- For new administrative resources, you can add a link in the administrative navigation menu by editing SlimPostgres/AdminNavigation.php, or config/settings.php ['adminNav']. 

<a name="authe">Authentication</a>  
Administrative resources can require authentication (login) to access. See config/routes.php admin home for adding Authentication Middleware to a route.  

<a name="autho">Authorization</a>  
Administrative resources and functionality can be protected against unauthorized use based on administrative roles. Resource and functionality access is defined in config.php in the 'administratorPermissions' array key based on the role and is set in routes.php on resources as necessary, in AdminNavigation to determine whether or not to display navigation options, and in views and controllers as necessary to grant or limit functionality access. Authorization failures result in alerts being written to the SystemEvents table and the user redirected to the admin homepage with a red alert message displayed. Authorization can be set as a minimum role level, where all roles with an equal or better level will be authorized, or as a set of authorized roles.

<a name="admin">Administrative Interface and Navigation</a>  
Upon browsing to the administrative directory set in $config['adminPath'] authenticating, the appropriate resource is loaded based on $config['slim']['authentication']['administratorHomeRoutes']. The following administrative functionalities are already coded:  
- View System Events
- View Login Attempts
- View, Create, Edit, Delete Administrators (various permissions/rules apply)
- View, Create, Edit, Delete Roles (various permissions/rules apply)
- Logout  
These options are found in the navigation menu at top left. Once other options are coded, they can be added to the menu by uncommenting/adding to $config['slim']['adminNav'].  

<a name="se">System Event Database Logging</a>  
Certain events such as logging in, logging out, inserting, updating, and deleting database records are automatically logged into the SystemEvents table. You can choose other events to insert as you write your application. For usage examples and help, search "systemEvents->insert" and see SystemEventsMapper.php. Note that PHP errors are also logged to the SystemEvents table by default (this can be turned off in $config['errors']['logToDatabase']).  

<a name="eh">Error Handling</a>  

Slim's built in error handling has been disabled, and custom error handling implemented, in order to handle any errors encountered prior to running the Slim application, as well as to be able to email an administrator that an error occured, and to log the error to the system_events database table, viewable in list form in the administrative interface.  

Reporting Methods:

1. Database Log
    If the database and system events services have been set as properties in the ErrorHandler class, all errors are logged to the SystemEvents table. The stack trace is not provided, instead, a reference is made to view the log file for complete details.
    
2. File Log
    All error details are logged to $config['storage']['logs']['pathPhpErrors'].

3. Echo
    Live Servers*
    Error details are never echoed, rather, a generic error message is echoed. For fatal errors, this message is set in $config['errors']['fatalMessage'].

    Dev Servers*
    Error details are echoed if $config['errors']['echoDev'] is true
    
4. Email
    For security, error details are never emailed.

    Live Servers
    All errors cause generic error notifications to be emailed to $config['errors']['emailTo'].
    
    Dev Servers*
    Generic error notifications are emailed to $config['errors']['emailTo'] if $config['errors']['emailDev'] is true.
    
* $config['isLive'] boolean from .env determines whether this is a production site.  
  
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
THIS SECTION NEEDS UPDATING. TWIG IS NO LONGER BEING USED, THEREFORE ANY DISPLAYED USER DATA MUST BE ESCAPED USING htmlspecialchars().  
  
The appropriate <a target="_blank" href="https://twig.sensiolabs.org/doc/2.x/filters/escape.html" target="_blank">Twig escape filter</a> are used for any user-input data* that is output through Twig. Note that Twig defaults to autoescape 'html' in the autoescape environment variable: https://twig.sensiolabs.org/api/2.x/Twig_Environment.html  
  
protectXSS() or arrayProtectRecursive() should be called for any user-input data* that is output into HTML independent of Twig (currently there is none).
  
*Note this includes database data that has been input by any user, including through the admin  
  
<a name="errLog">PHP Error Log</a>  
PHP Errors with stack trace are logged to the file set in config['storage']['errors']['phpErrorLogPath']  
  
Miscellaneous Instructions  

To print debugging info in admin pages:  
Send a 'debug' variable to twig ie:   
return $this->view->render($response, 'admin/lists/administratorsList.twig',['debug' => arrayWalkToStringRecursive($_SESSION)]);  
This is because the html main page content is set to 100% height, and simply doing a var_dump or echo can cause an unreadable display of the content.  

===========================================================Thank you.