<?php

namespace Bayeux\Server;

use Bayeux\Api\Server\ServerMessage;

class ServerMessageImpl extends \ArrayObject implements ServerMessage\Mutable
{
    private $_associated;
    private $_lazy = false;
    private $_json;

    public function getAssociated()
    {
        return $this->_associated;
    }

    public function setAssociated(ServerMessage\Mutable $associated)
    {
        $this->_associated = $associated;
    }

    public function isLazy()
    {
        return $this->_lazy;
    }

    public function setLazy($lazy)
    {
        $this->_lazy = $lazy;
    }

    public function freeze()
    {
        //assert _json == null;
        $this->_json = $this->getJSON();
    }

    //@Override
    public function getJSON()
    {
        if ($this->_json == null) {
            return parent::getJSON();
        }
        return $this->_json;
    }

    //@Override
    public function getData()
    {
        $data = parent::getData();
        if ($this->_json != null && $data instanceof Map)
        return $data;
    }

    //@Override
    public function put($key, $value)
    {
        if ($this->_json != null) {
            throw new UnsupportedOperationException();
        }
        return parent::put($key, $value);
    }

    //@Override
    public function entrySet()
    {
        if ($this->_json != null)
        return new ImmutableEntrySet(parent::entrySet());
        return parent::sentrySet();
    }

    //@Override
    public function getDataAsMap($create = null)
    {
        $data = parent::getDataAsMap();
        if ($this->_json != null && $data != null)
        return $data;
    }

    //@Override
    public function getExt()
    {
        $ext = parent::getExt();
        return $ext;
    }

    //@Override
    public function getAdvice($create = null)
    {
        $advice = parent::getAdvice();
        return $advice;
    }

    public static function parseServerMessages(Reader $reader, $jsonDebug) //throws ParseException, IOException
    {
        var_dump('parseServerMessages');
        exit;
        if ($jsonDebug) {
            return parseServerMessages(IO.toString(reader));
        }

        try
        {
            $batch = $this->serverMessagesParser.parse(new JSON.ReaderSource(reader));
            if ($batch == null) {
                return array();
            }
            if (batch.getClass()->isArray()) {
                return array($batch);
            }
            return array($batch);
        }
        catch (Exception $x)
        {
            throw new ParseException("", -1).initCause(x);
        }
    }

/*    public static function parseServerMessages($s) // throws ParseException
    {
        var_dump('parseServerMessages');
        exit;
         try
        {
            Object batch = serverMessagesParser.parse(new JSON.StringSource(s));
            if (batch == null)
            return new ServerMessage.Mutable[0];
            if (batch.getClass().isArray())
            return (ServerMessage.Mutable[])batch;
            return new ServerMessage.Mutable[]{
                (ServerMessage.Mutable)batch};
        }
        catch (Exception x)
        {
            throw (ParseException)new ParseException(s, -1).initCause(x);
        }
    }
*/
}

/*
 static class ImmutableEntrySet
{
private final Set<Map.Entry<String, Object>> delegate;

private ImmutableEntrySet(Set<Map.Entry<String, Object>> delegate)
{
this.delegate = delegate;
}

@Override
public Iterator<Map.Entry<String, Object>> iterator()
{
return new ImmutableEntryIterator(delegate.iterator());
}

@Override
public int size()
{
return delegate.size();
}

}



private static class ImmutableEntryIterator implements Iterator<Map.Entry<String, Object>>
{
private final Iterator<Map.Entry<String, Object>> delegate;

private ImmutableEntryIterator(Iterator<Map.Entry<String, Object>> delegate)
{
this.delegate = delegate;
}

public boolean hasNext()
{
return delegate.hasNext();
}

public Map.Entry<String, Object> next()
{
return new ImmutableEntry(delegate.next());
}

public void remove()
{
throw new UnsupportedOperationException();
}

private static class ImmutableEntry implements Map.Entry<String, Object>
{
private final Map.Entry<String, Object> delegate;

private ImmutableEntry(Map.Entry<String, Object> delegate)
{
this.delegate = delegate;
}

public String getKey()
{
return delegate.getKey();
}

public Object getValue()
{
return delegate.getValue();
}

public Object setValue(Object value)
{
throw new UnsupportedOperationException();
}
}
} */