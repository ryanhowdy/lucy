<?php

session_start();

require_once __DIR__.'/vendor/autoload.php';

define('INSTALL_TEMPLATE', __DIR__.'/templates/install');

$control = new InstallController();
$control->run();
exit();

/**
 * InstallController 
 * 
 * @package   Lucy
 * @copyright 2015 Haudenschilt LLC
 * @author    Ryan Haudenschilt <r.haudenschilt@gmail.com> 
 * @license   http://www.gnu.org/licenses/gpl-2.0.html
 */
class InstallController
{
    /**
     * run 
     * 
     * @return void
     */
    function run ()
    {
        if (isset($_GET['step']))
        {
            $step = $_GET['step'];

            // Step One - Database
            if ($step === '1')
            {
                if (isset($_POST['database-information']))
                {
                    $this->handleStepOne();
                    return;
                }

                $this->displayStepOne();
                return;
            }
            // Step One A - manual config
            else if ($step === '1config')
            {
                $this->handleStepOneManualConfig();
                return;
            }
            // Step One B - check config
            else if ($step === 'check-config')
            {
                $this->handleStepOneCheckConfig();
                return;
            }
            // Step Two - Website
            else if ($step === '2')
            {
                if (isset($_POST['site-information']))
                {
                    $this->handleStepTwo();
                    return;
                }

                $this->displayStepTwo();
                return;
            }
            // Step Three - Account
            else if ($step === '3')
            {
                if (isset($_POST['account-information']))
                {
                    $this->handleStepThree();
                    return;
                }

                $this->displayStepThree();
                return;
            }

            header("Location: install.php");
            return;
        }

        $this->displayRequirementsCheck();
        return;
    }

    /**
     * displayRequirementsCheck 
     * 
     * @return void
     */
    function displayRequirementsCheck()
    {
        $loader = new Twig_Loader_Filesystem(INSTALL_TEMPLATE);
        $twig   = new Twig_Environment($loader);

        $requirementsHaveBeenMet = true;

        // Requirements
        $requirementParams = array();

        // PHP version
        $phpVersion = phpversion();

        $phpParams = array(
            'label'      => 'Yes',
            'label_type' => 'success',
            'name'       => 'PHP 5.3+',
            'text'       => 'You have version '.$phpVersion,
        );

        if (!function_exists('version_compare') || version_compare($phpVersion, '5.3.0', '<'))
        {
            $phpParams['label']      = 'No';
            $phpParams['label_type'] = 'error';
            $requirementsHaveBeenMet = false;
        }

        $requirementParams[] = $phpParams;

        // Config writable
        $configParams = array(
            'label'      => 'Yes',
            'label_type' => 'success',
            'name'       => 'Config Writable',
            'text'       => 'A config file can be automagically created for you.',
        );

        if (!$this->isWritable('/'))
        {
            $configParams['label']      = 'No';
            $configParams['label_type'] = 'almost-success';
            $configParams['text']       = 'A config.php file cannot be created, but you can still continue with the installation.  You will need to manually create this file.';
        }

        $requirementParams[] = $configParams;

        // Gettext supports
        $gettextParams = array(
            'label'      => 'Yes',
            'label_type' => 'success',
            'name'       => 'Gettext',
            'text'       => 'You can use other languages besides English.',
        );

        if (!function_exists("gettext"))
        {
            $gettextParams['label']      = 'No';
            $gettextParams['label_type'] = 'warning';
            $gettextParams['text']       = 'Without Gettext, you will only be able to use the site in English.';
        }

        $requirementParams[] = $gettextParams;

        // Setup the template
        $templateParams = array();

        $templateParams['requirements'] = $requirementParams;

        // We can install
        if ($requirementsHaveBeenMet)
        {
            $templateParams['message']    = "Hooray! It looks like you have met all the requirements needed to install Lucy. Please click the 'Start Installation' button below to begin.";
            $templateParams['install_ok'] = 1;

            $_SESSION['requirements_checked'] = 1;
        }
        // We can NOT install
        else
        {
            $templateParams['message'] = 'Bummer! Lucy cannot be installed.';
        }

        $template = $twig->loadTemplate('requirements.html');
        echo $template->render($templateParams);
    }

    /**
     * displayStepOne 
     * 
     * Displays the form for gathering db info.
     * 
     * @return void
     */
    function displayStepOne ()
    {
        // Make sure we completed previous steps
        if (!isset($_SESSION['requirements_checked']))
        {
            header("Location: install.php");
            return;
        }

        // Save form values, if we refreshed this step
        if (isset($_SESSION['form_values']))
        {
            $formValues = $_SESSION['form_values'];

            unset($_SESSION['form_values']);
        }

        $templateParams = array();

        if (isset($_SESSION['form_errors']))
        {
            if (isset($_SESSION['form_errors']['fields']))
            {
                $templateParams['form_errors'] = array('Please fill out all required fields.');

                foreach ($_SESSION['form_errors']['fields'] as $field)
                {
                    $templateParams[$field.'_error'] = 'has-error';
                }
            }
            else
            {
                $templateParams['form_errors'] = $_SESSION['form_errors'];
            }

            unset($_SESSION['form_errors']);
        }

        if (is_array($formValues))
        {
            foreach ($formValues as $field => $value)
            {
                $templateParams[$field] = $value;
            }
        }

        // Check config exists
        if (file_exists('config.php'))
        {
            $templateParams['form_errors'] = array(
                "Uh-Oh! A config.php file already exists. Please delete it to continue."
            );
        }

        $loader = new Twig_Loader_Filesystem(INSTALL_TEMPLATE);
        $twig   = new Twig_Environment($loader);

        $template = $twig->loadTemplate('step1.html');
        echo $template->render($templateParams);
    }

    /**
     * handleStepOne 
     * 
     * @return void
     */
    function handleStepOne ()
    {
        // Save the form values
        $_SESSION['form_values'] = $_POST;

        // Check required fields
        $requiredFields  = array('database', 'username', 'password');
        $missingRequired = false;

        foreach ($requiredFields as $field)
        {
            if (empty($_POST[$field]))
            {
                $_SESSION['form_errors']['fields'][$field] = $field;

                $missingRequired = true;
            }
        }

        if ($missingRequired)
        {
            header("Location: install.php?step=1");
            return;
        }

        // Test Connection
        $database = $_POST['database'];
        $username = $_POST['username'];
        $password = $_POST['password'];

        ORM::configure('mysql:host=localhost;dbname='.$database);
        ORM::configure('username', $username);
        ORM::configure('password', $password);

        try
        {
            $db = ORM::get_db();
        }
        catch (Exception $e)
        {
            $_SESSION['form_errors'] = array(
                'Could not connect to the database.',
                $e->getMessage()
            );

            header("Location: install.php?step=1");
            return;
        }

        // Check config exists
        if (file_exists('config.php'))
        {
            $_SESSION['form_errors'] = array(
                "Uh-Oh! A config.php file already exists. Please delete it to continue."
            );

            header("Location: install.php?step=1");
            return;
        }

        // Show user how to manual create config
        if (!$this->isWritable('/'))
        {
            header("Location: install.php?step=1config");
            return;
        }

        // See if we can open the config
        $file = @fopen('config.php', 'w');
        if ($file === false)
        {
            $_SESSION['form_errors'] = array(
                "Uh-Oh! I can't create the config.php file."
            );

            header("Location: install.php?step=1");
            return;
        }

        // Create config
        $str  = "<?php
define('DB_DATABASE',   '$database');
define('DB_USERNAME',   '$username');
define('DB_PASSWORD',   '$password');
define('DB_HOST',       'localhost');
define('DB_DRIVER',     'mysql');
define('DB_PREFIX',     'lu_');
define('DB_CONNECTION', DB_DRIVER.':host='.DB_HOST.';dbname='.DB_DATABASE);
define('DEBUG',         false);";

        $write = fwrite($file, $str);
        if ($write === false)
        {
            $_SESSION['form_errors'] = array(
                "Uh-Oh! I can't write to the config.php file."
            );

            header("Location: install.php?step=1");
            return;
        }

        fclose($file);

        $_SESSION['config_created'] = 1;

        if (isset($_SESSION['form_values']))
        {
            unset($_SESSION['form_values']);
        }

        header("Location: install.php?step2");
    }

    /**
     * handleStepOneManualConfig 
     * 
     * @return void
     */
    function handleStepOneManualConfig ()
    {
        if (isset($_SESSION['form_values']))
        {
            $formValues = $_SESSION['form_values'];
        }

        $templateParams = array();

        if (isset($_SESSION['form_errors']))
        {
            $templateParams['errors'] = $_SESSION['form_errors'];
        }

        $templateParams['config'] = "<?php
define('DB_DATABASE',   '".$formValues['database']."');
define('DB_USERNAME',   '".$formValues['username']."');
define('DB_PASSWORD',   '".$formValues['password']."');
define('DB_HOST',       'localhost');
define('DB_DRIVER',     'mysql');
define('DB_PREFIX',     'lu_');
define('DB_CONNECTION', DB_DRIVER.':host='.DB_HOST.';dbname='.DB_DATABASE);
define('DEBUG',         false);";

        $loader = new Twig_Loader_Filesystem(INSTALL_TEMPLATE);
        $twig   = new Twig_Environment($loader);

        $template = $twig->loadTemplate('step1_manual_config.html');
        echo $template->render($templateParams);
    }

    /**
     * handleStepOneCheckConfig 
     * 
     * @return void
     */
    function handleStepOneCheckConfig ()
    {
        // Check config exists
        if (!file_exists('config.php'))
        {
            $_SESSION['form_errors'] = array(
                "Uh-Oh! I can't find the config.php file.  Are you sure you created it."
            );

            header("Location: install.php?step=1config");
            return;
        }

        $_SESSION['config_created'] = 1;

        if (isset($_SESSION['form_values']))
        {
            unset($_SESSION['form_values']);
        }

        header("Location: install.php?step=2");
    }

    /**
     * displayStepTwo 
     * 
     * @return void
     */
    function displayStepTwo ()
    {
        // Make sure we completed previous steps
        if (!isset($_SESSION['requirements_checked']))
        {
            header("Location: install.php");
            return;
        }
        if (!isset($_SESSION['config_created']))
        {
            header("Location: install.php?step1");
            return;
        }

        // Save form values, if we refreshed this step
        if (isset($_SESSION['form_values']))
        {
            $formValues = $_SESSION['form_values'];

            unset($_SESSION['form_values']);
        }

        $templateParams = array();

        if (isset($_SESSION['form_errors']))
        {
            if (isset($_SESSION['form_errors']['fields']))
            {
                $templateParams['form_errors'] = array('Please fill out all required fields.');

                foreach ($_SESSION['form_errors']['fields'] as $field)
                {
                    $templateParams[$field.'_error'] = 'has-error';
                }
            }
            else
            {
                $templateParams['form_errors'] = $_SESSION['form_errors'];
            }

            unset($_SESSION['form_errors']);
        }

        if (is_array($formValues))
        {
            foreach ($formValues as $field => $value)
            {
                $templateParams[$field] = $value;
            }
        }

        $loader = new Twig_Loader_Filesystem(INSTALL_TEMPLATE);
        $twig   = new Twig_Environment($loader);

        $template = $twig->loadTemplate('step2.html');
        echo $template->render($templateParams);
    }

    /**
     * handleStepTwo 
     * 
     * @return void
     */
    function handleStepTwo ()
    {
        // Save the form values
        $_SESSION['form_values'] = $_POST;

        // Check required fields
        $requiredFields  = array('name', 'source_code');
        $missingRequired = false;

        foreach ($requiredFields as $field)
        {
            if (empty($_POST[$field]))
            {
                $_SESSION['form_errors']['fields'][$field] = $field;

                $missingRequired = true;
            }
        }

        if ($missingRequired)
        {
            header("Location: install.php?step=2");
            return;
        }

        require_once 'config.php';

        // bootstrap.php would normally do this, but not during install
        // because the config.php (which holds the db data) wasn't 
        // included until afterwards
        ORM::configure(DB_CONNECTION);
        ORM::configure('username', DB_USERNAME);
        ORM::configure('password', DB_PASSWORD);
        ORM::configure('logging', true);

        // Create tables
        $worked = $this->installTables();

        if (!$worked)
        {
            header("Location: install.php?step=2");
            return;
        }

        // Site is setup
        $_SESSION['site_setup'] = 1;

        if (isset($_SESSION['form_values']))
        {
            unset($_SESSION['form_values']);
        }

        header("Location: install.php?step=3");
    }

    /**
     * displayStepThree 
     * 
     * @return void
     */
    function displayStepThree ()
    {
        // Make sure we completed previous steps
        if (!isset($_SESSION['requirements_checked']))
        {
            header("Location: install.php");
            return;
        }
        if (!isset($_SESSION['config_created']))
        {
            header("Location: install.php?step1");
            return;
        }
        if (!isset($_SESSION['site_setup']))
        {
            header("Location: install.php?step2");
            return;
        }

        // Save form values, if we refreshed this step
        if (isset($_SESSION['form_values']))
        {
            $formValues = $_SESSION['form_values'];

            unset($_SESSION['form_values']);
        }

        $templateParams = array();

        if (isset($_SESSION['form_errors']))
        {
            if (isset($_SESSION['form_errors']['fields']))
            {
                $templateParams['form_errors'] = array('Please fill out all required fields.');

                foreach ($_SESSION['form_errors']['fields'] as $field)
                {
                    $templateParams[$field.'_error'] = 'has-error';
                }
            }
            else
            {
                $templateParams['form_errors'] = $_SESSION['form_errors'];
            }

            unset($_SESSION['form_errors']);
        }

        if (is_array($formValues))
        {
            foreach ($formValues as $field => $value)
            {
                $templateParams[$field] = $value;
            }
        }

        $loader = new Twig_Loader_Filesystem(INSTALL_TEMPLATE);
        $twig   = new Twig_Environment($loader);

        $template = $twig->loadTemplate('step3.html');
        echo $template->render($templateParams);
    }

    /**
     * handleStepThree
     * 
     * @return void
     */
    function handleStepThree ()
    {
        // Save the form values
        $_SESSION['form_values'] = $_POST;

        // Check required fields
        $requiredFields  = array('name', 'source_code');
        $missingRequired = false;

        foreach ($requiredFields as $field)
        {
            if (empty($_POST[$field]))
            {
                $_SESSION['form_errors']['fields'][$field] = $field;

                $missingRequired = true;
            }
        }

        if ($missingRequired)
        {
            header("Location: install.php?step=3");
            return;
        }

        require_once 'config.php';

        ORM::configure(DB_CONNECTION);
        ORM::configure('username', DB_USERNAME);
        ORM::configure('password', DB_PASSWORD);
        ORM::configure('logging', true);

        $hasher   = new Hautelook\Phpass\PasswordHash(8, FALSE);
        $password = $hasher->HashPassword($_SESSION['form_values']['password']);

        // Create admin account
        $user = ORM::forTable(DB_PREFIX.'user')->create();

        $user->set(array(
            'name'                 => $_SESSION['form_values']['name'],
            'source_code_username' => $_SESSION['form_values']['source_code'],
            'email'                => $_SESSION['form_values']['email'],
            'password'             => $password,
            'activated'            => 1,
        ));
        $user->set_expr('updated', 'UTC_TIMESTAMP()');
        $user->set_expr('created', 'UTC_TIMESTAMP()');
        $user->save();

        $userId = $user->id();

        // Insert the default ticket statuses
        $defaultStatuses = array(
            'New',
            'Accepted',
            'Rejected',
            'Assigned',
            'Started',
            'Resolved',
            'Reopened',
        );
        foreach ($defaultStatuses as $status)
        {
            $ticketStatus = ORM::forTable(DB_PREFIX.'ticket_status')->create();

            $ticketStatus->set(array(
                'name'       => $status,
                'created_id' => $userId,
                'updated_id' => $userId,
            ));
            $ticketStatus->set_expr('updated', 'UTC_TIMESTAMP()');
            $ticketStatus->set_expr('created', 'UTC_TIMESTAMP()');
            $ticketStatus->save();
        }

        // Insert default milestone
        $ticketMilestone = ORM::forTable(DB_PREFIX.'ticket_milestone')->create();

        $ticketMilestone->set(array(
            'name'        => 'Major Milestone',
            'description' => 'This milestone is due in 5 weeks.  Better get started.',
            'due'         => gmdate('Y-m-d', strtotime('+5 weeks')),
            'created_id'  => $userId,
            'updated_id'  => $userId,
        ));
        $ticketMilestone->set_expr('updated', 'UTC_TIMESTAMP()');
        $ticketMilestone->set_expr('created', 'UTC_TIMESTAMP()');
        $ticketMilestone->save();

        // Account created
        $_SESSION['account_created'] = 1;

        if (isset($_SESSION['form_values']))
        {
            unset($_SESSION['form_values']);
        }

        header("Location: index.php");
    }


    /**
     * installTables 
     * 
     * @return boolean
     */
    function installTables ()
    {
        $db = ORM::get_db();

        try
        {
            // Drop Tables
            //------------------------------------

            // Tables with user + other foreign keys
            $db->exec("DROP TABLE IF EXISTS `".DB_PREFIX."ticket_comment_votes`");
            $db->exec("DROP TABLE IF EXISTS `".DB_PREFIX."ticket_comment`");
            $db->exec("DROP TABLE IF EXISTS `".DB_PREFIX."ticket`");
            $db->exec("DROP TABLE IF EXISTS `".DB_PREFIX."ticket_milestone`");
            $db->exec("DROP TABLE IF EXISTS `".DB_PREFIX."ticket_status`");

            // Tables with user foreign keys
            $db->exec("DROP TABLE IF EXISTS `".DB_PREFIX."user_activity`");
            $db->exec("DROP TABLE IF EXISTS `".DB_PREFIX."user`");

            // No foreign key tables
            $db->exec("DROP TABLE IF EXISTS `".DB_PREFIX."module`");
            $db->exec("DROP TABLE IF EXISTS `".DB_PREFIX."config`");

            // Create New Tables
            //------------------------------------

            // Config
            $db->exec("
                CREATE TABLE IF NOT EXISTS `".DB_PREFIX."config` (
                    `name`                  VARCHAR(255) NOT NULL,
                    `source_code_url`       VARCHAR(255) NOT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8;"
            );

            $config = ORM::forTable(DB_PREFIX.'config')->create();

            $config->set(array(
                'name'            => $_SESSION['form_values']['name'],
                'source_code_url' => $_SESSION['form_values']['source_code'],
            ));
            $config->save();

            // Modules
            $db->exec("
                CREATE TABLE IF NOT EXISTS `".DB_PREFIX."module` (
                    `id`                    INT NOT NULL AUTO_INCREMENT, 
                    `name`                  VARCHAR(255) NOT NULL,
                    `code`                  VARCHAR(20) NOT NULL,
                    `order`                 TINYINT(2) NOT NULL,
                    PRIMARY KEY (`id`), 
                    UNIQUE KEY `code` (`code`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8;"
            );

            $i = 1;
            $modulesToInstall = array();
            foreach ($_SESSION['form_values']['modules'] as $k => $code)
            {
                $name = str_replace('_', ' ', $code);
                $name = ucwords($name);

                $module = ORM::forTable(DB_PREFIX.'module')->create();

                $module->set(array(
                    'name'  => $name,
                    'code'  => $code,
                    'order' => $i,
                ));
                $module->save();

                $i++;
            }

            // User
            $db->exec("
                CREATE TABLE IF NOT EXISTS `".DB_PREFIX."user` (
                    `id`                    INT NOT NULL AUTO_INCREMENT, 
                    `name`                  VARCHAR(255) NOT NULL,
                    `source_code_username`  VARCHAR(255) NULL,
                    `email`                 VARCHAR(100) NOT NULL, 
                    `password`              VARCHAR(255) NOT NULL, 
                    `token`                 VARCHAR(255) NULL,
                    `birthday`              DATE NULL, 
                    `timezone`              INT NULL,
                    `language`              VARCHAR(6) NOT NULL DEFAULT 'en_US',
                    `activate_code`         CHAR(13) NULL, 
                    `activated`             TINYINT(1) NOT NULL DEFAULT '0', 
                    `login_attempts`        TINYINT(1) NOT NULL DEFAULT '0', 
                    `locked`                DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00', 
                    `created`               DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00', 
                    `updated`               DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
                    PRIMARY KEY (`id`), 
                    UNIQUE KEY `source_code_username` (`source_code_username`),
                    UNIQUE KEY `email` (`email`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;"
            );

            /**
             * User Activity
             *  
             * Levels
             *  
             *   Each level is 1 followed by that levels amount of 0's.
             *   Level 1 =     10
             *   Level 2 =    100
             *   Level 3 =  1,000
             *   Level 4 = 10,000
             *  
             * Categories
             *  
             *   new ticket             10  create a new ticket that gets accepted
             *   resolve ticket         25  resolve an accepted ticket assigned to you
             *   handle ticket           5  change any ticket from new to any other status
             *   assigned ticket         2  when a ticket is assigned to you
             *   upvote comment          1  when you upvote another user comment
             *   receive upvote         ??  when someone upvotes you.
             *                              you get 10 * the level of the person who upvoted you
             *                              so if level 5 upvotes you, you get 50
             *   answer accepted        50  your answer on discussion board is accepted
             *   suggestion accepted    25  your suggestion gets turned into a ticket
             *   suggestion upvoted     10  your suggestion gets upvoted by another user
             *   upvote suggestion       2  when you upvote another user suggestion
             *   translate string       10  you translate a word/phrase into another language
             *   commit code             5  you get 5 points for each line of code committed
             *  
             */

            // User Activity
            $db->exec("
                CREATE TABLE IF NOT EXISTS `".DB_PREFIX."user_activity` (
                    `id`                    INT NOT NULL AUTO_INCREMENT, 
                    `user_id`               INT NOT NULL,
                    `points`                INT NOT NULL DEFAULT '0',
                    `category`              VARCHAR(255),
                    `reason`                TEXT,
                    `created`               DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00', 
                    PRIMARY KEY (`id`), 
                    FOREIGN KEY (`user_id`) REFERENCES `".DB_PREFIX."user`(`id`)
                ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;"
            );

            // Ticket Statuses
            $db->exec("
                CREATE TABLE IF NOT EXISTS `".DB_PREFIX."ticket_status` (
                    `id`                    INT NOT NULL AUTO_INCREMENT, 
                    `name`                  VARCHAR(80) NOT NULL, 
                    `color`                 CHAR(6) NOT NULL DEFAULT 'dddddd',
                    `created`               DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00', 
                    `created_id`            INT NOT NULL,
                    `updated`               DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
                    `updated_id`            INT NOT NULL,
                    PRIMARY KEY (`id`),
                    FOREIGN KEY (`created_id`)  REFERENCES `".DB_PREFIX."user`(`id`),
                    FOREIGN KEY (`updated_id`)  REFERENCES `".DB_PREFIX."user`(`id`)
                ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;"
            );

            // Ticket Milestone
            $db->exec("
                CREATE TABLE IF NOT EXISTS `".DB_PREFIX."ticket_milestone` (
                    `id`                    INT NOT NULL AUTO_INCREMENT, 
                    `name`                  VARCHAR(80) NOT NULL, 
                    `description`           TEXT NOT NULL,
                    `due`                   DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00', 
                    `complete`              DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00', 
                    `created`               DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00', 
                    `created_id`            INT NOT NULL,
                    `updated`               DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
                    `updated_id`            INT NOT NULL,
                    PRIMARY KEY (`id`),
                    FOREIGN KEY (`created_id`)  REFERENCES `".DB_PREFIX."user`(`id`),
                    FOREIGN KEY (`updated_id`)  REFERENCES `".DB_PREFIX."user`(`id`)
                ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;"
            );

            // Tickets
            $db->exec("
                CREATE TABLE IF NOT EXISTS `".DB_PREFIX."ticket` (
                    `id`                    INT NOT NULL AUTO_INCREMENT, 
                    `subject`               VARCHAR(255) NOT NULL, 
                    `description`           TEXT NOT NULL,
                    `assigned_id`           INT NULL,
                    `status_id`             INT NOT NULL DEFAULT '1',
                    `milestone_id`          INT NULL,
                    `created`               DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00', 
                    `created_id`            INT NULL,
                    `updated`               DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
                    `updated_id`            INT NULL,
                    PRIMARY KEY (`id`), 
                    FOREIGN KEY (`assigned_id`) REFERENCES `".DB_PREFIX."user`(`id`),
                    FOREIGN KEY (`status_id`) REFERENCES `".DB_PREFIX."ticket_status`(`id`),
                    FOREIGN KEY (`milestone_id`) REFERENCES `".DB_PREFIX."ticket_milestone`(`id`),
                    FOREIGN KEY (`created_id`)  REFERENCES `".DB_PREFIX."user`(`id`),
                    FOREIGN KEY (`updated_id`)  REFERENCES `".DB_PREFIX."user`(`id`)
                ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;"
            );

            // Ticket Comments
            $db->exec("
                CREATE TABLE IF NOT EXISTS `".DB_PREFIX."ticket_comment` (
                    `id`                    INT NOT NULL AUTO_INCREMENT, 
                    `ticket_id`             INT NOT NULL,
                    `comment`               TEXT NOT NULL,
                    `total_votes`           INT NOT NULL DEFAULT '0',
                    `created`               DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00', 
                    `created_id`            INT NOT NULL,
                    `updated`               DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
                    `updated_id`            INT NOT NULL,
                    PRIMARY KEY (`id`), 
                    FOREIGN KEY (`ticket_id`)  REFERENCES `".DB_PREFIX."ticket`(`id`),
                    FOREIGN KEY (`created_id`) REFERENCES `".DB_PREFIX."user`(`id`),
                    FOREIGN KEY (`updated_id`) REFERENCES `".DB_PREFIX."user`(`id`)
                ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;"
            );

            // Ticket Comment Votes
            $db->exec("
                CREATE TABLE IF NOT EXISTS `".DB_PREFIX."ticket_comment_votes` (
                    `id`                    INT NOT NULL AUTO_INCREMENT, 
                    `ticket_comment_id`     INT NOT NULL,
                    `vote`                  INT NOT NULL,
                    `created`               DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00', 
                    `created_id`            INT NOT NULL,
                    PRIMARY KEY (`id`), 
                    FOREIGN KEY (`ticket_comment_id`)  REFERENCES `".DB_PREFIX."ticket_comment`(`id`),
                    FOREIGN KEY (`created_id`) REFERENCES `".DB_PREFIX."user`(`id`)
                ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;"
            );
        }
        catch (Exception $e)
        {
            $_SESSION['form_errors'] = array(
                'Could not create tables.',
                $e->getMessage(),
            );

            return false;
        }

        return true;
    }

    /**
     * isWritable 
     * 
     * will work in despite of Windows ACLs bug
     *
     * NOTE: use a trailing slash for folders!!!
     * see http://bugs.php.net/bug.php?id=27609
     * see http://bugs.php.net/bug.php?id=30931
     * 
     * @param string $path File path to check permissions
     * 
     * @return  void
     */
    function isWritable ($path)
    {
        if ($path{strlen($path)-1}=='/') // recursively return a temporary file path
            return $this->isWritable($path.uniqid(mt_rand()).'.tmp');
        else if (@is_dir($path))
            return $this->isWritable($path.'/'.uniqid(mt_rand()).'.tmp');
        // check tmp file for read/write capabilities
        $rm = file_exists($path);
        $f  = @fopen($path, 'a');
        if ($f===false)
            return false;
        fclose($f);
        if (!$rm)
            unlink($path);
        return true;
    }
}
