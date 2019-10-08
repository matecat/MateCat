


function ParsedHash( hash ) {
    var split ;
    var actionSep = ',' ;
    var chunkSep = '-';
    var that = this ;
    var _obj = {};

    var processObject = function( obj ) {
        _obj = obj ;
    };

    var processString = function( hash ) {
        if ( hash.indexOf('#') == 0 ) hash = hash.substr(1);

        if ( hash.indexOf( actionSep ) != -1 ) {
            split = hash.split( actionSep );

            _obj.segmentId = split[0];
            _obj.action = split[1];
        } else {
            _obj.segmentId = hash ;
            _obj.action = null;
        }

        if ( _obj.segmentId.indexOf( chunkSep ) != -1 ) {
            split = hash.split( chunkSep );

            _obj.splittedSegmentId = split[0];
            _obj.chunkId = split[1];
        }
    }

    if (typeof hash === 'string') {
        processString( hash );
    } else {
        processObject( hash );
    }

    this.segmentId = _obj.segmentId ;
    this.action = _obj.action ;
    this.splittedSegmentId = _obj.splittedSegmentId ;
    this.chunkId = _obj.chunkId ;

    this.isComment = function() {
        return _obj.action == MBC.const.commentAction ;
    }

    this.toString = function() {
        var hash = '';
        if ( _obj.splittedSegmentId ) {
            hash = _obj.splittedSegmentId + chunkSep + _obj.chunkId ;
        } else {
            hash = _obj.segmentId ;
        }
        if ( _obj.action ) {
            hash = hash + actionSep + _obj.action ;
        }
        return hash ;
    }

    this.onlyActionRemoved = function( hash ) {
        var current = new ParsedHash( hash );
        var diff = this.toString().split( current.toString() );
        return MBC.enabled() && (diff[1] == actionSep + MBC.const.commentAction) ;
    }

    this.hashCleanupRequired = function() {
        return MBC.enabled() && this.isComment();
    }

    this.cleanupHash = function() {
        notifyModules();
        window.location.hash = UI.parsedHash.segmentId ;
    }

    var notifyModules = function() {
        MBC.enabled() && that.isComment() && MBC.setLastCommentHash( that );
    }
}

function setBrowserHistoryBehavior() {

    window.onpopstate = function(ev) {

        if ( UI.parsedHash.onlyActionRemoved( window.location.hash ) ) {
            return ;
        }

        UI.parsedHash = new ParsedHash( window.location.hash );

        if ( UI.parsedHash.hashCleanupRequired() ) {
            UI.parsedHash.cleanupHash();
        }

        function updateAppByPopState() {
            var segment = UI.getSegmentById( UI.parsedHash.segmentId );
            var currentSegment = SegmentStore.getCurrentSegment();
            if ( currentSegment.sid === UI.parsedHash.segmentId ) return;
            if ( segment.length ) {
                UI.gotoSegment( UI.parsedHash.segmentId );
            } else {
                if ($('section').length) {
                    UI.pointBackToSegment( UI.parsedHash.segmentId );
                }
            }
        }
        updateAppByPopState();

    };

    UI.parsedHash = new ParsedHash( window.location.hash );
    UI.parsedHash.hashCleanupRequired() && UI.parsedHash.cleanupHash();
}


function goodbye(e) {

    UI.clearStorage('contribution');

    if ( $( '#downloadProject' ).hasClass( 'disabled' ) || $( 'tr td a.downloading' ).length || $( '.popup-tm td.uploadfile.uploading' ).length ) {
        return say_goodbye( 'You have a pending operation. Are you sure you want to quit?' );
    }

    if ( UI.offline ) {
        if(UI.setTranslationTail.length) {
            return say_goodbye( 'You are working in offline mode. If you proceed to refresh you will lose all the pending translations. Do you want to proceed with the refresh ?' );
        }
    }


    //set dont_confirm_leave to 1 when you want the user to be able to leave without confirmation
    function say_goodbye( leave_message ){

        if ( typeof leave_message !== 'undefined' ) {
            if ( !e ) e = window.event;
            //e.cancelBubble is supported by IE - this will kill the bubbling process.
            e.cancelBubble = true;
            e.returnValue = leave_message;
            //e.stopPropagation works in Firefox.
            if ( e.stopPropagation ) {
                e.stopPropagation();
                e.preventDefault();
            }
            //return works for Chrome and Safari
            return leave_message;
        }

    }

}

















