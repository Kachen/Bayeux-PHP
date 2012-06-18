<?php
/*
 * Copyright (c) 2010 the original author or authors.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace Bayeux\Api;

/**
 * <p>The {@link Bayeux} interface is the common API for both client-side and
 * server-side configuration and usage of the Bayeux object.</p>
 * <p>The {@link Bayeux} object handles configuration options and a set of
 * transports that is negotiated with the server.</p>
 * @see Transport
 */
interface Bayeux
{
    /**
     * @return the set of known transport names of this {@link Bayeux} object.
     * @see #getAllowedTransports()
     */
   public function getKnownTransportNames();

    /**
     * @param transport the transport name
     * @return the transport with the given name or null
     * if no such transport exist
     * @return Transport
     */
    public function getTransport($transport);

    /**
     * @return the ordered list of transport names that will be used in the
     * negotiation of transports with the other peer.
     * @see #getKnownTransportNames()
     */
    public function getAllowedTransports();

    /**
     * @param qualifiedName the configuration option name
     * @return the configuration option with the given {@code qualifiedName}
     * @see #setOption(String, Object)
     * @see #getOptionNames()
     */
    public function getOption($qualifiedName);

    /**
     * @param qualifiedName the configuration option name
     * @param value the configuration option value
     * @see #getOption(String)
     */
    public function setOption($qualifiedName, $value);

    /**
     * @return the set of configuration options
     * @see #getOption(String)
     */
    public function getOptionNames();

}
