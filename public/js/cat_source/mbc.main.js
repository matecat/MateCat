/*
 Component: mbc.main
 */

MBC = {
    enabled : function() {
        return ( config.comments_enabled && !!window.EventSource );
    }
}

if ( MBC.enabled() )
(function($,config,window,undefined,MBC) {

    SSE.init();

    MBC.const = {
        get commentAction() {
            return 'comment' ;
        }
    }

    var types = { sticky: 3, resolve: 2, comment: 1 };
    var source_pages = { revise: 2, translate: 1 };
    var loggedUserName = null ;
    var customUserName = null ;
    var lastCommentHash = null;

    var tpls ;

    var initConstants = function() {
        tpls = MBC.const.tpls ;
    }

    var db = {
        segments: {},
        history: {},
        refreshHistory : function() {
            var count = 0,
                comment ;
            for (var i in this.segments) {
                if (isNaN(i)) { continue; }

                for (var ii = this.segments[i].length - 1; ii >= 0 ; ii--) {
                    comment = this.segments[i][ii] ;

                    if (comment.thread_id == null) {
                        if (! this.history.hasOwnProperty(i) ) {
                            this.history[i] = [];
                        }
                        this.history[i].push( comment );
                        if (Number(comment.message_type) == types.comment) {
                            count++ ;
                        }
                    }
                    else { break; }
                }
            }
            return count;
        },

        resetSegments : function() {
            this.segments = {};
        },

        storeSegments : function(array) {
            for(var i = 0 ; i < array.length ; i++ ) {
                this.pushSegment(array[i]);
            }
        },

        pushSegment : function(data) {
            var s = Number(data.id_segment);

            if (typeof db.segments[s] === 'undefined') {
                db.segments[s] = [ data ];
            }
            else {
                db.segments[s].push( data );
            }
            if (Number(data.message_type) == types.resolve) {
                $(db.segments[s]).each(function(i,x) {
                    if (x.thread_id == null) {
                        x.thread_id = data.thread_id
                    }
                });
            }
        },

        getCommentsBySegment : function(s) {
            var s = Number(s) ;

            if (typeof this.segments[s] === 'undefined') {
                return [];
            } else {
                return this.segments[s];
            }
        },

        getCommentsCountBySegment : function(s) {
            var active = 0, total = 0 ;

            $(this.getCommentsBySegment(s)).each(function(i,x) {
                if (Number(x.message_type) == types.comment) {
                    if (null == x.thread_id) active++;
                    total++;
                }
            });
            return { active: active, total: total };
        }
    };

    var source = SSE.getSource('comments');

    source.addEventListener('message', function(e) {
        var message = new SSE.Message( JSON.parse(e.data) );
        if (message.isValid()) {
            $( document ).trigger(message.eventIdentifier, message );
        }
    }, false);

    source.addEventListener('open', function(e) {
        // TODO: handle event
    }, false);

    source.addEventListener('error', function(e) {
        if (e.readyState == EventSource.CLOSED) {
            // TODO: handle event
        }
    }, false);

    var getUsername = function() {
        if ( customUserName ) return customUserName ;
        if ( loggedUserName ) return loggedUserName ;
        return 'Anonymous';
    }

    var getSourcePage = function() {
        if ( config.isReview ) {
            return source_pages.revise ;
        }
        else {
            return source_pages.translate ;
        }
    }

    var limitNum = function(num) {
        if ( Number(num) > 99 ) return '+99';
        else return num ;
    }

    var buildFirstCommentHeader = function() {
        return $(tpls.firstCommentWrap) ; // .append($(tpls.insertCommentHeader));
    }

    var resolveCommentLinkIcon = function(el, comments_obj) {
        var root = $(el).find('.txt') ;
        root.hide();

        if ( comments_obj.total == 0 ) {
            root.append( $( tpls.commentIconHighlightInvite ) );
            return ;
        }

        root.find( '.mbc-comment-highlight-invite' ).remove();
        root.find('.mbc-comment-highlight').remove();

        var highlight = $(tpls.commentIconHighlightNumber) ;

        if ( comments_obj.active > 0 ) {
            root.append( highlight );
            highlight.text( comments_obj.active );
        }

        root.show();
    }

    var renderInputForm = function() {
        var inputForm = $( tpls.inputForm );
        inputForm.find('.mbc-new-message-notification').hide();
        inputForm.find('.mbc-comment-username-label')
            .toggleClass('mbc-comment-anonymous-label', !loggedUserName)
            .text( getUsername() );

        if (loggedUserName) {
            inputForm.find('.mbc-login-link').addClass('vis-hidden');
        } else {
            inputForm.find('.mbc-login-link').addClass('vis-visible');
            inputForm.find('.mbc-comment-username-label')
                    .attr('title', 'Click to edit');
        }
        inputForm.find('.mbc-comment-send-btn').hide();
        return inputForm ;
    }

    var renderSegmentCommentsFirstInput = function(el) {
        $('.mbc-comment-balloon-outer').remove();

        var root = $(tpls.segmentThread);
        var inputForm = renderInputForm() ;
        inputForm.addClass('mbc-first-input');

        root.find('.mbc-comment-balloon-inner').append( inputForm );
        el.append( root.show() );

        inputForm.find('textarea').focus();
    }

    var populateWithComments = function(root, comments) {
        var comments_root = root.find('.mbc-comments-wrap');

        if (comments.length == 0) {
            return root;
        }
        var thread_wrap = null, thread_id = 0, count = 0 ;

        for (var i = 0 ; i < comments.length ; i++) {
            if ( comments[i].thread_id != thread_id ) {
                // start a new thread
                if (thread_wrap != null) {
                    comments_root.append(thread_wrap);
                    count = 0 ;
                }
                thread_wrap = $(tpls.threadWrap) ;
            }
            if (Number(comments[i].message_type) == types.comment) {
                count++;
            }
            if (comments[i].thread_id == null) {
                thread_wrap.addClass('mbc-thread-wrap-active');
            }
            else {
                thread_wrap.addClass('mbc-thread-wrap-resolved');
            }
            thread_wrap.append( populateCommentTemplate(comments[i]) ) ;
            thread_wrap.data( 'count', count );

            thread_id = comments[i].thread_id ;
        }

        comments_root.append(thread_wrap);

        function threadIsResolved() {
            return comments[i-1].thread_id ;
        }

        var inputForm = renderInputForm();
        inputForm.addClass('mbc-reply-input');

        root.find('.mbc-comment-balloon-inner').append( inputForm ) ;

        // enableInputForm( root );

        // Append resolve button
        if ( !threadIsResolved() ) {
            root.find('.mbc-thread-wrap-active').append( $(tpls.resolveButton) );
        }

        return root;
    }

    window.Scrollable = function(el) {
        this.el = el ;
        var that = this ;
        var root = $(el).closest('.mbc-comment-balloon-outer') ;
        var notificationArea = $(root).find('.mbc-new-message-notification');
        var dataRoot = root.closest('section');

        this.bottomVisible = function() {
            return el.scrollTop + 30 >= el.scrollHeight - el.clientHeight ;
        }

        var verbalize = function(count) {
            if (count > 1)  return '' + count + ' new messages';
            else            return '1 new message';
        }

        this.notifyNewComments = function() {
            var count = dataRoot.data('mbc-new-comments-count') || 0 ;
            dataRoot.data( 'mbc-new-comments-count', ++ count );

            notificationArea.find('a').text( verbalize(count) );
            notificationArea.show();
        }

        this.scrollToBottom = function() {
            $(this.el).scrollTop( this.el.scrollHeight );
            notificationArea.hide();
            dataRoot.data('mbc-new-comments-count', 0) ;
        }
    }

    var renderSegmentComments = function(el) {
        $('.mbc-comment-balloon-outer').remove();

        var segment = new UI.Segment(el) ;
        var comments = db.getCommentsBySegment( segment.absoluteId );
        var root = $(tpls.segmentThread);

        populateWithComments(root, comments);

        el.append( root.show() );
    }

    var appendReceivedMessage = function(el) {
        var areaBefore = new Scrollable( $(el).find('.mbc-comments-wrap')[0]);
        var scrollTop = areaBefore.el.scrollTop ;
        var scrollableArea ;

        if (! areaBefore.bottomVisible()) {
            renderSegmentComments(el);
            scrollableArea = new Scrollable( $(el).find('.mbc-comments-wrap')[0]);
            el ; /// XXX seems to be required to get actual scrollTop number
            $(scrollableArea.el).scrollTop(scrollTop);
            scrollableArea.notifyNewComments();
        } else {
            renderSegmentComments(el);
            scrollableArea = new Scrollable( $(el).find('.mbc-comments-wrap')[0]);
            scrollableArea.scrollToBottom() ;
        }
    }

    var appendSubmittedMessage = function(el) {
        renderSegmentComments(el);
        var scrollableArea = new Scrollable( el.find('.mbc-comments-wrap')[0]);
        scrollableArea.scrollToBottom();
    }

    var renderSegmentBalloon = function(el) {
        if (! $(el).is(':visible') ) return ;

        var segment = new UI.Segment(el) ;
        var comments = db.getCommentsBySegment( segment.absoluteId );
        if (comments.length > 0) {
            renderSegmentComments(el);
            var scrollableArea = new Scrollable( el.find('.mbc-comments-wrap')[0]);
            scrollableArea.scrollToBottom();
        } else {
            renderSegmentCommentsFirstInput(el);
        }
    }

    var openSegmentComment = function(el) {
        $('article').addClass('mbc-commenting-opened');
        renderSegmentBalloon(el);
        UI.scrollSegment(el);
    }

    var closeSegment = function(el) {
        $('.mbc-comment-balloon-outer').remove();
        $('article').removeClass('mbc-commenting-opened');
    }

    var renderCommentIconLinks = function() {
        var segment ;
        $('section').each(function(i, el) {
            segment = new UI.Segment( el ) ;

            if ( (! segment.isSplit()) || segment.isFirstOfSplit()) {
                $(document).trigger('mbc:segment:update:links', segment.absoluteId );
            }
        });
    }

    var populateCommentTemplate = function(data) {
        if (Number(data.message_type) == types.resolve) {
            var root = $(tpls.showResolve)
            root.find('.mbc-comment-username-label').text( htmlDecode(data.full_name) );
        } else {
            var root = $(tpls.showComment) ;
            root.find('.mbc-comment-username-label').text( htmlDecode(data.full_name) );
            root.find('.mbc-comment-time').text( data.formatted_date );
            root.find('.mbc-comment-body').html( nl2br( data.message ) );
            if ( data.email != null ) {
                root.find('.mbc-comment-email-label').text( data.email );
            }
        }
        return root ;
    }

    var nl2br = function(str, is_xhtml) {
        var breakTag = (is_xhtml || typeof is_xhtml === 'undefined') ? '<br />' : '<br>';
        return (str + '').replace(/([^>\r\n]?)(\r\n|\n\r|\r|\n)/g, '$1'+ breakTag +'$2');
    }

    var nothingToSubmit = function() {
       return $.trim($('.mbc-comment-textarea').val()) == '';
    }

    var ajaxResolveSuccess = function(resp) {
        db.pushSegment(resp.data.entries[0]);
        $(document).trigger('mbc:comment:new', resp.data.entries[0]);
    }

    var refreshUserInfo = function(user) {
        if (typeof user === 'undefined') {
            loggedUserName = null;
        } else  {
            loggedUserName = user.full_name ;
        }
    }

    var renderHistoryWithNoComments = function() {
        $('.mbc-history-balloon-has-comment').remove();
        $('.mbc-history-balloon-has-no-comments').show();
        $('.mbc-comment-highlight-history').removeClass('visible');
    }

    var renderHistoryWithComments = function( count ) {
        var root = $(tpls.historyHasComments);

        for (var i in db.history) {
            if (isNaN(i)) { continue ; }

            var sid = db.history[i][0].id_segment ;
            var viewButton = $( tpls.historyViewButton );

            viewButton.find('a').text('View') ;
            viewButton.find('.mbc-comment-segment-number').text(sid);
            viewButton.attr('data-id', sid);

            var line = populateCommentTemplate( db.history[i][0] ) ;

            var number = $( tpls.activeCommentsNumberInHistory);
            number.text( db.history[i].length );

            line.find('.mbc-comment-info-wrap').append( number );
            line.append( viewButton ) ;

            root.append(
                $(tpls.threadWrap).append( line )
            );
        }
        $('.mbc-history-balloon-has-comment').remove();
        $('.mbc-history-balloon-has-no-comments').hide();

        $('.mbc-history-balloon-outer').append(root);

        $('#mbc-history .mbc-comment-highlight-history').text( limitNum(count) ).addClass( 'visible' );
    }

    var updateHistoryWithLoadedSegments = function() {
        db.history = {};
        var count = db.refreshHistory();

        if (count == 0) {
            renderHistoryWithNoComments();
        } else {
            renderHistoryWithComments( count );
        }
    }

    var submitComment = function(el) {
        if ( nothingToSubmit() ) return;

        var segment = new UI.Segment( el );

        var data = {
            action     : 'comment',
            _sub       : 'create',
            id_client  : config.id_client,
            id_job     : config.job_id,
            id_segment : segment.absoluteId,
            username   : getUsername(),
            password   : config.password,
            source_page  : getSourcePage(),
            message    : el.find('.mbc-comment-textarea').val(),
        }

        $('.mbc-comment-textarea').attr('disabled', 'disabled');

        clearGenericWarning();

        APP.doRequest({
            data: data,
            success : function(resp) {
                if (resp.errors.length) {
                    showGenericWarning();
                } else {
                    $(document).trigger('mbc:comment:saved', resp.data.entries[0]);
                }
            },
            error : function() {
                showGenericWarning();
            },
            always : function() {
                $('.mbc-comment-textarea').removeAttr('disabled');
            }
        });
    }

    var showGenericWarning = function() {
        $('.mbc-ajax-message-wrap').show();
    }

    var clearGenericWarning = function() {
        $('.mbc-ajax-message-wrap').hide();
    }

    function enableInputForm( outer ) {
        outer.find('.mbc-post-comment .mbc-comment-username-label')
            .toggleClass('mbc-comment-anonymous-label', !loggedUserName)
            .text( getUsername() ) ;

        if ( loggedUserName ) outer.find('.mbc-post-comment .mbc-login-link').addClass('vis-hidden');
        else outer.find('.mbc-post-comment .mbc-login-link').addClass('vis-visible');
    }

    // start event binding

    $(document).on('ready', function() {
        initConstants();

        // XXX: there'a binding on 'section' are delegated to #outer in ui.events.js.
        //      Since our DOM elements are children of `section` we must attach to #outer
        //      too in order to prevent bubbling.
        //
        var delegate = '#outer';


        $(delegate).on('click', '.mbc-comment-balloon-outer, .mbc-comment-link div', function(e) {
            e.stopPropagation();
        });

        $(delegate).on('click', '.mbc-comment-link .txt', function(e) {
            var section = $(e.target).closest('section');
            openSegmentComment( section );
        });

        $(delegate).on('click', 'section .mbc-close-btn', function(e) {
            e.preventDefault();
            closeSegment( $(e.target).closest('section') );
        });

        $(delegate).on('click', '.mbc-comment-send-btn', function(e) {
            e.preventDefault();
            submitComment( $(e.target).closest('section') );
        });

        $(delegate).on('click', '.mbc-comment-resolve-btn', function(e) {
            e.preventDefault();
            clearGenericWarning();

            var segment = new UI.Segment( $(e.target).closest('section') );

            var data = {
                action      : 'comment',
                _sub        : 'resolve',
                id_job      : config.job_id,
                id_client   : config.id_client,
                id_segment  : segment.absoluteId,
                password    : config.password,
                source_page : getSourcePage(),
                username    : getUsername(),
            }

            APP.doRequest({
                data: data,
                success : ajaxResolveSuccess,
                error : function() {
                    showGenericWarning();
                }
            });
        });

        $(delegate).on('click', '.show-thread-btn', function(e) {
            e.preventDefault();
            var el = $(e.target).closest('a');
            var panel = el.siblings('.thread-collapsed');
            var showlabel = $(el).find('.show-thread-label');
            var showIconlabel = $(el).find('.show-toggle-icon');

            if (panel.is(':visible')) {
                $(showlabel).text( 'Show more' );
                $(showIconlabel).text( '+' );
            } else {
                $(showlabel).text( 'Show less' );
                $(showIconlabel).text( '-' );
            }

            panel.stop().slideToggle();
        });

        $(delegate).on('click', '.mbc-login-link', function(e) {
            $('.login-google').show();
        });

        $(delegate).on('click', '.mbc-comment-anonymous-label', function() {
            var elem = $(this);
            var replaceWith = $('<input name="customName" type="text" class="mbc-comment-input mbc-comment-textinput" />')
                .val( getUsername() );
            var action = function() {
                var tmpval = $.trim(htmlDecode($(this).val())) ;
                if ( tmpval == "" ) {
                    customUserName = null;
                } else {
                    customUserName = tmpval ;
                    elem.text( customUserName ) ;
                }
                $(this).remove();
                elem.text( getUsername() ).show();
            }

            elem.hide().after(replaceWith);

            replaceWith.blur(action).keypress(function(ev) {
                if (ev.which == 13) { action.call(ev.target); }
            }).focus();
        });

        $(delegate).on('click', '.mbc-new-message-link', function(e) {
            e.preventDefault();
            var root = $(e.target).closest('.mbc-comment-balloon-outer') ;
            var scrollableArea = new Scrollable(root.find('.mbc-comments-wrap')[0]);
            scrollableArea.scrollToBottom();
        });
    });

    $(document).on('click', '.mbc-history-balloon-outer .mbc-close-btn', function(e) {
        e.preventDefault();
        $(e.target).closest('.mbc-history-balloon-outer').toggleClass('visible');
    });

    $(document).on('click', '.mbc-show-comment-btn', function(e) {
        e.preventDefault();
        $('.mbc-history-balloon-outer').removeClass('visible');

        var sid = $(e.target).closest('div').data('id') ;

        var new_hash = new ParsedHash({
            segmentId : sid,
            action : MBC.const.commentAction
        }).toString();

        window.location.hash = new_hash ;
    });


    $(document).on('getSegments_success', function(e) {
        var data = {
            action    : 'comment',
            _sub      : 'getRange',
            id_job    : config.job_id,
            first_seg : UI.getSegmentId( UI.firstSegment ),
            last_seg  : UI.getSegmentId( UI.lastSegment ),
            password  : config.password
        }

        APP.doRequest({
            data: data,
            success : function(resp) {
                db.resetSegments();
                db.storeSegments( resp.data.entries.current_comments );
                db.storeSegments( resp.data.entries.open_comments );
                refreshUserInfo( resp.data.user ) ;

                $(document).trigger('mbc:ready');
            },
            error : function() {
                // TODO: handle error on comments fetch
            }
        });
    });

    var initCommentLinks = function() {
        var section ;
        $('section').each(function(i, el) {
            section = new UI.Segment( el ) ;
            if ((! section.isSplit()) || section.isFirstOfSplit()) {
                $(el).append( $(tpls.commentLink) );
            }
        });
    }

    $(document).on('mbc:ready', function(ev) {
        $('.header-menu li:last-child').before($(tpls.historyIcon));
        $('.header-menu').append($(tpls.historyOuter).append($(tpls.historyNoComments)));

        initCommentLinks();
        renderCommentIconLinks();
        updateHistoryWithLoadedSegments();
    });

    $(document).on('sse:ack', function(ev, message) {
        config.id_client = message.data.clientId;
    });

    $(document).on('sse:comment', function(ev, message) {
        db.pushSegment( message.data ) ;
        $(document).trigger('mbc:comment:new', message.data);
    });

    $(document).on('click', '#filterSwitch', function(e) {
        $('.mbc-history-balloon-outer').removeClass('visible');
    });

    $(document).on('click', '#mbc-history', function(ev) {
        $('.mbc-history-balloon-outer').toggleClass('visible');
        if ($('.searchbox').is(':visible')) {
            UI.toggleSearch(ev) ;
        }
    });

    $(document).on('mbc:comment:new', function(ev, data) {
        updateHistoryWithLoadedSegments();
        $(document).trigger('mbc:segment:update:links', data.id_segment);

        var section = UI.Segment.findEl( data.id_segment ) ;
        if ( $('section .mbc-thread-wrap').is(':visible') ) {
            appendReceivedMessage( section );
        }
    });

    $(document).on('mbc:comment:saved', function(ev, data) {
        $(document).find('section .mbc-thread-wrap').remove();
        db.pushSegment(data); // TODO: move this in ajax success?
        updateHistoryWithLoadedSegments();

        $(document).trigger('mbc:segment:update:links', data.id_segment);
        appendSubmittedMessage( UI.Segment.findEl( data.id_segment ) );
    });

    $(window).on('segmentClosed', function(e) {
        closeSegment($(e.segment));
    });

    $(window).on('segmentOpened', function(e) {
        var segment = new UI.Segment($(e.segment)) ;

        if ( MBC.wasAskedByCommentHash( segment.absoluteId ) ) {
            openSegmentComment( $(e.segment) );
        }
    });

    $(document).on('mbc:segment:update:links', function(ev, id_segment) {
        var comments_obj = db.getCommentsCountBySegment( id_segment ) ;
        var el = UI.Segment.findEl(id_segment) ;
        resolveCommentLinkIcon( el.find('.mbc-comment-link'), comments_obj );
    });

    $(document).ready(function(){
        // load for history
    });

    $(document).on('keyup', '.mbc-comment-textarea', function(e) {
        var maxHeight = 100 ;
        var borderTopWidth = parseFloat( $(this).css("borderTopWidth") ) ;
        var borderBottomWidth = parseFloat( $(this).css("borderBottomWidth") ) ;
        var maxOuterHeight = this.scrollHeight + borderTopWidth + borderBottomWidth ;

        var minHeight = 34 ;

        while( $(this).height() < maxHeight && $(this).outerHeight() <  maxOuterHeight ) {
                $(this).height( $(this).height() + 10 );
            };

        if ( $(this).height() >= maxHeight ) {
            $(this).css("overflow-y", "auto");
        } else {
            $(this).css("overflow-y", "hidden");
        }
    });

    $(document).on('focus', '.mbc-comment-input', function(e) {
        $(e.target).closest('div').find('.mbc-comment-btn').show();
    });

    $(document).on('click', function(e) {
        $('.mbc-comment-balloon-outer').find('.mbc-comment-send-btn').hide();
    });

    $(document).on('ui:segment:focus', function(e, sid) {
        if ( lastCommentHash && lastCommentHash.segmentId == sid ) {
            openSegmentComment( UI.Segment.findEl( sid ) );
            lastCommentHash = null ;
        }
    });

    $(document).on('mouseover', 'section', function(e) {
        $(e.relatedTarget).closest('section').find('.mbc-comment-link .txt').stop();

        $(e.target).closest('section')
            .find('.mbc-comment-link .txt:has(.mbc-comment-highlight-invite)')
            .stop().delay( 300 ).fadeIn( 100 );
    });

    $(document).on('mouseout', 'section', function(e) {
        $(e.relatedTarget).closest('section').find('.mbc-comment-link .txt').stop();

        $(e.target).closest('section')
            .find('.mbc-comment-link .txt:has(.mbc-comment-highlight-invite)')
            .stop().delay( 150 ).fadeOut(  );
    });

    // Interfaces
    $.extend(MBC,  {
        popLastCommentHash : function() { // TODO: remove this, no longer needed since ParsedHash
            var l = lastCommentHash ;
            lastCommentHash = null;
            return l;
        },
        wasAskedByCommentHash: function( sid ) {
            return lastCommentHash && lastCommentHash.segmentId == sid;
        },
        setLastCommentHash : function(value) {
            lastCommentHash = value ;
        }
    });

})(jQuery, config, window, undefined, MBC);
