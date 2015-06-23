/*
 Component: ui.comment
 */

(function($,config,window,undefined) {
    if ( config.commentEnabled && !!window.EventSource ) {

        SSE.init();

        var types = { sticky: 3, resolve: 2, comment: 1 };
        var roles = { revisor: 2, translator: 1 };
        var openCommentsOnSegmentOpen = false;
        var lastResolvedSegment ;
        var currentGoogleUser ;

        // TODO: Make this private
        window.db = {
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
                var s = data.id_segment ;
                if (typeof db.segments[s] == 'undefined') {
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
                if (typeof this.segments[s] == 'undefined') {
                    return [];
                } else {
                    return this.segments[s];
                }
            },

            getCommentsCountBySegment : function(s) {
                return $(this.getCommentsBySegment(s)).filter(function(i,x) {
                    return Number(x.message_type) == types.comment;
                }).length ;
            }
        };

        window.tpls = { // TODO: make this local
            threadCollapsedControl : '' +
            ' <a href="javascript:" class="show-thread-btn">' +
            ' <span class="show-thread-label">Show more</span> ' +
            ' <span class="show-thread-number">(1)</span> ' +
            ' <span class="show-toggle-icon">+</span>' +
            ' </a>' +
            ' <div class="divider"></div>',

            threadCollapsedWrapper : '' +
                '<div class="thread-collapsed hide"></div>',

            insertCommentHeader : '' +
                '<div class="mbc-first-comment-header">' +
                ' <span class="mbc-comment-label mbc-first-comment-label">Insert a comment</span>' +
                ' <div class="divider"></div>' +
                ' </div>',

            reopenThread : ''+
                ' <div class="mbc-thread-wrap mbc-thread-wrap-active mbc-thread-number">' +
                ' <a href="#" class="mbc-comment-btn mbc-ask-btn mbc-show-form-btn">Ask new question</a>' +
                ' <div class="mbc-ask-comment-wrap hide">' +
                '' + // insertCommentHeader
                '' + // insert inputForm here
                ' </div>' +
                ' </div>',

            resolveButton : ''+
                ' <a href="#" class="mbc-comment-label mbc-comment-btn mbc-comment-resolve-btn">Resolve</a>',

            replyToComment : '' +
                ' <div><a href="#" class="mbc-comment-btn mbc-show-form-btn">Reply</a></div>' +
                '' , // insert inputForm here (with hide class)

            historyIcon : '' +
                '  <li id="mbc-history" title="View notifications"> ' +
                '      <span class="icon-bubble2"></span> ' +
                '      <span class="mbc-comment-highlight mbc-comment-highlight-history hide"></span> ' +
                '  </li>',

            historyOuter : '' +
                ' <div class="mbc-history-balloon-outer hide"> ' +
                '     <div class="triangle triangle-top"></div> ' +
                ' </div> ',

            historyViewButton: '' +
                ' <div><a href="javascript:;" class="mbc-comment-btn mbc-show-comment-btn">View</a></div>  ',

            historyHasComments: '' +
                ' <div class="mbc-history-balloon mbc-history-balloon-has-comment mbc-history-balloon-perfectscrollbar">' +
                ' <a href="#" class="mbc-close-btn">&#10005;</a> ' +
                ' ' + // showComment loop here
                ' </div> ',

            historyNoComments : '' +
                '<div class="mbc-history-balloon mbc-history-balloon-has-no-comments" style="display: block;">' +
                '  <a href="#" class="mbc-close-btn">&#10005;</a> ' +
                '    <div class="mbc-thread-wrap mbc-thread-number"> ' +
                '        <span class="mbc-comment-label">No notifications</span>'  +
                '    </div> ' +
                ' </div>',

            divider : '' +
                '<div class="divider"></div>',

            showResolve : '' +
                '<div class="mbc-show-comment">' +
                ' <span class="mbc-comment-label mbc-comment-username-label mbc-comment-resolvedby mbc-truncate">Anonymous</span>' +
                ' <span class="mbc-comment-resolved-label">resolved</span>' +
                ' </div>' ,

            threadWrap : '' +
                ' <div class="mbc-thread-wrap mbc-thread-number">'  +
                '' + // comments go here
                ' </div>',

            showComment : '' +
                '<div class="mbc-show-comment">' +
                ' <span class="mbc-comment-label mbc-comment-username-label"></span>' +
                ' <div class="mbc-comment-info-wrap mbc-clearfix">' +
                ' <span class="mbc-comment-info mbc-comment-time"></span>' +
                ' <span class="mbc-comment-info mbc-comment-role"></span>' +
                ' </div>' +
                ' <p class="mbc-comment-body"></p>' +
                ' <div class="divider"></div>' +
                ' </div>' ,

            inputForm : '' +
                ' <div class="mbc-post-comment">' +
                ' <span class="mbc-comment-label mbc-comment-username-label">Anonymous</span>' +
                ' <textarea class="mbc-comment-textarea"></textarea>' +
                ' <div>' +
                ' <a href="#" class="mbc-comment-btn mbc-comment-send-btn">Send</a>' +
                ' <a href="javascript:" class="mbc-login">Login to be notified</a>' +
                ' </div>' +
                ' </div>',

            inputFirstComment : '' +
                ' <div class="mbc-thread-wrap mbc-thread-wrap-active mbc-thread-number">' +
                '' + // insert inputForm here
                ' </div>',

            firstCommentHeader : '' +
                ' <div class="mbc-thread-wrap mbc-thread-wrap-active mbc-thread-number">' +
                '' + // insertCommentHeader
                '</div>',

            segmentThread : '' +
                ' <div class="mbc-comment-balloon-outer mbc-thread-active mbc-thread-active-first-comment">' +
                ' <div class="triangle triangle-topleft"></div>' +
                '<a href="#" class="mbc-close-btn">&#10005;</a>' +
                ' ' +
                ' ' +
                ' </div>',

            commentLink : '' +
                '<div class="mbc-comment-link">' +
                ' <div class="txt">' +
                ' <span class="mbc-comment-total"></span>' +
                ' <span class="mbc-comment-icon icon-bubbles4"></span>' +
                ' <span class="mbc-comment-highlight mbc-comment-total-none"></span>' +
                '</div>' +
                '</div>',
        }

        var source = SSE.getSource('comments');

        source.addEventListener('message', function(e) {
            var message = new SSE.Message( JSON.parse(e.data) );
            if (message.isValid()) {
                $( document ).trigger(message.eventIdentifier, message );
            }
        }, false);

        source.addEventListener('error', function(e) {
            if (e.readyState == EventSource.CLOSED) {
                console.log('connection closed');
            }
        }, false);

        var decodeRole = function(role) {
            if (Number(role) == roles.translator) {
                return 'translator';
            } else {
                return 'revisor' ;
            }
        }

        var getUsername = function() {
            return 'John Doe';
        }

        var getRole = function() {
            if (window.location.pathname.split('/')[1] == 'revise') {
                return roles.revisor ;
            }
            else {
                return roles.translator ;
            }
        }

        var buildFirstCommentHeader = function() {
            return $(tpls.firstCommentHeader).append($(tpls.insertCommentHeader)) ;
        }

        var renderSegmentCommentsFirstInput = function(el) {
            var root = $(tpls.segmentThread);
            var inputFirstComment = $(tpls.inputFirstComment);
            root
                .append(buildFirstCommentHeader())
                .append(inputFirstComment.append($(tpls.inputForm)));
            el.append( root.show() );
        }

        var applyCollapsedThreadWrap = function(root) {
            if (lastResolvedSegment == UI.currentSegmentId) return ;

            root.find('.mbc-thread-wrap-resolved').each(function(i, el) {
                var e = $(el), c = e.data('count') ;
                if ( c > 1 ) {
                    console.log('wrap to apply');
                    e.find('.mbc-show-comment')
                        .not(':first')
                        .not(':last')
                        .wrapAll($(tpls.threadCollapsedWrapper));
                    $(tpls.threadCollapsedControl)
                        .insertAfter(e.find('.thread-collapsed'))
                        .find('span.show-thread-number').text('(' + (c-1) + ')');
                }
            });
        }

        var populateWithComments = function(root, comments, panel) {
            if (comments.length == 0) {
                return root;
            }
            var thread_wrap = null, thread_id = 0, count = 0 ;

            for (var i = 0 ; i < comments.length ; i++) {
                if ( comments[i].thread_id != thread_id ) {
                    // start a new thread
                    if (thread_wrap != null) {
                        root.append(thread_wrap);
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
            root.append(thread_wrap);

            applyCollapsedThreadWrap(root);

            // add buttons
            if (comments[i-1].thread_id) {
                // thread is resolved
                var button = $(tpls.reopenThread);
                var container = button.find('.mbc-ask-comment-wrap');
                $(tpls.insertCommentHeader).appendTo(container);
                $(tpls.inputForm).appendTo(container);
                root.append( button ) ;
            } else  {
                // thread is not resolved
                root.find('.mbc-thread-wrap:last')
                    .append($(tpls.replyToComment))
                    .append($(tpls.inputForm).addClass('hide'))
                    .append($(tpls.resolveButton));
            }
            return root;
        }

        var renderSegmentComments = function(el, comments) {
            var root = $(tpls.segmentThread);
            populateWithComments(root, comments, 'segment');
            el.append( root.show() );
        }

        var refreshSegmentContent = function(el) {
            var id_segment = UI.getSegmentId(el);
            var coms = window.db.getCommentsBySegment(id_segment);
            $('.mbc-comment-balloon-outer').remove();

            if (coms.length > 0) {
                renderSegmentComments(el, coms);
            } else {
                renderSegmentCommentsFirstInput(el, coms);
            }
        }

        // behaviour and rendering functions
        var openSegmentComment = function(el) {
            el.find('.editarea').click();
            $('article').addClass('mbc-commenting-opened');
            refreshSegmentContent(el);
        }

        var closeSegment = function(el) {
            lastResolvedSegment = null ;
            $('.mbc-comment-balloon-outer').remove();
            $('article').removeClass('mbc-commenting-opened');
        }

        var renderCommentIconLinks = function() {
            $('section:not(.readonly)').each(function(i, el) {
                $(el).append($(tpls.commentLink));
                $(document).trigger('mbc:segment:update', el);
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
                root.find('.mbc-comment-role').text( decodeRole (data.user_role) );
                root.find('.mbc-comment-body').text( htmlDecode(data.message) );
            }
            return root ;
        }

        var submitComment = function(el) {
            var id_segment = el.attr('id').split('-')[1];

            var data = {
                action     : 'comment',
                _sub       : 'create',
                id_client  : config.id_client,
                id_job     : config.job_id,
                id_segment : id_segment,
                username   : 'John Doe', // TODO
                password   : config.password,
                user_role  : getRole(),
                message    : el.find('.mbc-comment-textarea').val(),
            }

            APP.doRequest({
                data: data,
                success : function(resp) {
                    $(document).trigger('mbc:comment:saved', resp.data[0]);
                },
                failure : function() {
                    console.log('failure');
                }
            });
        }

        $(document).on('click', '.mbc-show-comment-btn', function(e) {
            e.preventDefault();
            openCommentsOnSegmentOpen = true;
            $('.mbc-history-balloon-outer').removeClass('visible');
            var sid = $(e.target).closest('div').data('id')
            if (Number(sid) == UI.currentSegmentId) {
                UI.scrollSegment(UI.currentSegment);
                openSegmentComment( $(UI.currentSegment) );
            } else {
                window.location.hash = '#' + sid;
            }
        });

        $(document).on('click', '.mbc-comment-link', function(e) {
            e.preventDefault();
            var section = $(e.target).closest('section');
            if ( section.hasClass('readonly') ) {
                section.find('.targetarea').click();
            } else {
                openSegmentComment( section );
            }
        });

        $(document).on('click', '.mbc-history-balloon-outer .mbc-close-btn', function(e) {
            e.preventDefault();
            $(e.target).closest('.mbc-history-balloon-outer').toggleClass('visible');
        });

        $(document).on('click', 'section .mbc-close-btn', function(e) {
            e.preventDefault();
            closeSegment( $(e.target).closest('section') );
        });

        $(document).on('click', '.mbc-comment-send-btn', function(e) {
            e.preventDefault();
            submitComment( $(e.target).closest('section') );
            // var id_segment = el.attr('id').split('-')[1];
        });

        $(document).on('click', '.mbc-comment-resolve-btn', function(e) {
            e.preventDefault();

            var data = {
                action     : 'comment',
                _sub       : 'resolve',
                id_job     : config.job_id,
                id_client  : config.id_client,
                id_segment : UI.currentSegmentId,
                password   : config.password,
                user_role  : getRole(),
                username   : getUsername(),
            }


            APP.doRequest({
                data: data,
                success : ajaxResolveSuccess,
                error : function() {
                    console.log('failure');
                }
            });
        });

        var ajaxResolveSuccess = function(resp) {
            db.pushSegment(resp.data[0]);
            lastResolvedSegment = UI.currentSegmentId ;
            $(document).trigger('mbc:comment:new', resp.data[0]);
        }

        $(document).on('click', '.mbc-show-form-btn', function(e) {
            e.preventDefault();
            var t = $(e.target);

            t.closest('.mbc-comment-balloon-outer')
                .find('.mbc-post-comment')
                .addClass('visible');

            t.closest('.mbc-comment-balloon-outer')
                .find('.mbc-ask-comment-wrap')
                .addClass('visible');

            t.remove();
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
                    db.storeSegments( resp.data.current_comments );
                    db.storeSegments( resp.data.open_comments );

                    $(document).trigger('mbc:ready');
                },
                error : function() {
                    console.log('failure');
                }
            });

        });

        $(document).on('mbc:ready', function(ev) {
            renderCommentIconLinks();
            updateHistoryWithLoadedSegments();
        });

        $(document).on('sse:ack', function(ev, message) {
            // generic, just save the current client id
            config.id_client = message.data.clientId;
        });

        $(document).on('sse:comment', function(ev, message) {
            db.pushSegment( message.data ) ;
            updateHistoryWithLoadedSegments();
            renderCommentIconLinks();
        });

        $(document).on('click', '#mbc-history', function(ev) {
            $('.mbc-history-balloon-outer').toggleClass('visible');
        });

        var renderHistoryWithNoComments = function() {
            $('.mbc-history-balloon-has-comment').remove();
            $('.mbc-history-balloon-has-no-comments').show();
            $('.mbc-comment-highlight-history').removeClass('visible');
        }

        var renderHistoryWithComments = function( count ) {
            var root = $(tpls.historyHasComments);

            for (var i in db.history) {
                if (isNaN(i)) { continue ; }

                root.append(
                    $(tpls.threadWrap).append(
                        populateCommentTemplate( db.history[i][0] )
                    ).append( $(tpls.historyViewButton).data('id', db.history[i][0].id_segment ) )
                );
            }
            $('.mbc-history-balloon-has-comment').remove();
            $('.mbc-history-balloon-has-no-comments').hide();

            $('.mbc-history-balloon-outer').append(root);
            $('.mbc-comment-highlight-history').text( count ).addClass( 'visible' );
        }

        window.updateHistoryWithLoadedSegments = function() {
            db.history = {};

            var count = db.refreshHistory();

            if (count == 0) {
                renderHistoryWithNoComments();
            } else {
                renderHistoryWithComments( count );
            }
        }

        $(document).on('mbc:comment:new', function(ev, data) {
            updateHistoryWithLoadedSegments();
            renderCommentIconLinks();

            if (UI.currentSegmentId == Number(data.id_segment)) {
                refreshSegmentContent(UI.currentSegment);
            }
        });

        $(document).on('mbc:comment:saved', function(ev, data) {
            $(document).find('section .mbc-thread-wrap').remove();
            db.pushSegment(data); // TODO: move this in ajax success?
            $(document).trigger('mbc:comment:new', data);
        });

        $(window).on('segmentClosed', function(e) {
            closeSegment($(e.segment));
        });

        $(window).on('segmentOpened', function(e) {
            if (openCommentsOnSegmentOpen) {
                openSegmentComment($(e.segment));
                openCommentsOnSegmentOpen = false;
            }
        });

        $(document).on('click', '.show-thread-btn', function(e) {
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

        $(document).on('mbc:segment:update', function(ev, el) {
            var s = UI.getSegmentId(el);
            var count = db.getCommentsCountBySegment(s) ;
            if (count > 0) {
                $(el).find('.mbc-comment-link .mbc-comment-total').text(count);
            }
        });

        $(document).ready(function(){
            // load for history
            $('.header-menu').append($(tpls.historyIcon));
            $('.header-menu').append($(tpls.historyOuter).append($(tpls.historyNoComments)));
        });

        $(document).on('click', '.mbc-login', function(e) {
            $('.login-google').show();
        });

        $(document).on('login:window:close', function(e) {
            console.log('login window close');

        });

    }

})(jQuery, config, window, undefined);
