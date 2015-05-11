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

require_once 'Stomp/Message.php';

/**
 * Message that contains a stream of uninterpreted bytes
 *
 * @package Stomp
 * @author Dejan Bosanac <dejan@nighttale.net>
 * @version $Revision: 23 $
 */
class Stomp_Message_Bytes extends Stomp_Message
{
    /**
     * Constructor
     *
     * @param string $body
     * @param array $headers
     */
    function __construct ($body, $headers = null)
    {
        $this->_init("SEND", $headers, $body);
        if ($this->headers == null) {
            $this->headers = array();
        }
        $this->headers['content-length'] = count($body);
    }
}
?>