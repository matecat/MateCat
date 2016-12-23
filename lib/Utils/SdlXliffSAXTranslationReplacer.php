<?php

/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 18/09/15
 * Time: 12.23
 *
 */
class SdlXliffSAXTranslationReplacer extends XliffSAXTranslationReplacer {

    protected $isTargetDefinition = false;
    protected $markerPos = "";

    /*
       callback for tag open event
     */
    protected function tagOpen( $parser, $name, $attr ) {

        //check if we are entering into a <trans-unit>
        if ( 'trans-unit' == $name ) {
            $this->inTU = true;
            //get id
            $this->currentId = $attr[ 'id' ];
        }

        //check if we are entering into a <target>
        if ( 'target' == $name ) {
            $this->inTarget = true;
        }

        //reset Marker positions
        if( 'sdl:seg-defs' == $name ){
            $this->markerPos = 0;
        }

        //check if we are inside a <target>, obviously this happen only if there are targets inside the trans-unit
        //<target> must be stripped to be replaced, so this check avoids <target> reconstruction
        if ( !$this->inTarget ) {

            //costruct tag
            $tag = "<$name ";

            //needed to avoid multiple conf writing inside the same tag
            //because the "conf" attribute could be not present in the tag,
            // so the check on it's name is not enough
            $_sdlStatus_confWritten = false;

            foreach ( $attr as $k => $v ) {

                //if tag name is file, we must replace the target-language attribute
                if ( $name == 'file' && $k == 'target-language' && !empty( $this->target_lang ) ) {
                    //replace Target language with job language provided from constructor
                    $tag .= "$k=\"$this->target_lang\" ";
                    //Log::doLog($k . " => " . $this->target_lang);
                } elseif ( 'sdl:seg' == $name ) {

                    //write the confidence level for this segment ( Translated, Draft, etc. )
                    if ( isset( $this->segments[ 'matecat|' . $this->currentId ] ) && $_sdlStatus_confWritten == false ) {

                            //append definition attribute
                            $tag .= $this->prepareTargetStatuses( $this->lastTransUnit[ $this->markerPos ] );

                            //prepare for an eventual next cycle
                            $this->markerPos++;
                            $_sdlStatus_confWritten = true;

                    }

                    //Warning, this is NOT an elseif
                    if( $k != 'conf' ){
                        //put also the current attribute in it if it is not a "conf" attribute
                        $tag .= "$k=\"$v\" ";
                    }

                } else {
                    //normal tag flux, put attributes in it
                    $tag .= "$k=\"$v\" ";
                }

            }

            //this logic helps detecting empty tags
            //get current position of SAX pointer in all the stream of data is has read so far:
            //it points at the end of current tag
            $idx = xml_get_current_byte_index( $parser );

            //check whether the bounds of current tag are entirely in current buffer or the end of the current tag
            //is outside current buffer (in the latter case, it's in next buffer to be read by the while loop);
            //this check is necessary because we may have truncated a tag in half with current read,
            //and the other half may be encountered in the next buffer it will be passed
            if ( isset( $this->currentBuffer[ $idx - $this->offset ] ) ) {
                //if this tag entire lenght fitted in the buffer, the last char must be the last
                //symbol before the '>'; if it's an empty tag, it is assumed that it's a '/'
                $tmp_offset = $idx - $this->offset;
                $lastChar   = $this->currentBuffer[ $idx - $this->offset ];
            } else {
                //if it's out, simple use the last character of the chunk
                $tmp_offset = $this->len - 1;
                $lastChar   = $this->currentBuffer[ $this->len - 1 ];
            }

            //trim last space
            $tag = rtrim( $tag );

            //detect empty tag
            $this->isEmpty = ( $lastChar == '/' || $name == 'x' );
            if ( $this->isEmpty ) {
                $tag .= '/';
            }

            //add tag ending
            $tag .= ">";

            //seta a Buffer for the segSource Source tag
            if ( 'source' == $name
                    || 'seg-source' == $name
                    || $this->bufferIsActive
                    || 'value' == $name
                    || 'bpt' == $name
                    || 'ept' == $name
                    || 'ph' == $name
                    || 'st' == $name ) {

                //WARNING BECAUSE SOURCE AND SEG-SOURCE TAGS CAN BE EMPTY IN SOME CASES!!!!!
                //so check for isEmpty also in conjunction with name
                if( $this->isEmpty && ( 'source' == $name || 'seg-source' == $name ) ) {
                    $this->postProcAndFlush( $this->outputFP, $tag );

                } else {
                    //these are NOT source/seg-source/value empty tags, THERE IS A CONTENT, write it in buffer
                    $this->bufferIsActive = true;
                    $this->CDATABuffer .= $tag;
                }

            } else {
                $this->postProcAndFlush( $this->outputFP, $tag );
            }

        }

    }

    protected function prepareTargetStatuses( $segment ){

        $statusMap = array(
                'NEW'        => '',
                'DRAFT'      => 'Draft',
                'TRANSLATED' => 'Translated',
                'APPROVED'   => 'ApprovedTranslation',
                'REJECTED'   => 'RejectedTranslation',
        );

        return "conf=\"{$statusMap[ $segment[ 'status' ] ]}\" ";

    }

}