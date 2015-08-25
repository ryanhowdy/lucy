<?php

/**
 * Page
 * 
 * @package   Lucy
 * @copyright 2014 Haudenschilt LLC
 * @author    Ryan Haudenschilt <r.haudenschilt@gmail.com> 
 * @license   http://www.gnu.org/licenses/gpl-2.0.html
 */
class Page
{
    private $error;
    private $currentPage;
    private $pageLkup;

    /**
     * Constructor
     * 
     * @param string $currentPage
     * @return void
     */
    public function __construct ($currentPage)
    {
        $this->error = Error::getInstance();

        $this->currentPage = $currentPage;

        $this->pageLkup = array(
            'dashboard'   => _('Dashboard'),
            'user'        => _('User'),
            'login'       => _('Login'),
            'help'        => _('Help'),
            'tickets'     => _('Tickets'),
            'discussions' => _('Discussions'),
        );
    }

    /**
     * displayHeader
     * 
     * @param array $templateOptions 
     * 
     * @return null|boolean
     */
    public function displayHeader ($templateOptions = array())
    {
        $javaScriptIncludes = isset($templateOptions['js_includes'])  ? $templateOptions['js_includes']  : '';
        $javaScriptCode     = isset($templateOptions['js_code'])      ? $templateOptions['js_code']      : '';
        $cssIncludes        = isset($templateOptions['css_includes']) ? $templateOptions['css_includes'] : '';

        // Get config
        try
        {
            $db = ORM::get_db();

            $config = ORM::forTable(DB_PREFIX.'config')->findOne();
        }
        catch (Exception $e)
        {
            $this->error->add(array(
                'title'   => _('Database Error.'),
                'message' => _('Could not get site configuration.'),
                'object'  => $e,
                'file'    => __FILE__,
                'line'    => __LINE__,
                'sql'     => ORM::getLastQuery(),
            ));

            $this->error->displayError();
            return false;
        }

        // Get navigation
        $links = $this->getNavigationLinks();
        if ($links === false)
        {
            $this->error->displayError();
            return false;
        }

        $params = array(
            'language'      => 'en',
            'site_title'    => $config->name,
            'page_title'    => $this->pageLkup[$this->currentPage],
            'js_includes'   => $javaScriptIncludes,
            'js_code'       => $javaScriptCode,
            'css_includes'  => $cssIncludes,
            'links'         => $links,
        );

        $user = new User();
        if ($user->isLoggedIn())
        {
            $params['logged_in'] = $user->name;

            // Set User page title to user's name
            if ($this->currentPage == 'user')
            {
                $params['page_title'] = $user->name;
            }
        }

        // Display the header
        $this->displayTemplate('global', 'header', $params);
        if ($this->error->hasError())
        {
            $this->error->displayError();
            return;
        }

        return true;
    }

    /**
     * displayFooter
     * 
     * @return boolean
     */
    public function displayFooter ()
    {
        $this->displayTemplate('global', 'footer', array('year' => gmdate('Y')));

        return true;
    }

    /**
     * displayMustBeLoggedIn 
     * 
     * Prints the page we show the user when they tried to perform
     * an action that requires them to be logged in.
     * 
     * @return void
     */
    public function displayMustBeLoggedIn ()
    {
        $this->displayTemplate('global', 'must_be_logged_in');
        return;
    }

    /**
     * displayTemplate 
     * 
     * A wrapper that loads and renders a twig template file.
     * 
     * @param string $directory 
     * @param string $name 
     * @param array  $params 
     * 
     * @return void
     */
    public function displayTemplate ($directory, $name, $params = array())
    {
        $directory = basename($directory);

        $themeDir = $this->getThemeDirectory();

        $loader = new Twig_Loader_Filesystem($themeDir.'/'.$directory);
        $twig   = new Twig_Environment($loader);

        $template = $twig->loadTemplate($name.'.html');
        echo $template->render($params);
    }

    /**
     * getNavigationLinks 
     * 
     * @return array
     */
    private function getNavigationLinks ()
    {
        $links = array(
            array(
                'name'   => _('Dashboard'),
                'url'    => 'index.php',
                'status' => ($this->currentPage == 'dashboard' ? 'active' : ''),
            ),
        );

        try
        {
            $db = ORM::get_db();

            foreach (ORM::forTable(DB_PREFIX.'module')->orderByAsc('order')->findResultSet() as $module)
            {
                $links[] = array(
                    'name'   => _($module->name),
                    'url'    => $module->code . '.php',
                    'status' => ($this->currentPage == $module->code ? 'active' : ''),
                );
            }
        }
        catch (Exception $e)
        {
            $this->error->add(array(
                'title'   => _('Database Error.'),
                'message' => _('Could not get module list.'),
                'object'  => $e,
                'file'    => __FILE__,
                'line'    => __LINE__,
                'sql'     => $db->getLastQuery(),
            ));

            return false;
        }

        return $links;
    }

    /**
     * getThemeDirectory 
     * 
     * @todo - this needs configurable
     *
     * @return string
     */
    private function getThemeDirectory ()
    {
        return 'templates/default';
    }
}
