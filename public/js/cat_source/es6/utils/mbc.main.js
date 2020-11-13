/*
 Component: mbc.main
 */
import CommentsStore from '../stores/CommentsStore';
const MBC = {
    enabled: function () {
        return ( config.comments_enabled && !!window.EventSource );
    }
};

MBC.init = function() {

    return (function ( $, config, window, MBC, undefined ) {

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
        var commentsLoaded = false;
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

        var refreshBadgeHeaderIcon = function(){
            var count = CommentsStore.db.getOpenedThreadCount();
            $('#mbc-history .badge').remove();
            if(count > 0){
                $('#mbc-history').append(`<span class='badge'>${count}</span>`)
            }
        };
        var limitNum = function ( num ) {
            if ( Number( num ) > 99 ) return '+99';
            else return num;
        };

        var buildFirstCommentHeader = function () {
            return $( tpls.firstCommentWrap );
        };

        var popLastCommentHash = function () {
            var l = lastCommentHash;
            lastCommentHash = null;
            return l;
        };
        var openSegmentCommentNoScroll = function ( idSegment ) {
            SegmentActions.openSegmentComment(idSegment);
            SegmentActions.scrollToSegment(idSegment);

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
            localStorage.setItem(MBC.localStorageCommentsClosed, true);
        };

        var populateCommentTemplate = function ( data ) {
            if ( Number( data.message_type ) === types.resolve ) {
                var root = $( tpls.showResolve );
                root.find( '.mbc-comment-username' ).text( TextUtils.htmlDecode( data.full_name ) );
            } else {
                var root = $( tpls.showComment );
                root.find( '.mbc-comment-username' ).text( TextUtils.htmlDecode( data.full_name ) );
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
            $( '#mbc-history' ).removeClass( 'open' );
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
                revision_number: config.revisionNumber,
                username: getUsername(),
                password: config.password,
                source_page: getSourcePage(),
                message: text,
            };

            return APP.doRequest( {
                data: data
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
            refreshBadgeHeaderIcon();
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

        MBC.const.tpls = { // TODO: make this local

            historyIcon : '' +
                '  <div id="mbc-history" title="View comments"> ' +
                /*'      <span class="icon-bubble2"></span> ' +*/
                '<svg xmlns="http://www.w3.org/2000/svg" viewBox="3 3 36 36">' +
                ' <path fill="#fff" fill-rule="evenodd" stroke="none" stroke-width="1" d="M33.125 13.977c-1.25-1.537-2.948-2.75-5.093-3.641C25.886 9.446 23.542 9 21 9c-2.541 0-4.885.445-7.031 1.336-2.146.89-3.844 2.104-5.094 3.64C7.625 15.514 7 17.188 7 19c0 1.562.471 3.026 1.414 4.39.943 1.366 2.232 2.512 3.867 3.439-.114.416-.25.812-.406 1.187-.156.375-.297.683-.422.922-.125.24-.294.505-.508.797a8.15 8.15 0 01-.484.617 249.06 249.06 0 00-1.023 1.133 1.1 1.1 0 00-.126.141l-.109.132-.094.141c-.052.078-.075.127-.07.148a.415.415 0 01-.031.156c-.026.084-.024.146.007.188v.016c.042.177.125.32.25.43a.626.626 0 00.422.163h.079a11.782 11.782 0 001.78-.344c2.73-.697 5.126-1.958 7.189-3.781.78.083 1.536.125 2.265.125 2.542 0 4.886-.445 7.032-1.336 2.145-.891 3.843-2.104 5.093-3.64C34.375 22.486 35 20.811 35 19c0-1.812-.624-3.487-1.875-5.023z"/> ' +
                '</svg>' +
                '  </div>',

            historyOuter : '' +
                ' <div class="mbc-history-balloon-outer hide"> ' +
                '     <div class="mbc-triangle mbc-triangle-top"></div> ' +
                ' </div> ',

            historyViewButton: '' +
                ' <div class="mbc-clearfix mbc-view-comment-wrap"> ' +
                '   <a href="javascript:;" class="mbc-comment-link-btn mbc-view-link mbc-show-comment-btn">View thread</a>' +
                ' </div> ',

            historySegmentLabel: '' +
                ' <span class="mbc-nth-comment mbc-nth-comment-label">Segment <span class="mbc-comment-segment-number"></span></span> ',

            historyHasComments: '' +
                ' <div class="mbc-history-balloon mbc-history-balloon-has-comment"> ' +
                ' ' + // showComment loop here
                ' </div> ',

            historyNoComments : '' +
                ' <div class="mbc-history-balloon mbc-history-balloon-has-no-comments" style="display: block;">' +
                '    <div class="mbc-thread-wrap"> ' +
                '       <div class="mbc-show-comment"> ' +
                '           <span class="mbc-comment-label">No comments</span>'  +
                '       </div> ' +
                '    </div> ' +
                ' </div>',

            activeCommentsNumberInHistory : '' +
                '<span class="mbc-comment-highlight mbc-comment-highlight-balloon-history mbc-comment-notification"></span>',

            divider : '' +
                '<div class="divider"></div>',

            // showResolve : '' +
            //     '<div class="mbc-resolved-comment">' +
            //     ' <span class="mbc-comment-resolved-label">' +
            //     '   <span class="mbc-comment-username mbc-comment-resolvedby"></span>' +
            //     '   <span class="">marked as resolved</span>' +
            //     ' </span>' +
            //     '</div>' ,

            threadWrap : '' +
                ' <div class="mbc-thread-wrap mbc-clearfix">'  +
                '' + // comments go here
                ' </div>',

            showComment : '' +
                '<div class="mbc-show-comment mbc-clearfix">' +
                ' <span class="mbc-comment-label mbc-comment-username mbc-comment-username-label mbc-truncate"></span>' +
                ' <span class="mbc-comment-label mbc-comment-email-label mbc-truncate"></span>' +
                ' <div class="mbc-comment-info-wrap mbc-clearfix">' +
                '   <span class="mbc-comment-info mbc-comment-time pull-left"></span>' +
                ' </div>' +
                ' <p class="mbc-comment-body"></p>' +
                ' </div>' ,

            firstCommentWrap : '' +
                ' <div class="mbc-thread-wrap mbc-thread-wrap-active mbc-clearfix">' +
                '' + // insertCommentHeader
                '</div>',
        };

        /**
         * Close balloon if the user click on some dead area
         * of the page.
         */
        $( document ).on( 'click', function ( e ) {
            if ( $( e.target ).closest( 'section' ) == null ) {
                closeBalloon();
            }
        } );

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
            $( '#mbc-history' ).removeClass( 'open' );
        } );

        $( window ).on( 'segmentsAdded', function ( e ) {
            loadCommentData( function ( resp ) {
                resetDatabase( resp );
                refreshElements();
            } );
        } );

        $( document ).on( 'getSegments_success', function ( e ) {
            loadCommentData( function ( resp ) {
                MBC.commentsLoaded = true;
                resetDatabase( resp );
                $( document ).trigger( 'mbc:ready' );
            } );
        } );

        $( document ).on( 'mbc:ready', function ( ev ) {

            $( '#mbc-history' ).remove();
            $( '.action-menu #action-filter' ).before( $( tpls.historyIcon ) );
            $( '#mbc-history' ).append( $( tpls.historyOuter ).append( $( tpls.historyNoComments ) ) );


            getTeamUsers().then( function () {
                refreshElements();
                // open a comment if was asked by hash
                var lastAsked = popLastCommentHash();
                if ( lastAsked ) {
                    openSegmentComment( lastAsked.segmentId );
                }
            } );
            //New icon inserted in the header -> resize file name
            APP.fitText($('#pname-container'), $('#pname'), 25);
        } );


        $( document ).on( 'click', '.mbc-show-comment-btn', function ( e ) {
            e.preventDefault();
            e.stopPropagation();
            $( '.mbc-history-balloon-outer' ).removeClass( 'mbc-visible' );
            $( '#mbc-history' ).removeClass( 'open' );

            var sid = $( e.target ).closest( 'div' ).data( 'id' ) + "";
            SegmentActions.scrollToSegment( sid, SegmentActions.openSegmentComment );
        } );

        $( document ).on( 'click', '#action-search', function ( e ) {
            $( '.mbc-history-balloon-outer' ).removeClass( 'mbc-visible' );
            $( '#mbc-history' ).removeClass( 'open' );
        } );

        $( document ).on( 'click', '#mbc-history', function ( ev ) {
            if ( $( '.mbc-history-balloon-outer' ).hasClass( 'mbc-visible' ) ) {
                UI.closeAllMenus( ev );
            } else {
                UI.closeAllMenus( ev );
                $( '.mbc-history-balloon-outer' ).addClass( 'mbc-visible' );
                $( '#mbc-history' ).addClass( 'open' );
            }
        } );

        $( document ).on( 'sse:comment', function ( ev, message ) {
            CommentsActions.updateCommentsFromSse( message.data );
            updateHistoryWithLoadedSegments();
            setTimeout(refreshBadgeHeaderIcon);
        } );

        $( document ).on( 'mbc:comment:saved', function ( ev, data ) {
            //Update Header icon
            updateHistoryWithLoadedSegments();
            setTimeout(refreshBadgeHeaderIcon);
        } );

        $( window ).on( 'segmentOpened', function ( e, data ) {
            var fn = function () {
                if ( MBC.wasAskedByCommentHash( data.segmentId ) ) {
                    openSegmentComment( $( UI.getSegmentById( data.segmentId ) ) );
                }
                checkOpenSegmentComment( data.segmentId );
            };

            if ( MBC.commentsLoaded ) {
                fn();
            } else {
                setTimeout( fn, 1000 );
            }
        } );


        $( document ).on( 'ui:segment:focus', function ( e, sid ) {
            if ( lastCommentHash && lastCommentHash.segmentId == sid ) {
                openSegmentComment( UI.Segment.findEl( sid ) );
                lastCommentHash = null;
            }
        } );

    })( jQuery, config, window, MBC );
};

$(document).ready(function() {
    if (MBC.enabled()) {
        MBC.init();
    }
});
module.exports = MBC;