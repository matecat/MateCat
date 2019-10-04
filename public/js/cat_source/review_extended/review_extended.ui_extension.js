
if ( ReviewExtended.enabled() || ReviewExtendedFooter.enabled()) {

    $.extend(ReviewExtended, {

        submitIssue: function (sid, data_array, diff) {
            var fid = UI.getSegmentFileId(UI.getSegmentById(sid));


            var deferreds = _.map(data_array, function (data) {
                data.diff = diff;
                return API.SEGMENT.sendSegmentVersionIssue(sid, data)
            });

            return $.when.apply($, deferreds).done(function (response) {
                UI.getSegmentVersionsIssues(sid, fid);
                UI.reloadQualityReport();
            });
        },

        submitComment : function(id_segment, id_issue, data) {
            return API.SEGMENT.sendSegmentVersionIssueComment(id_segment, id_issue, data)
                .done( function( data ) {
                    var fid = UI.getSegmentFileId(UI.getSegmentById(id_segment));
                    UI.getSegmentVersionsIssues(id_segment, fid);
                });
        }
    });

    var originalRender = UI.render;
    $.extend(UI, {

        render: function ( options ) {
            var promise = (new $.Deferred() ).resolve();
            originalRender.call(this, options);
            return promise;
        },
        // overrideButtonsForRevision: function () {
        //     var div = $('<ul>' + UI.segmentButtons + '</ul>');
        //     var className = "revise-button-" + ReviewExtended.number;
        //     div.find('.translated').text('APPROVED').removeClass('translated').addClass('approved').addClass(className);
        //     var nextSegment = UI.currentSegment.next();
        //     var nextSelector = this.getSelectorForNextSegment();
        //     var goToNextApprovedButton = !nextSegment.is(nextSelector);
        //     var filtering = (SegmentFilter.enabled() && SegmentFilter.filtering() && SegmentFilter.open);
        //     div.find('.next-untranslated').parent().remove();
        //     div.find('.next-repetition').removeClass('next-repetition').addClass('next-review-repetition').removeClass('primary').addClass('green');
        //     div.find('.next-repetition-group').removeClass('next-repetition-group').addClass('next-review-repetition-group').removeClass('primary').addClass('green');
        //     if (goToNextApprovedButton && !filtering) {
        //         var htmlButton = '<li><a id="segment-' + this.currentSegmentId +
        //             '-nexttranslated" href="#" class="btn next-unapproved ' + className + '" data-segmentid="segment-' +
        //             this.currentSegmentId + '" title="Revise and go to next translated"> A+&gt;&gt;</a><p>' +
        //             ((UI.isMac) ? 'CMD' : 'CTRL') + '+SHIFT+ENTER</p></li>';
        //         div.html(htmlButton + div.html());
        //     }
        //     UI.segmentButtons = div.html();
        // },
        /**
         * Overwrite the Review function that updates the tab trackChanges, in this review we don't have track changes.
         * @param editarea
         */
        trackChanges: function (editarea) {
            var segmentId = UI.getSegmentId($(editarea));
            var segmentFid = UI.getSegmentFileId($(editarea));
            var currentSegment =  UI.getSegmentById(segmentId)
            var originalTranslation = currentSegment.find('.original-translation').html();
            // SegmentActions.updateTranslation(segmentFid, segmentId, $(editarea).html(), originalTranslation);
        },

        submitIssues: function (sid, data, diff) {
            return ReviewExtended.submitIssue(sid, data, diff);
        },

        getSegmentVersionsIssuesHandler(sid) {
            var segment = SegmentStore.getSegmentByIdToJS(sid);
            UI.getSegmentVersionsIssues(segment.original_sid, segment.id_file);
        },

        getSegmentVersionsIssues: function (segmentId, fileId) {
            API.SEGMENT.getSegmentVersionsIssues(segmentId)
                .done(function (response) {
                    UI.addIssuesToSegment(fileId, segmentId, response.versions)
                });
        },

        /**
         * To show the issues in the segment footer
         * @param fileId
         * @param segmentId
         * @param versions
         */
        addIssuesToSegment: function ( fileId, segmentId, versions ) {
            SegmentActions.addTranslationIssuesToSegment(fileId, segmentId, versions);
        },


        /**
         * To delete a segment issue
         * @param context
         */
        deleteTranslationIssue : function( context ) {
            var parsed = JSON.parse( context );
            var issue_path = sprintf(
                APP.getRandomUrl() + 'api/v2/jobs/%s/%s/segments/%s/translation-issues/%s',
                config.id_job, config.review_password,
                parseInt(parsed.id_segment),
                parsed.id_issue
            );
            var issue_id = parsed.id_issue;
            var fid = UI.getSegmentFileId(UI.getSegmentById(parsed.id_segment));
            $.ajax({
                url: issue_path,
                type: 'DELETE',
                xhrFields: { withCredentials: true }
            }).done( function( data ) {
                UI.deleteSegmentIssues(fid, parsed.id_segment, issue_id);
                UI.reloadQualityReport();
            });
        },
        /**
         * To remove Segment issue from the segment footer
         * @param fid
         * @param id_segment
         * @param issue_id
         */
        deleteSegmentIssues: function ( fid, id_segment, issue_id ) {
            SegmentActions.confirmDeletedIssue(id_segment, issue_id);
            UI.getSegmentVersionsIssues(id_segment, fid);
        },
        /**
         * To know if a segment has been modified but not yet approved
         * @param sid
         * @returns {boolean}
         */
        segmentIsModified: function ( sid ) {
            var segment = SegmentStore.getSegmentByIdToJS(sid);
            return segment.modified;
        },
        submitComment : function(id_segment, id_issue, data) {
            return ReviewExtended.submitComment(id_segment, id_issue, data)
        },
        openIssuesPanel : function(data, openSegment) {
            var segment = SegmentStore.getSegmentByIdToJS( data.sid );

            if (segment && !UI.evalOpenableSegment( segment )) {
                return false;
            }
            $('body').addClass('review-extended-opened');
            localStorage.setItem(ReviewExtended.localStoragePanelClosed, false);

            $('body').addClass('side-tools-opened review-side-panel-opened');
            window.dispatchEvent(new Event('resize'));
            if (data && openSegment) {
                SegmentActions.openSegment(data.sid);
                SegmentActions.scrollToSegment(data.sid);
                window.setTimeout( function ( data ) {
                    UI.scrollSegment( data.sid );
                }, 500, data );
            }
            return true;
        },

        closeIssuesPanel : function() {
            hackIntercomButton( false );
            SegmentActions.closeIssuesPanel();
            $('body').removeClass('side-tools-opened review-side-panel-opened review-extended-opened');
            localStorage.setItem(ReviewExtended.localStoragePanelClosed, true);
            // if ( UI.currentSegment ) {
            //     setTimeout( function() {
            //         UI.scrollSegment( UI.currentSegmentId );
            //     }, 100 );
            // }
            // window.dispatchEvent(new Event('resize'));
        },

        deleteIssue : function( issue, sid, dontShowMessage) {
            var message = '';
            if ( issue.target_text ) {
                message = sprintf(
                    "You are about to delete the issue on string <span style='font-style: italic;'>'%s'</span> " +
                    "posted on %s." ,
                    issue.target_text,
                    moment( issue.created_at ).format('lll')
                );
            } else {
                message = sprintf(
                    "You are about to delete the issue posted on %s." ,
                    moment( issue.created_at ).format('lll')
                );
            }
            if ( !dontShowMessage) {
                APP.confirm({
                    name : 'Confirm issue deletion',
                    callback : 'deleteTranslationIssue',
                    msg: message,
                    okTxt: 'Yes delete this issue',
                    context: JSON.stringify({
                        id_segment : sid,
                        id_issue : issue.id
                    })
                });
            } else {
                UI.deleteTranslationIssue(JSON.stringify({
                    id_segment : sid,
                    id_issue : issue.id
                }));
            }
        },
        setRevision: function ( data ) {
            APP.doRequest( {
                data: data,
                error: function () {
                    UI.failedConnection( data, 'setRevision' );
                },
                success: function ( d ) {
                    window.quality_report_btn_component.setState( {
                        vote: d.data.overall_quality_class
                    } );
                }
            } );
        },
        /**
         *
         * @param d Data response from the SetCurrentSegment request
         * @param id_segment
         */
        addOriginalTranslation: function ( d, id_segment ) {
            var xEditarea = $( '#segment-' + id_segment + '-editarea' );
            if ( d.original !== '' ) {
                setTimeout( function () {
                    SegmentActions.addOriginalTranslation( id_segment, UI.getSegmentFileId( $( '#segment-' + id_segment ) ), d.original );
                } );
            }
        },

        clickOnApprovedButton: function (button ) {
            // the event click: 'A.APPROVED' i need to specify the tag a and not only the class
            // because of the event is triggered even on download button
            var sid = UI.currentSegmentId;
            var segment = SegmentStore.getSegmentByIdToJS(sid);

            //If is a splitted segment the user need to specify a issue before approve
            var isSplit = segment.splitted;
            if (!isSplit && UI.segmentIsModified(sid) && ReviewExtended.issueRequiredOnSegmentChange) {
                SegmentActions.showIssuesMessage(sid);
                return;
            }

            var goToNextUnapproved = ($( button ).hasClass( 'next-unapproved' )) ? true : false;
            SegmentActions.removeClassToSegment( sid, 'modified' );
            UI.currentSegment.data( 'modified', false );


            $( '.sub-editor.review .error-type' ).removeClass( 'error' );

            var afterApproveFn = function (  ) {
                if ( goToNextUnapproved ) {
                    if ( segment.revision_number > 1 ) {
                        UI.openNextApproved();
                    } else {
                        UI.openNextTranslated();
                    }
                } else {
                    UI.gotoNextSegment( sid );
                }
            };

            UI.setTimeToEdit(UI.currentSegment);
            UI.changeStatus( button, 'approved', 0,  afterApproveFn);  // this does < setTranslation

            var original = UI.currentSegment.find( '.original-translation' ).text();




            var data = {
                action: 'setRevision',
                job: config.id_job,
                jpassword: config.password,
                segment: sid,
                original: original
            };
            // Lock the segment if it's approved in a second pass but was previously approved in first revision
            if ( ReviewExtended.number > 1 ) {
                UI.removeFromStorage('unlocked-' + sid);
            }
            UI.setRevision( data );
        },
        openNextApproved: function ( sid ) {
            sid = sid || UI.currentSegmentId;
            var nextApprovedSegment = SegmentStore.getNextSegment(sid, null, 9, 1);
            var nextApprovedSegmentInPrevious = SegmentStore.getNextSegment(-1, null, 9, 1);
            // find in next segments
            if(nextApprovedSegment) {
                SegmentActions.openSegment(nextApprovedSegment.sid);
                // else find from the beginning of the currently loaded segments in all files
            } else if ( this.noMoreSegmentsBefore && nextApprovedSegmentInPrevious) {
                SegmentActions.openSegment(nextApprovedSegmentInPrevious.sid);
            } else if ( !this.noMoreSegmentsBefore || !this.noMoreSegmentsAfter) { // find in not loaded segments or go to the next approved
                SegmentActions.openSegment(UI.nextUntranslatedSegmentIdByServer);
            }
        }

    });
}
