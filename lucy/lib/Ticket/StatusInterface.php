<?php

namespace Ticket;

/**
 * StatusInterface
 * 
 * @package   Lucy
 * @copyright 2015 Haudenschilt LLC
 * @author    Ryan Haudenschilt <r.haudenschilt@gmail.com> 
 * @license   http://www.gnu.org/licenses/gpl-2.0.html
 */
interface StatusInterface
{
    public function getNextStatuses ($currentStatus = null);
    public function getOpenStatuses ();
    public function getClosedStatuses ();

    public function wasTicketAccepted ($ticket, $newData);
    public function wasTicketResolvedByUser ($ticket, $newData, $userId);
}
