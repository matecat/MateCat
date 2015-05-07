<?php
/**
 *
 * Copyright 2005-2006 The Apache Software Foundation
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/* vim: set expandtab tabstop=3 shiftwidth=3: */

/**
 * A Stomp Connection
 *
 *
 * @package Stomp
 */
class StompException extends Exception
{
    protected $_details;
    
    /**
     * Constructor
     *
     * @param string $message Error message
     * @param int $code Error code
     * @param string $details Stomp server error details
     */
    public function __construct($message = null, $code = 0, $details = '')
    {
        $this->_details = $details;
        
        parent::__construct($message, $code);
    }
    
    /**
     * Stomp server error details
     *
     * @return string
     */
    public function getDetails()
    {
        return $this->_details;
    }
}
?>