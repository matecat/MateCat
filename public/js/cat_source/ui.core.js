/*
	Component: ui.core
 */
var UI = {
    /**
     * Open file menu in Header
     * @returns {boolean}
     */
	toggleFileMenu: function() {
        var jobMenu = $('#jobMenu');
		if (jobMenu.is(':animated')) {
			return false;
		} else {
            var segment = SegmentStore.getCurrentSegment();
            var currSegment = jobMenu.find('.currSegment');
            if (segment) {
                currSegment.removeClass('disabled');
            } else {
                currSegment.addClass('disabled');
            }
            var menuHeight = jobMenu.height();
            if (LXQ.enabled()) {
                var lexiqaBoxIsOpen = $('#lexiqa-popup').hasClass('lxq-visible');
                var lxqBoxHeight =  (lexiqaBoxIsOpen)? $('#lexiqa-popup').outerHeight() + 8 : 0;
                jobMenu.css('top', (lxqBoxHeight + 43 - menuHeight) + "px");
            }
            else {
                jobMenu.css('top', (43 - menuHeight) + "px");
            }

            if (jobMenu.hasClass('open')) {
                jobMenu.animate({top: "-=" + menuHeight + "px"}, 500).removeClass('open');
            } else {
                jobMenu.animate({top: "+=" + menuHeight + "px"}, 300, function() {
                    $('body').on('click', function() {
                        if (jobMenu.hasClass('open')) {
                            UI.toggleFileMenu();
                        }
                    });
                }).addClass('open');
            }
            return true;
        }

	},

	cacheObjects: function( editarea_or_segment ) {
        var segment, $segment;

        this.editarea = $(".targetarea", $(editarea_or_segment).closest('section'));
        $segment = $(editarea_or_segment).closest('section');
        segment = SegmentStore.getSegmentByIdToJS( UI.getSegmentId($segment) );

        if ( !$segment.length || !segment ) {
            return;
        }

		this.currentSegmentId    = segment.sid ;
		this.currentSegment      = $segment ;
    },

    removeCacheObjects: function() {
        this.editarea = "";
        this.currentSegmentId = undefined;
        this.currentSegment = undefined;
    },
    /**
     * shouldSegmentAutoPropagate
     *
     * Returns whether or not the segment should be propagated. Default is true.
     *
     * @returns {boolean}
     */
    shouldSegmentAutoPropagate : function( segment, status ) {
        var segmentStatus = segment.status.toLowerCase();
        var statusAcceptedNotModified = ['new', 'draft'];
        var segmentModified = segment.modified;
        return segmentModified || ( statusAcceptedNotModified.indexOf(segmentStatus) !== -1 ) || ( !segmentModified && status.toLowerCase() !== segmentStatus ) ||
            ( !segmentModified && status.toLowerCase() === segmentStatus && segmentStatus === 'approved' && config.revisionNumber !== segment.revision_number ); // from R1 to R2 and reverse
    },

    /**
     *
     * @param segment
     * @param status
     * @param callback
     */
	changeStatus: function(segment, status, callback) {
        var segment_id = segment.sid;
        var opts = {
            segment_id      : segment_id,
            status          : status,
            propagation     : segment.propagable && UI.shouldSegmentAutoPropagate( segment, status ),
            callback        : callback
        };

        // ask if the user wants propagation or this is valid only
        // for this segment

        if ( this.autopropagateConfirmNeeded( opts.propagation ) ) {

            var text = ( !_.isUndefined(segment.alternatives) ) ? "There are other identical segments with <b>translation conflicts</b>. <br><br>Would you " +
                "like to propagate the translation and the status to all of them, " +
                "or keep this translation only for this segment?"
                : "There are other identical segments. <br><br>Would you " +
                "like to propagate the translation and the status to all of them, " +
                "or keep this translation only for this segment?";
            // var optionsStr = opts;
            var props = {
                text: text,
                successText: 'Only this segment',
                successCallback: function(){
                        opts.propagation = false;
                        opts.autoPropagation = false;
                        UI.preExecChangeStatus(opts);
                        APP.ModalWindow.onCloseModal();
                    },
                cancelText: 'Propagate to All',
                cancelCallback: function(){
                        opts.propagation = true;
                        opts.autoPropagation = false;
                        UI.execChangeStatus(opts);
                        APP.ModalWindow.onCloseModal();
                    },
                onClose: function(){
                    UI.preExecChangeStatus(opts);
                }
            };
            APP.ModalWindow.showModalComponent(ConfirmMessageModal, props, "Confirmation required ");
        } else {
            opts.autoPropagation = true;
            this.execChangeStatus( opts ); // autopropagate
        }
	},

    autopropagateConfirmNeeded: function (propagation) {
        var segment = SegmentStore.getCurrentSegment();
        var segmentModified = segment.modified;
        var segmentStatus = segment.status.toLowerCase();
        var statusNotConfirmationNeeded = ['new', 'draft'];
        if( propagation ) {
            if(config.isReview) {
                return  ( segmentModified || !_.isUndefined(segment.alternatives) ) ;
            } else {
                return statusNotConfirmationNeeded.indexOf(segmentStatus) === -1  &&
                    ( segmentModified || !_.isUndefined(segment.alternatives) );
            }
        }
        return false;

    },
    preExecChangeStatus: function (optStr) {
        var opt = optStr;
        opt.propagation = false;
        this.execChangeStatus(opt);
    },
    execChangeStatus: function (optStr) {
        var options = optStr;

        var propagation   = options.propagation;
        var status        = options.status;

        SegmentActions.hideSegmentHeader(options.segment_id);

        this.setTranslation({
            id_segment: options.segment_id,
            status: status,
            caller: false,
            propagate: propagation,
            autoPropagation: options.autoPropagation
        }, optStr.callback);

         SegmentActions.modifiedTranslation(options.segment_id, false);
    },

    getSegmentId: function (segment) {
        if(typeof segment == 'undefined') return false;
        if ( segment.el ) {
            return segment.el.attr( 'id' ).replace( 'segment-', '' );
        }
        try {
            segment = segment.closest("section");
            return $(segment).attr('id').replace('segment-', '');
        } catch( e ){
            return false;
        }

    },

    getSegmentFileId: function (segment) {
        if(typeof segment == 'undefined') return false;
        try {
            segment = segment.closest("section");
            return $(segment).attr('data-fid');
        } catch( e ){
            return false;
        }

    },

    // fixHeaderHeightChange: function() {
    //     var headerHeight = $('header .wrapper').height();
    //     $('#outer').css('margin-top', headerHeight + 'px');
    // },

	getMoreSegments: function(where) {

		if ((where == 'after') && (this.noMoreSegmentsAfter)) {

            return;
        }
		if ((where == 'before') && (this.noMoreSegmentsBefore)) {

            return;
        }
		if ( this.loadingMore ) {

            return;
        }

        console.log('Get more segments: ', where);

		this.loadingMore = true;

		var segId = (where === 'after') ? SegmentStore.getLastSegmentId() : (where === 'before') ? SegmentStore.getFirstSegmentId() : '';

		if (where == 'before') {
			$("section").each(function() {
				if ($(this).offset().top > $(window).scrollTop()) {
					UI.segMoving = UI.getSegmentId($(this));
					return false;
				}
			});
		}

		if (where == 'before') {
			$('#outer').addClass('loadingBefore');
		} else if (where == 'after') {
			$('#outer').addClass('loading');
		}

		APP.doRequest({
			data: {
				action: 'getSegments',
				jid: config.id_job,
				password: config.password,
				step: UI.moreSegNum,
				segment: segId,
				where: where
			},
			error: function() {
				OfflineUtils.failedConnection(where,'getMoreSegments');
			},
			success: function(d) {
                $(document).trigger('segments:load', d.data);
                UI.getMoreSegments_success(d);
			}
		});
	},
	getMoreSegments_success: function(d) {
		if (d.errors.length)
			this.processErrors(d.errors, 'getMoreSegments');
		var where = d.data.where;
        var section = $('section');
		if ( d.data.files && _.size(d.data.files) ) {

			this.renderFiles(d.data.files, where, false);

            $(window).trigger('segmentsAdded',{ resp : d.data.files });

		}

		if ( d.data.files.length === 0 || SegmentStore.getLastSegmentId() === config.last_job_segment) {
			if (where == 'after')
				this.noMoreSegmentsAfter = true;
			if (where == 'before')
				this.noMoreSegmentsBefore = true;
		}
		$('#outer').removeClass('loading loadingBefore');
		this.loadingMore = false;
	},

	getSegments: function(options) {

		var where = (this.startSegmentId) ? 'center' : 'after';
		var step = this.initSegNum;
		$('#outer').addClass('loading');
		var seg = (options.segmentToOpen) ? options.segmentToOpen : this.startSegmentId;

		return APP.doRequest({
			data: {
				action: 'getSegments',
				jid: config.id_job,
				password: config.password,
				step: 40,
				// step: step,
				segment: seg,
				where: where
			},
			error: function() {
                OfflineUtils.failedConnection(0, 'getSegments');
			},
			success: function(d) {
                $(document).trigger('segments:load', d.data);

                if (Cookies.get('tmpanel-open') == '1') UI.openLanguageResourcesPanel();
				UI.getSegments_success(d, options);

			}
		});
	},
	getSegments_success: function(d, options) {
        var startSegmentId;
        if (d.errors.length) {
			this.processErrors(d.errors, 'getSegments');
        }

		var where = d.data.where;

		if (!this.startSegmentId){
            var firstFile = d.data.files[Object.keys(d.data.files)[0]];
            this.startSegmentId = firstFile.segments[0].sid;
        }
		this.body.addClass('loaded');

		if (typeof d.data.files !== 'undefined') {

			this.renderFiles(d.data.files, where, UI.firstLoad);
			if ((options.openCurrentSegmentAfter) && (!options.segmentToOpen)) {
                var seg = (UI.firstLoad) ? this.currentSegmentId : UI.startSegmentId;
                SegmentActions.openSegment(seg);
			}

			// if (options.segmentToOpen && UI.segmentIsLoaded(options.segmentToOpen)) {
            //     SegmentActions.scrollToSegment( options.segmentToOpen );
            //     SegmentActions.openSegment(options.segmentToOpen);
			// }

			// if (options.applySearch) {
			// 	$('mark.currSearchItem').removeClass('currSearchItem');
			// 	SearchUtils.markSearchResults(options);
			// 	if (SearchUtils.searchMode == 'normal') {
			// 		$('section[id^="segment-' + options.segmentToOpen + '"] mark.searchMarker').first().addClass('currSearchItem');
			// 	} else {
			// 		$('section[id^="segment-' + options.segmentToOpen + '"] .targetarea mark.searchMarker').first().addClass('currSearchItem');
			// 	}
			// }
		}
		$('#outer').removeClass('loading loadingBefore');

		this.loadingMore = false;
        CatToolActions.updateFooterStatistics();
        $(document).trigger('getSegments_success');

	},

    // Update the translations if job is splitted
	getUpdates: function() {
		if (UI.chunkedSegmentsLoaded()) {
			var lastUpdateRequested = UI.lastUpdateRequested;
			UI.lastUpdateRequested = new Date();
			APP.doRequest({
				data: {
					action: 'getUpdatedTranslations',
					last_timestamp: lastUpdateRequested.getTime(),
					first_segment: UI.getSegmentId($('section').first()),
					last_segment: UI.getSegmentId($('section').last()),
                    id_job: config.id_job,
                    password: config.password
				},
				error: function() {
                    OfflineUtils.failedConnection(0, 'getUpdatedTranslations');
				},
				success: function(d) {
					UI.lastUpdateRequested = new Date();
					UI.updateSegments(d.data);
				}
			});
		}

		setTimeout(function() {
			UI.getUpdates();
		}, UI.checkUpdatesEvery);
	},

	updateSegments: function(segments) {
		$.each(segments, function() {
			SegmentActions.replaceEditAreaTextContent(this.sid, this.translation);
            SegmentActions.setStatus(this.sid, null, this.status.lowercase());
		});
	},

    /**
     * removed the #outer div, taking care of extra cleaning needed, like unmounting
     * react components, closing side panel etc.
     */
    unmountSegments : function() {
        $('.article-segments-container').each(function (index, value) {
            ReactDOM.unmountComponentAtNode(value);
            delete UI.SegmentsContainers;
        });
        this.removeCacheObjects();
        SegmentStore.removeAllSegments();
        SegmentActions.closeSideSegments();
        $('#outer').empty();
    },

	pointBackToSegment: function(segmentId) {
		if (segmentId === '') {
			this.startSegmentId = config.last_opened_segment;
            UI.unmountSegments();
			this.render();
		} else {
            UI.unmountSegments();
			this.render();
		}
	},
	renderFiles: function(files, where, starting) {
        // If we are going to re-render the articles first we remove them
        if (where === "center" && !starting) {
            this.unmountSegments();
        }
        var segments = [];
        var self = this;
        $.each(files, function(k) {
			var newFile = '';
			var articleToAdd = !$( '#file' ).length;
			if (articleToAdd) {
				newFile += '<article id="file" class="loading mbc-commenting-closed">' +
                        '   <div class="article-segments-container"></div>' +
                        '</article>';
			}

			if (articleToAdd) {
                $('#outer').append(newFile);
                $('#outer').append('   <div id="loader-getMoreSegments"/>' );
            }
			segments = segments.concat(this.segments);

		});
        UI.renderSegments(segments, false, where);
        $(document).trigger('files:appended');

		if (starting) {
			this.init();
            // LXQ.getLexiqaWarnings();
		}

	},

    getSegmentStructure: function() {
        return '<section  class="status-draft hasTagsToggle hasTagsAutofill"> <div class="sid"> <div class="txt">0000000</div> <div class="txt' +
            ' segment-add-inBulk"> <input type="checkbox"> </div> <div class="actions"> <button class="split" href="#" title="Click to split segment"> <i class="icon-split"></i> </button> <p class="split-shortcut">CTRL + S</p> </div> </div> <div class="body"> <div class="header toggle"></div> <div class="text segment-body-content"> <div class="wrap"> <div class="outersource"> <div class="source item" tabindex="0"> </div> <div class="copy" title="Copy source to target"><a href="#"></a><p>CTRL+I</p></div> <div class="target item"> <div class="textarea-container"> <div class="targetarea editarea" contenteditable="true" spellcheck="true"> </div> <div class="toolbar"> <a class="revise-qr-link" title="Segment Quality Report." target="_blank" href="/revise-summary/1143-d1bd30bcde1c?revision_type=1&amp;id_segment=898088">QR</a> <a href="#" class="tagModeToggle " alt="Display full/short tags" title="Display full/short tags"><span class="icon-chevron-left"></span><span class="icon-tag-expand"></span><span class="icon-chevron-right"></span></a> <a href="#" class="autofillTag" alt="Copy missing tags from source to target" title="Copy missing tags from source to target"></a> <ul class="editToolbar"> <li class="uppercase" title="Uppercase"></li> <li class="lowercase" title="Lowercase"></li> <li class="capitalize" title="Capitalized"></li> </ul> </div> </div> <p class="warnings"></p> <ul class="buttons toggle"> <li><a href="#" class="translated"> Translated </a><p>CTRL ENTER</p></li> </ul> </div> </div> </div> <div class="status-container"> <a href="#" class="status no-hover"></a> </div> </div> <div class="timetoedit" data-raw-time-to-edit="0"></div> <div class="edit-distance">Edit Distance: </div> </div> <div class="segment-side-buttons"> <div data-mount="translation-issues-button" class="translation-issues-button"></div> </div> <div class="segment-side-container"></div> </section>'
    },

    renderSegments: function (segments, justCreated, where) {

        if((typeof this.split_points_source == 'undefined') || (!this.split_points_source.length) || justCreated) {
            if ( !this.SegmentsContainers  ) {
                if (!this.SegmentsContainers) {
                    this.SegmentsContainers = [];
                }
                var mountPoint = $(".article-segments-container")[0];
                this.SegmentsContainers[0] = ReactDOM.render(React.createElement(SegmentsContainer, {
                    // fid: fid,
                    isReview: Review.enabled(),
                    isReviewExtended: ReviewExtended.enabled(),
                    reviewType: Review.type,
                    enableTagProjection: UI.enableTagProjection,
                    tagModesEnabled: UI.tagModesEnabled,
                    startSegmentId: this.startSegmentId
                }), mountPoint);
                SegmentActions.renderSegments(segments, this.startSegmentId);
            } else {
                SegmentActions.addSegments(segments, where);
            }
            UI.registerFooterTabs();
        }
    },

	renderAndScrollToSegment: function(sid) {
        var segment = SegmentStore.getSegmentByIdToJS(sid);
        if ( segment ) {
            SegmentActions.openSegment(sid);
        } else {
            UI.unmountSegments();
            this.render({
                caller: 'link2file',
                segmentToOpen: sid,
                scrollToFile: true
            });
        }
	},

    getTranslationMismatches: function (id_segment) {
        APP.doRequest({
            data: {
                action: 'getTranslationMismatches',
                password: config.password,
                id_segment: id_segment.toString(),
                id_job: config.id_job
            },
            context: id_segment,
            error: function(d) {
                OfflineUtils.failedConnection(this, 'getTranslationMismatches');
            },
            success: function(d) {
                if (d.errors.length) {
                    UI.processErrors(d.errors, 'setTranslation');
                } else {
                    UI.detectTranslationAlternatives(d, id_segment);
                }
            }
        });
    },

    detectTranslationAlternatives: function(d, id_segment) {
        /**
         *
         * the three rows below are commented because business logic has changed, now auto-propagation info
         * is sent as response in getMoreSegments and added as data in the "section" Tag and
         * rendered/prepared in renderFiles/createHeader
         * and managed in propagateTranslation
         *
         * TODO
         * I leave them here but they should be removed
         *
         * @see renderFiles
         * @see createHeader
         * @see propagateTranslation
         *
         */
        var sameContentIndex = -1;
        var segmentObj = SegmentStore.getSegmentByIdToJS(id_segment)
        $.each(d.data.editable, function(ind) {
            if( this.translation === segmentObj.translation ) {
                sameContentIndex = ind;
            }
        });
        if(sameContentIndex != -1) d.data.editable.splice(sameContentIndex, 1);

        let sameContentIndex1 = -1;
        $.each(d.data.not_editable, function(ind) {
            //Remove trailing spaces for string comparison
            if( this.translation === segmentObj.translation ) {
                sameContentIndex1 = ind;
            }
        });
        if(sameContentIndex1 != -1) d.data.not_editable.splice(sameContentIndex1, 1);

        var numAlt = d.data.editable.length + d.data.not_editable.length;
        var numSeg = 0;
        $.each(d.data.editable, function() {
            numSeg += this.involved_id.length;
        });
        if(numAlt) {
            // UI.renderAlternatives(d);
            SegmentActions.setAlternatives(id_segment, d.data);
            SegmentActions.activateTab(id_segment, 'alternatives');
            SegmentActions.setTabIndex(id_segment, 'alternatives', numAlt);
        }
    },

	chunkedSegmentsLoaded: function() {
		return $('section.readonly:not(.ice-locked)').length;
	},

    setTimeToEdit: function(sid) {
        let $segment = UI.getSegmentById(sid);
        this.editStop = new Date();
        var tte = $('.timetoedit', $segment);
        this.editTime = this.editStop - this.editStart;
        this.totalTime = this.editTime + tte.data('raw-time-to-edit');
        var editedTime = CommonUtils.millisecondsToTime(this.totalTime);
        if (config.time_to_edit_enabled) {
            var editSec = $('.timetoedit .edit-sec', $segment);
            var editMin = $('.timetoedit .edit-min', $segment);
            editMin.text(APP.zerofill(editedTime[0], 2));
            editSec.text(APP.zerofill(editedTime[1], 2));
        }
        tte.data('raw-time-to-edit', this.totalTime);
    },
	goToFirstError: function() {
        $("#point2seg").trigger('mousedown');
        setTimeout(function (  ) {
            $('.qa-issues-container ').first().click()
        }, 300);
	},
    setDownloadStatus: function(stats) {
        var t = CommonUtils.getTranslationStatus( stats );

        var downloadable = (t === 'translated' || t.indexOf('approved') > -1 ) ;

        var isGDriveFile = false;

        if ( config.isGDriveProject && config.isGDriveProject !== 'false') {
            isGDriveFile = true;
        }

        var label = '';

        if ( downloadable ) {
            if(isGDriveFile){
                label = 'Open in Google Drive';
            } else {
                label = 'Download Translation';
            }
            $('#action-download').addClass('job-completed');
        } else {
            if(isGDriveFile){
                label = 'Preview in Google Drive';
            } else {
                label = 'Draft';
            }
            $('#action-download').removeClass('job-completed');
        }


        $('#action-download .downloadTranslation a').text(label);
        $('#action-download .previewLink a').text(label);
    },
    disableDownloadButtonForDownloadStart : function( openOriginalFiles ) {
        $("#action-download").addClass('disabled' );
    },

    reEnableDownloadButton : function() {
        $("#action-download").removeClass('disabled');
    },

    downloadFileURL : function( openOriginalFiles ) {
        return sprintf( '%s?action=downloadFile&id_job=%s&password=%s&original=%s',
            config.basepath,
            config.id_job,
            config.password,
            openOriginalFiles
        );
    },

    continueDownloadWithGoogleDrive : function ( openOriginalFiles ) {
        if ($('#downloadProject').hasClass('disabled')) {
            return;
        }
        UI.disableDownloadButtonForDownloadStart(openOriginalFiles);

        APP.downloadGDriveFile(openOriginalFiles, config.id_job, config.password,  UI.reEnableDownloadButton);
    },

    continueDownload: function() {
        if ( $('#downloadProject').hasClass('disabled') ) {
            return ;
        }

        //UI.showDownloadCornerTip();

        UI.disableDownloadButtonForDownloadStart();

        APP.downloadFile(config.id_job, config.password, UI.reEnableDownloadButton.bind(this));

    },
    showFixWarningsOnDownload: function( continueDownloadFunction ) {
        APP.confirm({
            name: 'confirmDownload', // <-- this is the name of the function that gets invoked?
            cancelTxt: 'Fix errors',
            onCancel: 'goToFirstError',
            callback: continueDownloadFunction,
            okTxt: 'Download anyway',
            msg: 'Unresolved issues may prevent downloading your translation. <br>Please fix the issues. <a style="color: #4183C4; font-weight: 700; text-decoration: underline;"' +
                ' href="https://site.matecat.com/support/advanced-features/understanding-fixing-tag-errors-tag-issues-matecat/" target="_blank">How to fix tags in MateCat </a> <br /><br /> If you' +
                ' continue downloading, part of the content may be untranslated - look for the string UNTRANSLATED_CONTENT in the downloaded files.'
        });
    },

    runDownload: function() {
        var continueDownloadFunction ;

        if( $('#downloadProject').hasClass('disabled') ) return false;

        if ( config.isGDriveProject ) {
            continueDownloadFunction = 'continueDownloadWithGoogleDrive';
        }
        else  {
            continueDownloadFunction = 'continueDownload';
        }

        //the translation mismatches are not a severe Error, but only a warn, so don't display Error Popup
        if ( $("#notifbox").hasClass("warningbox") && UI.globalWarnings.ERROR && UI.globalWarnings.ERROR.total > 0 ) {
            UI.showFixWarningsOnDownload(continueDownloadFunction);
        } else {
            UI[ continueDownloadFunction ]();
        }
    },
	startWarning: function() {
		clearTimeout(UI.startWarningTimeout);
		UI.startWarningTimeout = setTimeout(function() {
            // If the tab is not active avoid to make the warnings call
            if (document.visibilityState === "hidden") {
                UI.startWarning();
            } else {
                UI.checkWarnings(false);
            }
		}, config.warningPollingInterval);
	},

	checkWarnings: function(openingSegment) {
		var dd = new Date();
		var ts = dd.getTime();
		var seg = (typeof this.currentSegmentId == 'undefined') ? this.startSegmentId : this.currentSegmentId;
		var token = seg + '-' + ts.toString();
        var dataMix = {
            action: 'getWarning',
            id_job: config.id_job,
            password: config.password,
            token: token
        };

        // var mock = {
        //     ERRORS: {
        //         categories: {
        //             'TAG': ['23853','23854','23855','23856','23857'],
        //         }
        //     },
        //     WARNINGS: {
        //         categories: {
        //             'TAG': ['23857','23858','23859'],
        //             'GLOSSARY': ['23860','23863','23864','23866',],
        //             'MISMATCH': ['23860','23863','23864','23866',]
        //         }
        //     },
        //     INFO: {
        //         categories: {
        //         }
        //     }
        // };

        APP.doRequest({
            data: dataMix,
            error: function() {
                UI.warningStopped = true;
                OfflineUtils.failedConnection(0, 'getWarning');
            },
            success: function(data) {//console.log('check warnings success');
                UI.startWarning();

                UI.globalWarnings = data.details;
                //The tags with tag projection enabled doesn't show the tags in the source, so dont show the warning

                //check for errors
                if(data.details){
                    SegmentActions.updateGlobalWarnings(data.details);
                }

                // check for messages
                if ( data.messages ) {
                    var msgArray = $.parseJSON(data.messages);
                    if (msgArray.length > 0) {
                        UI.displayMessage(msgArray);
                    }
                }

                $(document).trigger('getWarning:global:success', { resp : data }) ;

                SegmentActions.updateGlossaryData(data.data);

            }
        });
	},
	displayMessage: function(messages) {
        var self = this;
		if($('body').hasClass('incomingMsg')) return false;
        $.each(messages, function() {
            var elem = this;
            if(typeof Cookies.get('msg-' + this.token) == 'undefined' && ( new Date( this.expire ) > ( new Date() ) ) &&
                (typeof self.displayedMessages !== 'undefined' && self.displayedMessages.indexOf(this.token) < 0 )) {
                var notification = {
                    title: 'Notice',
                    text: this.msg,
                    type: 'warning',
                    autoDismiss: false,
                    position: "bl",
                    allowHtml: true,
                    closeCallback: function () {
                        var expireDate = new Date(elem.expire);
                        Cookies.set('msg-' + elem.token, '', {expires: expireDate, secure: true });
                    }
                };
                APP.addNotification(notification);
                self.displayedMessages.push(elem.token);
                return false;
            }
        });
	},
	checkVersion: function() {
		if(this.version != config.build_number) {
            var notification = {
                title: 'New version of MateCat',
                text: 'A new version of MateCat has been released. Please <a href="#" class="reloadPage">click here</a> or press CTRL+F5 (or CMD+R on Mac) to update.',
                type: 'warning',
                allowHtml: true,
                position: "bl"
            };
            APP.addNotification(notification);
		}
	},
    registerQACheck: function() {
        clearTimeout(UI.pendingQACheck);
        UI.pendingQACheck = setTimeout(function() {
            UI.segmentQA(UI.currentSegment);
        }, config.segmentQACheckInterval);
    },
    segmentQA : function( $segment ) {
	    if ( UI.tagMenuOpen ) return;

	    var segment = SegmentStore.getSegmentByIdToJS(UI.getSegmentId($segment));
        if ( !segment ) return;
		var dd = new Date();
		var ts = dd.getTime();
		var token = segment.sid + '-' + ts.toString();

        var segment_status = segment.status;

        var src_content = segment.updatedSource.replace(/&lt;/g,'<').replace(/&gt;/g,'>');
        var trg_content = segment.translation.replace(/&lt;/g,'<').replace(/&gt;/g,'>');

		APP.doRequest({
			data: {
				action: 'getWarning',
				id: segment.sid,
				token: token,
                id_job: config.id_job,
				password: config.password,
				src_content: src_content,
				trg_content: trg_content,
                segment_status: segment_status,
			},
			error: function() {
                OfflineUtils.failedConnection(0, 'getWarning');
			},
			success: function(d) {
			    if (UI.editAreaEditing) return;
			    if(d.details && d.details.id_segment){
                    SegmentActions.setSegmentWarnings(d.details.id_segment,d.details.issues_info, d.details.tag_mismatch);
                }else{
                    SegmentActions.setSegmentWarnings(segment.original_sid, {}, {});
                }
                $(document).trigger('getWarning:local:success', { resp : d, segment: segment    }) ;
			}
		}, 'local');

	},

    translationIsToSave : function( segment ) {
        // add to setTranslation tail
        var alreadySet = this.alreadyInSetTranslationTail( segment.sid );
        var emptyTranslation = ( segment && segment.translation.length === 0 );

        return ( !alreadySet && !emptyTranslation );
    },

    translationIsToSaveBeforeClose : function( segment ) {
        // add to setTranslation tail
        var alreadySet = this.alreadyInSetTranslationTail( segment.sid );
        var emptyTranslation = ( segment && segment.translation.length === 0 );

        return ( !alreadySet && !emptyTranslation && segment.modified && ( segment.status === config.status_labels.NEW.toUpperCase() || segment.status === config.status_labels.DRAFT.toUpperCase() ) );
    },

    setTranslation: function(options, callback) {
        var id_segment = options.id_segment;
        var status = options.status;
        var caller = options.caller || false;
        var propagate = options.propagate || false;

        var segment = SegmentStore.getSegmentByIdToJS( id_segment );

        if (!segment) {
            return;
        }

        //REMOVED Check for to save
        //Send ALL to the queue
        var item = {
            id_segment: id_segment,
            status: status,
            caller: caller,
            propagate: propagate,
            autoPropagation: options.autoPropagation
        };
        //Check if the traslation is not already in the tail
        var saveTranslation = this.translationIsToSave( segment );
        // If not i save it or update
        if( saveTranslation ) {
            this.addToSetTranslationTail( item );
        } else {
            this.updateToSetTranslationTail( item )
        }

        // If is offline and is in the tail I decrease the counter
        // else I execute the tail
        if ( OfflineUtils.offline && config.offlineModeEnabled ) {
            if ( saveTranslation ) {
                OfflineUtils.decrementOfflineCacheRemaining();
                options.callback = OfflineUtils.incrementOfflineCacheRemaining;
                OfflineUtils.failedConnection( options, 'setTranslation' );
            }
            OfflineUtils.changeStatusOffline( id_segment );
            OfflineUtils.checkConnection( 'Set Translation check Authorized' );
            if (callback) {
                callback.call(this);
            }
        } else {
            if ( this.executingSetTranslation.indexOf(id_segment) === -1 )  {

                return this.execSetTranslationTail(callback);
            }
        }
    },
    alreadyInSetTranslationTail: function (sid) {
        var alreadySet = false;
        $.each(UI.setTranslationTail, function (index) {
            if(this.id_segment == sid) alreadySet = true;
        });
        return alreadySet;
    },

    addToSetTranslationTail: function (item) {
        SegmentActions.addClassToSegment(item.id_segment, 'setTranslationPending');
        this.setTranslationTail.push(item);
    },
    updateToSetTranslationTail: function (item) {
        SegmentActions.addClassToSegment(item.id_segment, 'setTranslationPending');

        $.each( UI.setTranslationTail, function (index) {
            if( this.id_segment == item.id_segment ) {
                this.status   = item.status;
                this.caller   = item.caller;
                this.callback = item.callback;
                this.propagate = item.propagate;
            }
        });
    },
    execSetTranslationTail: function ( callback_to_execute ) {
        if ( UI.setTranslationTail.length ) {
            var item = UI.setTranslationTail[0];
            UI.setTranslationTail.shift(); // to move on ajax callback
            return UI.execSetTranslation(item, callback_to_execute);
        }
    },

    execSetTranslation: function(options, callback_to_execute) {
        var id_segment = options.id_segment;
        var status = options.status;
        var caller = options.caller;
        var propagate = options.propagate;
        var sourceSegment, translation;
        this.executingSetTranslation.push(id_segment);
        var reqArguments = arguments;
		var segment = SegmentStore.getSegmentByIdToJS(id_segment);
		var contextBefore = UI.getContextBefore(id_segment);
        var idBefore = UI.getIdBefore(id_segment);
        var contextAfter = UI.getContextAfter(id_segment);
        var idAfter = UI.getIdAfter(id_segment);

        this.lastTranslatedSegmentId = id_segment;


		caller = (typeof caller == 'undefined') ? false : caller;
        try {
            // Attention, to be modified when we will lock tags
            translation = TagUtils.prepareTextToSend( segment.translation );
            sourceSegment = TagUtils.prepareTextToSend( segment.segment );
        } catch ( e ) {
            var indexSegment = UI.executingSetTranslation.indexOf(id_segment);
            if (indexSegment > -1) {
                UI.executingSetTranslation.splice(indexSegment, 1);
            }
            return false;
        }
		if (translation === '') {
            this.unsavedSegmentsToRecover.push(this.currentSegmentId);
            var index = this.executingSetTranslation.indexOf(id_segment);
            if (index > -1) {
                this.executingSetTranslation.splice(index, 1);
            }
            return false;
        }
		var time_to_edit = UI.editTime;
		var id_translator = config.id_translator;
		var autosave = (caller == 'autosave');

        var isSplitted = (segment.splitted);
        if(isSplitted) {
            translation = this.collectSplittedTranslations(segment.original_sid);
            sourceSegment = this.collectSplittedTranslations(segment.original_sid, ".source");
        }
        this.tempReqArguments = {
            id_segment: id_segment,
            id_job: config.id_job,
            password: config.password,
            status: status,
            translation: translation,
            segment : sourceSegment,
            time_to_edit: time_to_edit,
            id_translator: id_translator,
            chosen_suggestion_index: segment.choosenSuggestionIndex,
            autosave: autosave,
            version: segment.version,
            propagate: propagate,
            context_before: contextBefore,
            id_before: idBefore,
            context_after: contextAfter,
            id_after: idAfter,
            by_status: false,
            revision_number: config.revisionNumber,
            guess_tag_used: !SegmentUtils.checkCurrentSegmentTPEnabled(segment)
        };
        if(isSplitted) {
            SegmentActions.setStatus(segment.original_sid, null, status);
            this.tempReqArguments.splitStatuses = this.collectSplittedStatuses(segment.original_sid, segment.sid, status).toString();
        }
        if(!propagate) {
            this.tempReqArguments.propagate = false;
        }
        var reqData = this.tempReqArguments;
        reqData.action = 'setTranslation';
        if (callback_to_execute) {
            callback_to_execute.call(this);
        }
        return APP.doRequest({
            data: reqData,
			context: [reqArguments, options],
			error: function(response) {
                var idSegment = this[0][0].id_segment;
                var index = UI.executingSetTranslation.indexOf(idSegment);
                if (index > -1) {
                    UI.executingSetTranslation.splice(index, 1);
                }
                if ( response.status ===  409 ) {

                    SegmentActions.addClassToSegment(idSegment, 'setTranslationError');
                    var callback = function() {
                        UI.reloadToSegment(idSegment);
                    };
                    var props = {
                        text: "There was an error saving segment "+ idSegment +".</br></br>" +
                            "Press OK to refresh segments.",
                        successText: "Ok",
                        successCallback: function (  ) {
                            APP.ModalWindow.onCloseModal();
                        }
                    };
                    APP.ModalWindow.showModalComponent(ConfirmMessageModal, props, "Error saving segment", {}, callback);
                    return false;
                } else {
                    UI.addToSetTranslationTail(this[1]);
                    OfflineUtils.changeStatusOffline(this[0][0].id_segment);
                    OfflineUtils.failedConnection(this[0], 'setTranslation');
                    OfflineUtils.decrementOfflineCacheRemaining();
                }
            },
			success: function( data ) {
                var idSegment = this[0][0].id_segment;
                var index = UI.executingSetTranslation.indexOf(idSegment);
                if (index > -1) {
                    UI.executingSetTranslation.splice(index, 1);
                }
                if ( typeof callback == 'function' ) {
                    callback(data);
                }
                UI.execSetTranslationTail();
				UI.setTranslation_success(data, this[1]);

                data.translation.segment = segment;
                $(document).trigger('translation:change', data.translation);
                data.segment = segment;
                $(document).trigger('setTranslation:success', data);
                if (config.alternativesEnabled ) {
                    UI.getTranslationMismatches(id_segment);
                }
			}
		});
	},

    collectSplittedStatuses: function (sid, splittedSid, status) {
        var statuses = [];
        var segments = SegmentStore.getSegmentsInSplit(sid);
        $.each(segments, function (index) {
            var segment = SegmentStore.getSegmentByIdToJS(this.sid);
            if ( splittedSid === this.sid) {
                statuses.push(status);
            } else {
                statuses.push(segment.status);
            }
        });
        return statuses;
    },
    /**
     *
     * @param sid
     * @param selector
     * @returns {string}
     */
    collectSplittedTranslations: function (sid, selector) {
        var totalTranslation = '';
        var segments = SegmentStore.getSegmentsInSplit(sid);
        $.each(segments, function (index) {
            var segment = this;
            totalTranslation += (selector === '.source') ? segment.segment : TagUtils.prepareTextToSend(segment.translation);
            if(index < (segments.length - 1)) totalTranslation += UI.splittedTranslationPlaceholder;
        });
        return totalTranslation;
    },

    targetContainerSelector : function() {
        return '.targetarea';
    },

	processErrors: function(err, operation) {
		$.each(err, function() {
		    var codeInt = parseInt( this.code );

			if (operation === 'setTranslation') {
				if ( codeInt !== -10) {
					APP.alert({msg: "Error in saving the translation. Try the following: <br />1) Refresh the page (Ctrl+F5 twice) <br />2) Clear the cache in the browser <br />If the solutions above does not resolve the issue, please stop the translation and report the problem to <b>support@matecat.com</b>"});
				}
			}

			if ( codeInt === -10 && operation !== 'getSegments' ) {
				APP.alert({
					msg: 'Job canceled or assigned to another translator',
					callback: 'location.reload'
				});
			}
			if ( codeInt === -1000 || codeInt === -101) {
				console.log('ERROR '+ codeInt);
                OfflineUtils.startOfflineMode();
			}

            if ( codeInt <= -2000 && !_.isUndefined(this.message)) {
			    APP.alert({ msg: this.message }) ;
            }
		});
	},
	setTranslation_success: function(response, options) {
        var id_segment = options.id_segment;
        var status = options.status;
        var propagate = options.propagate;
        var segment = $('#segment-' + id_segment);

		if (response.errors.length) {
            this.processErrors(response.errors, 'setTranslation');
        } else if (response.data == 'OK') {
			SegmentActions.setStatus(id_segment, null, status);
			this.setDownloadStatus(response.stats);
			CatToolActions.setProgress(response.stats);
            SegmentActions.removeClassToSegment(options.id_segment, 'setTranslationPending');

			this.checkWarnings(false);
            $(segment).attr('data-version', response.version);

            this.tempReqArguments = null;

            UI.checkSegmentsPropagation(propagate, options.autoPropagation, id_segment, response.propagation, status);
        }
        this.resetRecoverUnsavedSegmentsTimer();
    },
    checkSegmentsPropagation: function(propagate, autoPropagate, id_segment, propagationData, status) {
        if( propagate ) {
            if ( propagationData.propagated_ids && propagationData.propagated_ids.length > 0 ) {
                SegmentActions.propagateTranslation( id_segment, propagationData.propagated_ids, status );
            }
            if ( autoPropagate ) {
                return;
            }
            var text = "The segment translation has been propagated to the other repetitions.";
            if ( propagationData.segments_for_propagation.not_propagated &&
                propagationData.segments_for_propagation.not_propagated.ice.id && propagationData.segments_for_propagation.not_propagated.ice.id.length > 0 ) {
                text = "The segment translation has been <b>propagated to the other repetitions</b>.</br> Repetitions in <b>locked segments have been excluded</b> from the propagation.";
            } else if ( propagationData.segments_for_propagation.not_propagated &&
                propagationData.segments_for_propagation.not_propagated.not_ice.id && propagationData.segments_for_propagation.not_propagated.not_ice.id.length > 0 ) {
                text = "The segment translation has been <b>propagated to the other repetitions in locked segments</b>. </br> Repetitions in <b>non-locked segments have been excluded</b> from the" +
                    " propagation."
            }

            var notification = {
                title: 'Segment propagated',
                text: text,
                type: 'info',
                autoDismiss: true,
                timer: 5000,
                allowHtml: true,
                position: "bl",
            };
            APP.removeAllNotifications();
            APP.addNotification(notification);
        } else {
            SegmentActions.setSegmentPropagation(id_segment, null, false);
        }
    },
    recoverUnsavedSetTranslations: function() {
        $.each(UI.unsavedSegmentsToRecover, function (index) {
            if($('#segment-' + this + ' .editarea').text() === '') {
                UI.resetRecoverUnsavedSegmentsTimer();
            } else {
                UI.setTranslation({
                    id_segment: this.toString(),
                    status: 'translated'
                });
                UI.unsavedSegmentsToRecover.splice(index, 1);
            }
        });
    },
    resetRecoverUnsavedSegmentsTimer: function () {
        clearTimeout(this.recoverUnsavedSegmentsTimer);
        this.recoverUnsavedSegmentsTimer = setTimeout(function() {
            UI.recoverUnsavedSetTranslations();
        }, 1000);
    },
    setTagLockCustomizeCookie: function (first) {
        if(first && !config.tagLockCustomizable) {
            UI.tagLockEnabled = true;
            return true;
        }
        var cookieName = 'tagLockDisabled';

        if(typeof Cookies.get(cookieName + '-' + config.id_job) != 'undefined') {
            if(first) {
                if(Cookies.get(cookieName + '-' + config.id_job) == 'true') {
                    this.tagLockEnabled = false;
                    setTimeout(function() {
                        $('.editor .tagLockCustomize').addClass('unlock');
                    }, 100);
                } else {
                    this.tagLockEnabled = true;
                }
            } else {
                Cookies.set(cookieName + '-' + config.id_job, !this.tagLockEnabled,  { expires: 30, secure: true  });
            }

        } else {
            Cookies.set(cookieName + '-' + config.id_job, !this.tagLockEnabled , { expires: 30, secure: true  });
        }

    },
    /**
     * After User click on Translated or T+>> Button
     * @param segment
     * @param goToNextUntranslated
     */
    clickOnTranslatedButton: function (segment, goToNextUntranslated) {
        var sid = UI.currentSegmentId;
        //??
        $('.temp-highlight-tags').remove();

        SegmentActions.removeClassToSegment( sid, 'modified' );
        UI.currentSegment.data( 'modified', false );

        UI.setTimeToEdit(segment.sid);

        var afterTranslateFn = function (  ) {
            if ( !goToNextUntranslated ) {
                UI.gotoNextSegment(); //Others functionality override this function
                // SegmentActions.openSegment(UI.nextSegmentId);
            } else {
                SegmentActions.gotoNextUntranslatedSegment();
            }
        };

        UI.changeStatus(segment, 'translated', afterTranslateFn);

    },

    // Project completion override this metod
    handleClickOnReadOnly : function(section) {
        if ( TextUtils.justSelecting('readonly') )   return;
        clearTimeout(UI.selectingReadonly);
        if ( section.hasClass('ice-locked') || section.hasClass('ice-unlocked') ) {
            UI.selectingReadonly = setTimeout(function() {
                APP.alert({ msg: UI.messageForClickOnIceMatch() });
            }, 200);
            return
        }

        UI.selectingReadonly = setTimeout(function() {
            UI.readonlyClickDisplay() ;
        }, 200);
    },
    readonlyClickDisplay : function() {
        APP.alert({ msg: UI.messageForClickOnReadonly() });
    },
    messageForClickOnReadonly : function() {
        var msgArchived = 'Job has been archived and cannot be edited.' ;
        var msgOther = 'This part has not been assigned to you.' ;
        return (UI.body.hasClass('archived'))? msgArchived : msgOther ;
    },
    messageForClickOnIceMatch : function() {
        return  'Segment is locked (in-context exact match) and shouldn’t be edited. ' +
            'If you must edit it, click on the padlock icon to the left of the segment. ' +
            'The owner of the project will be notified of any edits.' ;

    },
    openOptionsPanel: function() {
        if ($(".popup-tm").hasClass('open') ) {
            return false;
        }
        var tab = 'opt';
        $('body').addClass('side-popup');
        $(".popup-tm").addClass('open').show().animate({ right: '0px' }, 400);
        $(".outer-tm").show();
        $('.mgmt-panel-tm .nav-tabs .mgmt-' + tab).click();
    },

    closeAllMenus: function (e, fromQA) {
        CatToolActions.closeSubHeader();
    },
    // overridden by plugin
    inputEditAreaEventHandler: function (e) {
        UI.currentSegment.trigger('modified');
    }
};

$(document).ready(function() {
    UI.start();
});

$(window).resize(function() {
    // UI.fixHeaderHeightChange();
    APP.fitText($('#pname-container'), $('#pname'), 25);
});
