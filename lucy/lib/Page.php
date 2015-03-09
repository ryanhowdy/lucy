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
    private $lucyError;
    private $currentPage;
    private $pageLkup;

    /**
     * Constructor
     * 
     * @return void
     */
    public function __construct ($currentPage)
    {
        $this->lucyError = Lucy_Error::getInstance();

        // Ensure that the site has been installed first
        if (!file_exists('config.php'))
        {
            header("Location: install.php");
            return;
        }

        ORM::configure(DB_CONNECTION);
        ORM::configure('username', DB_USERNAME);
        ORM::configure('password', DB_PASSWORD);
        ORM::configure('logging', true);

        $this->currentPage = $currentPage;

        $this->pageLkup = array(
            'dashboard' => _('Dashboard'),
        );
    }

    /**
     * displayHeader
     * 
     * @param array $templateOptions 
     * 
     * @return boolean
     */
    public function displayHeader ($templateOptions = array())
    {
        // Get config
        try
        {
            $db = ORM::get_db();

            $config = ORM::forTable(DB_PREFIX.'config')->findOne();
        }
        catch (Exception $e)
        {
            $this->lucyError->add(array(
                'title'   => _('Database Error.'),
                'message' => _('Could not get site configuration.'),
                'object'  => $e,
                'file'    => __FILE__,
                'line'    => __LINE__,
                'sql'     => ORM::getLastQuery(),
            ));

            return false;
        }

        // Get navigation
        $links = $this->getNavigationLinks();
        if ($links === false)
        {
            return false;
        }

        // Display the header
        $this->displayTemplate('global', 'header', array(
            'language'      => 'en',
            'site_title'    => $config->name,
            'page_title'    => $this->pageLkup[$this->currentPage],
            'js_includes'   => '',
            'js_code'       => '',
            'css_includes'  => '',
            'links'         => $links,
        ));

        return true;
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
            $this->lucyError->add(array(
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
     * @return void
     */
    private function getThemeDirectory ()
    {
        return 'templates/default';
    }
}
