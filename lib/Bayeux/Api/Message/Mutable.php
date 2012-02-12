<?php

namespace Bayeux\Api\Message;

use Bayeux\Api\Message;

/**
 * The mutable version of a {@link Message}
 */
interface Mutable extends Message
{
    /**
     * Convenience method to retrieve the {@link #ADVICE_FIELD} and create it if it does not exist
     * @param create whether to create the advice field if it does not exist
     * @return the advice of the message
     */
    //Map<String, Object> getAdvice(boolean create);
    public function getAdvice($create);

    /**
     * Convenience method to retrieve the {@link #DATA_FIELD} and create it if it does not exist
     * @param create whether to create the data field if it does not exist
     * @return the data of the message
     */
    //Map<String, Object> getDataAsMap(boolean create);
    public function getDataAsMap($create);

    /**
     * Convenience method to retrieve the {@link #EXT_FIELD} and create it if it does not exist
     * @param create whether to create the ext field if it does not exist
     * @return the ext of the message
     */
    //Map<String, Object> getExt(boolean create);
    public function getExt($create);

    /**
     * @param channel the channel of this message
     */
    public function setChannel($channel);

    /**
     * @param clientId the client id of this message
     */
    public function setClientId($clientId);

    /**
     * @param data the data of this message
     */
    public function setData($data);

    /**
     * @param id the id of this message
     */
    public function setId($id);

    /**
     * @param successful the successfulness of this message
     */
    public function setSuccessful($successful);

}
