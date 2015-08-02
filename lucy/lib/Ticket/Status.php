<?php

namespace Ticket;

/**
 * Status
 * 
 * Creates a status object for you based on the db config
 * 
 * @package   Lucy
 * @copyright 2015 Haudenschilt LLC
 * @author    Ryan Haudenschilt <r.haudenschilt@gmail.com> 
 * @license   http://www.gnu.org/licenses/gpl-2.0.html
 */
class Status
{
    private $error;

    public static function build ()
    {
        // Get config
        try
        {
            $db = \ORM::get_db();

            $config = \ORM::forTable(DB_PREFIX.'config')->findOne();
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

        $statusClassName = $config->ticket_status_class;
        $statusClassName = "\\Ticket\\Status\\".$statusClassName;

        if (class_exists($statusClassName))
        {
            return new $statusClassName();
        }
        else
        {
            die('Could not find the Ticket Status class ['.$statusClassName.'].');
        }
    }
}
