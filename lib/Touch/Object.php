<?php

abstract class Touch_Object implements ArrayAccess {

    public function offsetSet($offset, $value) {
        if(isset($this->$offset)) {
            $this->$offset = $value;
        }
    }
    public function offsetExists($offset) {
        
        return isset($this->$offset);
    }
    public function offsetUnset($offset) {
        unset($this->$offset);
    }
    public function offsetGet($offset) {
        return isset($this->$offset) ? $this->$offset : null;
    }

    public function toArray()
    {
        return (array)$this;
    }
}