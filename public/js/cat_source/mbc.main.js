/*
 Component: mbc.main
 */

MBC = {
    enabled: function () {
        return ( config.comments_enabled && !!window.EventSource );
    }
};

if ( MBC.enabled() )
    (function ( $, config, window, MBC, undefined ) {

        MBC.const = {
            get commentAction() {
                return 'comment';
            }
        };

        MBC.localStorageCommentsClosed =  "commentsPanelClosed-"+config.id_job+config.password

        var types = {sticky: 3, resolve: 2, comment: 1};
        var source_pages = {revise: 2, translate: 1};
        var loggedUserName = null;
        var customUserName = null;
        var lastCommentHash = null;

        var tpls = MBC.const.tpls;

        var initConstants = function () {
            tpls = MBC.const.tpls;
        };


        var getUsername = function () {
            if ( customUserName ) return customUserName;
            if ( loggedUserName ) return loggedUserName;
            return 'Anonymous';
        };

        var getSourcePage = function () {
            if ( config.isReview ) {
                return source_pages.revise;
            }
            else {
                return source_pages.translate;
            }
        };

        var popLastCommentHash = function () {
            var l = lastCommentHash;
            lastCommentHash = null;
            return l;
        };
        var openSegmentCommentNoScroll = function ( idSegment ) {
            SegmentActions.openSegmentComment(idSegment);

            // $( 'article' ).removeClass('comment-opened-0').removeClass('comment-opened-1').removeClass('comment-opened-2').removeClass('comment-opened-empty-0');
            localStorage.setItem(MBC.localStorageCommentsClosed, false);
        };

        var openSegmentComment = function ( idSegment ) {
            SegmentActions.openSegment(idSegment);
            SegmentActions.openSegmentComment(idSegment);

            // $( 'article' ).removeClass('comment-opened-0').removeClass('comment-opened-1').removeClass('comment-opened-2').removeClass('comment-opened-empty-0');
            localStorage.setItem(MBC.localStorageCommentsClosed, false);
        };

        var closeBalloon = function (segmentClose) {
            SegmentActions.closeSegmentComment(segmentClose);
        };

        var populateCommentTemplate = function ( data ) {
            if ( Number( data.message_type ) === types.resolve ) {
                var root = $( tpls.showResolve );
                root.find( '.mbc-comment-username' ).text( htmlDecode( data.full_name ) );
            } else {
                var root = $( tpls.showComment );
                root.find( '.mbc-comment-username' ).text( htmlDecode( data.full_name ) );
                root.find( '.mbc-comment-time' ).text( data.formatted_date );
                var text = nl2br( data.message );
                text = parseCommentHtml(text);
                root.find( '.mbc-comment-body' ).html( text );
                if ( data.email != null ) {
                    root.find( '.mbc-comment-email-label' ).text( data.email );
                }
            }
            return root;
        };

        var nl2br = function ( str, is_xhtml ) {
            var breakTag = (is_xhtml || typeof is_xhtml === 'undefined') ? '<br />' : '<br>';
            return (str + '').replace( /([^>\r\n]?)(\r\n|\n\r|\r|\n)/g, '$1' + breakTag + '$2' );
        };

        var refreshUserInfo = function ( user ) {
            if ( typeof user === 'undefined' ) {
                loggedUserName = null;
            } else {
                loggedUserName = user.full_name;
            }
        };

        var renderHistoryWithNoComments = function () {
            $( '.mbc-history-balloon-has-comment' ).remove();
            $( '.mbc-history-balloon-has-no-comments' ).show();
            $( '.mbc-comment-highlight-history' ).removeClass( 'mbc-visible' );
        };

        var renderHistoryWithComments = function () {
            var root = $( tpls.historyHasComments );
            var count = 1;
            var comment;

            for ( var i in CommentsStore.db.history ) {
                comment = CommentsStore.db.history[i];

                var viewButton = $( tpls.historyViewButton );
                viewButton.find( 'a' ).text( 'View thread' );
                viewButton.attr( 'data-id', comment.id_segment );

                var segmentLabel = $( tpls.historySegmentLabel );

                segmentLabel.find( '.mbc-comment-segment-number' ).text( comment.id_segment );
                //segmentLabel.closest('.mbc-nth-comment').text( count++ );

                var line = populateCommentTemplate( comment );

                line.append( viewButton );
                line.prepend( segmentLabel );

                var wrap = $( tpls.threadWrap ).append( line );

                if ( comment.thread_id == null ) {
                    wrap.addClass( 'mbc-thread-wrap-active' );
                } else {
                    wrap.addClass( 'mbc-thread-wrap-resolved' );
                }

                root.append( wrap );
            }
            $( '.mbc-history-balloon-has-comment' ).remove();
            $( '.mbc-history-balloon-has-no-comments' ).hide();

            $( '.mbc-history-balloon-outer' ).append( root );
        };

        var updateHistoryWithLoadedSegments = function () {
            if ( CommentsStore.db.history_count === 0 ) {
                $( '#mbc-history' )
                        .addClass( 'mbc-history-balloon-icon-has-no-comments' )
                        .removeClass( 'mbc-history-balloon-icon-has-comment' );
                renderHistoryWithNoComments();
            } else {
                $( '#mbc-history' )
                        .removeClass( 'mbc-history-balloon-icon-has-no-comments' )
                        .addClass( 'mbc-history-balloon-icon-has-comment' );
                renderHistoryWithComments();
            }
        };

        var parseCommentHtmlBeforeSend = function ( text ) {

            var elem = $( '<div></div>' ).html(text);
            elem.find(".atwho-inserted").each(function (  ) {
                var id = $(this).find('.tagging-item').data('id');
                $(this).html("{@"+id+"@}");
            });
            elem.find(".tagging-item").remove();
            return elem.text();

        };

        var parseCommentHtml = function ( text ) {
            var regExp = /{@([0-9]+|team)@}/gm;
            if ( regExp.test(text) ) {
                text = text.replace( regExp, function (match, id) {
                    id = (id === "team") ? id : parseInt(id);
                    var user = findUser(id);
                    if (user) {
                        var html = '<span contenteditable="false" class="tagging-item" data-id="'+id+'">'+ user.first_name + ' ' + user.last_name +'</span>';
                        return match.replace(match, html);
                    }
                    return match;
                });
            }

            return text;
        };

        var findUser = function ( id ) {
            return _.find(MBC.teamUsers, function ( item ) {
                return item.uid === id;
            });
        };

        var submitComment = function ( text, sid ) {

            text = parseCommentHtmlBeforeSend(text);

            var data = {
                action: 'comment',
                _sub: 'create',
                id_client: config.id_client,
                id_job: config.id_job,
                id_segment: sid,
                username: getUsername(),
                password: config.password,
                source_page: getSourcePage(),
                message: text,
            };

            return APP.doRequest( {
                data: data,
                success: function ( resp ) {
                    if ( resp.errors.length ) {
                        // showGenericWarning();
                    } else {
                        $( document ).trigger( 'mbc:comment:saved', resp.data.entries[0] );
                    }
                }
            } );
        };

        var loadCommentData = function ( success ) {
            var data = {
                action: 'comment',
                _sub: 'getRange',
                id_job: config.id_job,
                first_seg: UI.getSegmentId( UI.firstSegment ),
                last_seg: UI.getSegmentId( UI.lastSegment ),
                password: config.password
            };

            APP.doRequest( {
                data: data,
                success: success,
                error: function () {
                    // TODO: handle error on comments fetch
                }
            } );
        };

        var resolveThread = function ( sid ) {


            var data = {
                action: 'comment',
                _sub: 'resolve',
                id_job: config.id_job,
                id_client: config.id_client,
                id_segment: sid,
                password: config.password,
                source_page: getSourcePage(),
                username: getUsername(),
            };

            return APP.doRequest( {
                data: data,
                success: function ( resp ) {
                    $( document ).trigger( 'mbc:comment:new', resp.data.entries[0] );
                }
            } );
        };

        var resetDatabase = function ( resp ) {
            CommentsActions.storeComments(resp.data.entries.comments, resp.data.user );
            refreshUserInfo( resp.data.user );
        };

        var refreshElements = function () {
            updateHistoryWithLoadedSegments();
        };

        var getTeamUsers = function (  ) {
            var teamId = config.id_team;
            if ( teamId ) {
                return $.ajax({
                    async: true,
                    type: "get",
                    xhrFields: { withCredentials: true },
                    url : APP.getRandomUrl() + "api/app/teams/" + teamId + "/members/public"
                }).done(function ( data ) {
                    var team = {
                        uid: "team",
                        first_name: "Team",
                        last_name: ""
                    };
                    MBC.teamUsers = data;
                    MBC.teamUsers.unshift(team);

                    CommentsActions.updateTeamUsers(MBC.teamUsers);

                }).fail(function ( response ) {
                    MBC.teamUsers = [];
                })
            } else {
                MBC.teamUsers = [];
                return $.Deferred().resolve();
            }
        };

        var checkOpenSegmentComment = function ( id_segment ) {
            if ( CommentsStore.db.getCommentsCountBySegment && UI.currentSegmentId === id_segment) {
                var comments_obj = CommentsStore.db.getCommentsCountBySegment( id_segment );
                var panelClosed = localStorage.getItem(MBC.localStorageCommentsClosed) === 'true';
                if ( comments_obj.active > 0  && !panelClosed) {
                    openSegmentCommentNoScroll(id_segment);
                }
            }
        };


        /**
         * Close balloon if the user click on some dead area
         * of the page.
         */
        $(document).on('click', function(e) {
            if ($(e.target).closest('section') == null) {
                closeBalloon();
            }
        });

        $( document ).ready( function () {
            initConstants();
            // XXX: there'a binding on 'section' are delegated to #outer in ui.events.js.
            //      Since our DOM elements are children of `section` we must attach to #outer
            //      to in order to prevent bubbling.
            //
            //      If a click event reaches #outer, we assume the user clicked outside
            //      the section, so we close the balloon.
            //
            //
            var delegate = '#outer';


            // Click reached #outer , close the history balloon
            $( delegate ).on( 'click', function () {
                $( '.mbc-history-balloon-outer' ).removeClass( 'mbc-visible' );
            } );

            $( window ).on( 'segmentsAdded', function ( e ) {
                loadCommentData( function ( resp ) {
                    resetDatabase( resp );
                    refreshElements();
                } );
            } );

            $( document ).on( 'getSegments_success', function ( e ) {
                loadCommentData( function ( resp ) {
                    resetDatabase( resp );
                    $( document ).trigger( 'mbc:ready' );
                } );
            } );

            $( document ).on( 'mbc:ready', function ( ev ) {

                $( '#mbc-history' ).remove();
                $( '.header-menu li#filterSwitch' ).before( $( tpls.historyIcon ) );
                $( '#mbc-history' ).append( $( tpls.historyOuter ).append( $( tpls.historyNoComments ) ) );


                getTeamUsers().then( function() {
                    refreshElements();
                    // open a comment if was asked by hash
                    var lastAsked = popLastCommentHash();
                    if ( lastAsked ) {
                        openSegmentComment(lastAsked.segmentId );
                    }
                });
                //New icon inserted in the header -> resize file name
                APP.fitText($('.breadcrumbs'), $('#pname'), 30);
            } );
        } );


        $( document ).on( 'keydown', function ( e ) {
            if ( e.which == '27' ) {
                e.preventDefault();
                SegmentActions.closeSegmentComment();
            }
        } );

        $( document ).on( 'click', '.mbc-show-comment-btn', function ( e ) {
            e.preventDefault();
            e.stopPropagation();
            $( '.mbc-history-balloon-outer' ).removeClass( 'mbc-visible' );

            var sid = $( e.target ).closest( 'div' ).data( 'id' ) + "";
            SegmentActions.scrollToSegment(sid, SegmentActions.openSegmentComment);
        } );



        $( document ).on( 'sse:comment', function ( ev, message ) {
            CommentsActions.updateCommentsFromSse(message.data);
            $( document ).trigger( 'mbc:comment:new', message.data );
        } );

        $( document ).on( 'click', '#filterSwitch', function ( e ) {
            $( '.mbc-history-balloon-outer' ).removeClass( 'mbc-visible' );
        } );

        $( document ).on( 'click', '#mbc-history', function ( ev ) {
            if ( $( '.mbc-history-balloon-outer' ).hasClass('mbc-visible') ) {
                UI.closeAllMenus(ev);
            } else {
                UI.closeAllMenus(ev);
                $( '.mbc-history-balloon-outer' ).addClass( 'mbc-visible' );
            }
        } );

        $( document ).on( 'mbc:comment:new', function ( ev, data ) {
            updateHistoryWithLoadedSegments();
        } );

        $( document ).on( 'mbc:comment:saved', function ( ev, data ) {
            //Update Header icon
            updateHistoryWithLoadedSegments();
        } );

        $( window ).on( 'segmentOpened', function ( e ) {
            var segment = e.segment ;
            if ( MBC.wasAskedByCommentHash( segment.absoluteId ) ) {
                openSegmentComment( $( e.segment ) );
            }
            checkOpenSegmentComment(segment.absoluteId);
        } );

        // $( document ).on( 'split:segment:complete', function ( e, sid ) {
        //     var segment = UI.Segment.find( sid );
        //     initCommentLink( segment.el );
        //     renderCommentIconLink( segment.el );
        // } );

        // $( document ).on( 'click', '.mbc-tag-link', function ( e ) {
        //     $( '.mbc-comment-textarea-tagging' ).toggleClass('hide');
        // } );

        $( document ).on( 'ui:segment:focus', function ( e, sid ) {
            if ( lastCommentHash && lastCommentHash.segmentId == sid ) {
                openSegmentComment( UI.Segment.findEl( sid ) );
                lastCommentHash = null;
            }
        } );

        // Interfaces
        $.extend( MBC, {
            popLastCommentHash: popLastCommentHash,

            wasAskedByCommentHash: function ( sid ) {
                return lastCommentHash && lastCommentHash.segmentId == sid;
            },
            setLastCommentHash: function ( value ) {
                lastCommentHash = value;
            },


            submitComment: submitComment,
            resolveThread: resolveThread
        } );

    })( jQuery, config, window, MBC );
