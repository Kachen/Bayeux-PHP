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
use Bayeux\Common\IllegalStateException;

/**
 * <p>Reification of a {@link Channel#getId() channel id} with methods to test properties
 * and compare with other {@link ChannelId}s.</p>
 * <p>A {@link ChannelId} breaks the channel id into path segments so that, for example,
 * {@code /foo/bar} breaks into {@code ["foo","bar"]}.</p>
 * <p>{@link ChannelId} can be wild, when they end with one or two wild characters {@code "*"};
 * a {@link ChannelId} is shallow wild if it ends with one wild character (for example {@code /foo/bar/*})
 * and deep wild if it ends with two wild characters (for example {@code /foo/bar/**}).</p>
 */
class ChannelId
{
    const WILD = '*';
    const DEEPWILD = '**';

    private $_id;
    private $_segments = array();
    private $_wild;
    private $_wilds = array();
    private $_parent;

    public function __construct($id)
    {
        if (! is_string($id)) {
            // FIXME: correção
            throw new \Exception('Alterar para a exception correta de argumetno');
        }

        if (strlen($id) == 0 || $id[0] != '/' || '/' == $id) {
            throw new \InvalidArgumentException($id);
        }

        $id = rtrim(trim($id), '/');
        $this->_id = $id;
    }

    private function resolve()
    {
        if (!empty($this->_segments)) {
            return;
        }

        $segments = explode('/', ltrim($this->_id, '/'));
        if (count($segments) < 1) {
            throw new \InvalidArgumentException("Invalid channel id:" . $this);
        }

        $lastSegment = end($segments);

        $wild = 0;
        if (self::WILD == $lastSegment) {
            $wild = 1;
        } else if (self::DEEPWILD == $lastSegment) {
            $wild = 2;
        }

        $this->_wild = $wild;

        if ($wild > 0)
        {
            $this->_wilds = array();
        }
        else
        {
            $wilds = array();
            $b = '';
            $b = '/';
            for ($i = 0; $i < count($segments); ++$i)
            {
                if (count(trim($segments[$i])) == 0) {
                    throw new IllegalArgumentException("Invalid channel id:" . $this);
                }
                if ($i > 0) {
                    $b .= $segments[$i - 1] . '/';
                }
                $wilds[count($segments) - $i] = $b . "**";
            }
            $wilds[0] = $b . "*";
            $this->_wilds = $wilds;
        }

         $this->_parent = count($segments) == 1 ? null : substr($this->_id, 0, count($this->_id) - strlen($lastSegment) - 2);

        // Volatile write, other members will be visible as well
        $this->_segments = $segments;
    }

    public function getId() {
        return $this->_id;
    }

    /**
     * @return whether this {@code ChannelId} is either {@link #isShallowWild() shallow wild}
     *         or {@link #isDeepWild() deep wild}
     */
    public function isWild()
    {
        $this->resolve();
        return $this->_wild > 0;
    }

    /**
     * <p>Shallow wild {@code ChannelId}s end with a single wild character {@code "*"}
     * and {@link #matches(ChannelId) match} non wild channels with
     * the same {@link #depth() depth}.</p>
     * <p>Example: {@code /foo/*} matches {@code /foo/bar}, but not {@code /foo/bar/baz}.</p>
     *
     * @return whether this {@code ChannelId} is a shallow wild channel id
     */
    public function isShallowWild()
    {
        return $this->isWild() && ! $this->isDeepWild();
    }

    /**
     * <p>Deep wild {@code ChannelId}s end with a double wild character "**"
     * and {@link #matches(ChannelId) match} non wild channels with
     * the same or greater {@link #depth() depth}.</p>
     * <p>Example: {@code /foo/**} matches {@code /foo/bar} and {@code /foo/bar/baz}.</p>
     *
     * @return whether this {@code ChannelId} is a deep wild channel id
     */
    public function isDeepWild()
    {
        $this->resolve();
        return $this->_wild > 1;
    }

    public function isMeta()
    {
        return self::staticIsMeta($this->_id);
    }

    public function isService()
    {
        return self::staticIsService($this->_id);
    }

    /**
     * @return whether this {@code ChannelId} is neither {@link #isMeta() meta} nor {@link #isService() service}
     */
    public function isBroadcast()
    {
        return self::staticIsBroadcast($this->_id);
    }

    public function equals($obj)
    {
        if ($this === $obj) {
            return true;
        }

        if (! ($obj instanceof ChannelId)) {
            return false;
        }

        return $this->getId() == $obj->getId();
    }

    public function hashCode()
    {
        return sha1($this->_id);
    }

    /**
     * <p>Tests whether this {@code ChannelId} matches the given {@code ChannelId}.</p>
     * <p>If the given {@code ChannelId} is {@link #isWild() wild},
     * then it matches only if it is equal to this {@code ChannelId}.</p>
     * <p>If this {@code ChannelId} is non-wild,
     * then it matches only if it is equal to the given {@code ChannelId}.</p>
     * <p>Otherwise, this {@code ChannelId} is either shallow or deep wild, and
     * matches {@code ChannelId}s with the same number of equal segments (if it is
     * shallow wild), or {@code ChannelId}s with the same or a greater number of
     * equal segments (if it is deep wild).</p>
     *
     * @param channelId the channelId to match
     * @return true if this {@code ChannelId} matches the given {@code ChannelId}
     */
    public function matches(ChannelId $channelId)
    {
        $this->resolve();

        if ($channelId->isWild()) {
            return $this->equals($channelId);
        }

        switch ($this->_wild)
        {
            case 0:
            {
                return $this->equals($channelId);
            }
            case 1:
            {
                if ($channelId->depth() != $this->depth()) {
                    return false;
                }
                for ($i = $this->depth() - 1; $i-- > 0; ) {
                    if (! ($this->getSegment($i) == $channelId->getSegment($i))) {
                        return false;
                    }
                }
                return true;
            }
            case 2:
            {
                if ($channelId->depth() < $this->depth()) {
                    return false;
                }
                for ($i = $this->depth() - 1; $i-- > 0; ) {
                    if (! ($this->getSegment($i) == $channelId->getSegment($i))) {
                        return false;
                    }
                }
                return true;
            }
            default:
            {
                throw new IllegalStateException();
            }
        }
    }

    public function toString()
    {
        return $this->_id;
    }

    public function depth()
    {
        $this->resolve();
        return count($this->_segments);
    }

    public function isAncestorOf(ChannelId $id)
    {
        $this->resolve();
        if ($this->isWild() || $this->depth() >= $id->depth()) {
            return false;
        }

        for ($i = $this->depth(); $i-- > 0;)
        {
            if (! ($this->getSegment($i) == $id->getSegment($i))) {
                return false;
            }
        }

        return true;
    }

    public function isParentOf(ChannelId $id)
    {
        $this->resolve();
        if ($this->isWild() || $this->depth() != $id->depth()-1) {
            return false;
        }

        for ($i = $this->depth(); $i-- > 0; ) {
            if (! ($this->getSegment($i) == $id->getSegment($i))) {
                return false;
            }
        }

        return true;
    }

    public function getParent()
    {
        $this->resolve();
        return $this->_parent;
    }

    public function getSegment($i)
    {
        $this->resolve();
        if ($i > $this->depth()) {
            return null;
        }

        if (isset($this->_segments[$i])) {
            return $this->_segments[$i];
        }
        return null;
    }

    /**
     * @return The list of wilds channels that match this channel, or
     * the empty list if this channel is already wild.
     */
    public function getWilds()
    {
        $this->resolve();
        return $this->_wilds;
    }

    public static function staticIsMeta($channelId) {
        return self::_staticTypeSegment('meta', $channelId);
    }

    public static function staticIsService($channelId) {
        return self::_staticTypeSegment('service', $channelId);
    }

    /**
     * <p>Helper method to test if the string form of a {@code ChannelId}
     * represents a {@link #isBroadcast()} broadcast} {@code ChannelId}.</p>
     *
     * @param channelId the channel id to test
     * @return whether the given channel id is a broadcast channel id
     */
    public static function staticIsBroadcast($channelId)
    {
        return ! self::staticIsMeta($channelId) && ! self::staticIsService($channelId);
    }

    private static function _staticTypeSegment($segment, $channelId) {
        return $channelId != '' && stripos($channelId, "/{$segment}/") === 0;
    }
}