<?php

namespace Bayeux\Api\Server;

use Bayeux\Api\Bayeux\Message;

/**
 * <p>Representation of a server side message.</p>
 *
 * @version $Revision: 1483 $ $Date: 2009-03-04 14:56:47 +0100 (Wed, 04 Mar 2009) $
 */
interface ServerMessage extends Message
{
    /**
     * @return a message associated with this message on the server. Typically
     * this is a meta message that the current message is being sent in response
     * to.
     */
    public function getAssociated();

    /**
     * @return true if the message is lazy and should not force the session's queue to be flushed
     */
    public function isLazy();
}
