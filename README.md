MAIN - the simple PHP framework

## Folder structure ##

    site:
      public (the DocRoot of your site)
      .htaccess (see .htaccess)
      index.php
    system (put all system support files in here)
      lib
        main
        classes (store all your model files in here)
        views (create a folder for each controller and an action.html file for every view)
    data (a good place to store all your application data, logs, etc)

So a typical bootstrap would go something like this:

    cd mysite
    mkdir -p system data public system/lib system/classes system/views
    touch public/index.php data/error.log views/default.html
    cd system/lib
    git clone git://github.com/filipp/main.git
    cd ../..
    mate .
  
## index.php ##

A typical index.php using Main would look like this:

    <?php
  
      if (TRUE) {
        error_reporting(E_ALL|E_STRICT);
        ini_set('display_errors', 'On');
      }
      
      $sysdir = realpath(dirname(__FILE__).'/../system');
  
      set_include_path(
        get_include_path()    . PATH_SEPARATOR
        . $sysdir             . PATH_SEPARATOR
        . "{$sysdir}/lib"     . PATH_SEPARATOR
        . "{$sysdir}/conf"    . PATH_SEPARATOR
        . "{$sysdir}/classes" . PATH_SEPARATOR
      );
  
      require_once 'main/MainApp.php';
      require_once 'main/MainController.php';
  
      $locale = MainApp::locale();
      setlocale(LC_ALL, $locale);
      session_start();
  
      MainApp::init();

    ?>

## .htaccess ##

Main uses and is developed on using Apache httpd's mod_rewrite module. The .htaccess file in your web app's
public root folder should look something like this:

    <IfModule mod_rewrite.c>
      RewriteEngine On
      RewriteCond %{REQUEST_FILENAME} -s [OR]
      RewriteCond %{REQUEST_FILENAME} -l [OR]
      RewriteCond %{REQUEST_FILENAME} -d
      RewriteRule ^.*$ - [NC,L]
      RewriteRule ^.*$ index.php [NC,L]
    </IfModule>
