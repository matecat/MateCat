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
            currSegment = jobMenu.find('.currSegment');
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

        if ( !$segment.length ) {
            return;
        }

		this.lastOpenedSegment = this.currentSegment; // this.currentSegment
                                                      // seems to be the previous current segment

		this.currentSegmentId    = segment.sid ;
		this.currentSegment      = $segment ;
		this.currentFile         = $segment.closest("article");
		this.currentFileId       = this.currentFile.attr('id').split('-')[1];
    },

    removeCacheObjects: function() {
        this.editarea = "";
        this.lastOpenedSegment = undefined;
        this.currentSegmentId = undefined;
        this.lastOpenedSegmentId = undefined;
        this.currentSegment = undefined;
        this.currentFile = undefined;
        this.currentFileId = undefined;
    },

    /**
     *
     * @param el
     * @param status
     * @param byStatus
     */
	changeStatus: function(el, status, byStatus, callback) {
        var segment = $(el).closest("section");
        var segment_id = this.getSegmentId(segment);
        var segObj = SegmentStore.getSegmentByIdToJS(segment_id);
        var opts = {
            segment_id      : segment_id,
            status          : status,
            byStatus        : byStatus,
            propagation     : segObj.propagable,
            callback        : callback
        };

        // ask if the user wants propagation or this is valid only
        // for this segment

        if ( this.autopropagateConfirmNeeded() && !byStatus) {

            // var optionsStr = opts;
            var props = {
                text: "There are other identical segments. <br><br>Would you " +
                    "like to propagate the translation to all of them, " +
                    "or keep this translation only for this segment?",
                successText: 'Only this segment',
                successCallback: function(){
                        opts.propagation = false;
                        UI.preExecChangeStatus(opts);
                        APP.ModalWindow.onCloseModal();
                    },
                cancelText: 'Propagate to All',
                cancelCallback: function(){
                        opts.propagation = true;
                        UI.execChangeStatus(opts);
                        APP.ModalWindow.onCloseModal();
                    },
                onClose: function(){
                    UI.preExecChangeStatus(opts);
                }
            };
            APP.ModalWindow.showModalComponent(ConfirmMessageModal, props, "Confirmation required ");
        } else {
            this.execChangeStatus( opts ); // autopropagate
        }
	},

    autopropagateConfirmNeeded: function () {
        var segment = SegmentStore.getCurrentSegment();
        if( !segment.modified && !config.isReview) { //segment not modified
            return false;
        }

        if(segment.propagable) {
            if(config.isReview) {
                return true;
            } else {
                return segment.status.toLowerCase() !== "new" && segment.status.toLocaleString() !== "draft";
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
        var byStatus      = options.byStatus;

        ropagation = propagation || false;

        // $('.percentuage', segment.el).removeClass('visible');
        SegmentActions.hideSegmentHeader(options.segment_id);

        this.setTranslation({
            id_segment: options.segment_id,
            status: status,
            caller: false,
            byStatus: byStatus,
            propagate: propagation,
        });
        SegmentActions.removeClassToSegment(options.segment_id, 'saved');
        SegmentActions.modifiedTranslation(options.segment_id, null, false);
        if ( optStr.callback ) {
            optStr.callback();
        }
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

    createJobMenu: function() {
        var menu = '<nav id="jobMenu" class="topMenu">' +
            '<ul class="gotocurrentsegment">' +
            '<li class="currSegment" data-segment="' + UI.currentSegmentId + '"><a>Go to current segment</a><span>' +Shortcuts.cattol.events.gotoCurrent.keystrokes[Shortcuts.shortCutsKeyType].toUpperCase() + '</span></li>' +
            '<li class="firstSegment" ><a href="#"><span class="label">Go to first segment of the file</span></a></li>' +
            '</ul>' +
            '<div class="separator"></div>' +
            '<ul class="jobmenu-list">';

        var iconTick = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 12">' +
        '<path fill="#FFF" fillRule="evenodd" stroke="none" strokeWidth="1" d="M15.735.265a.798.798 0 00-1.13 0L5.04 9.831 1.363 6.154a.798.798 0 00-1.13 1.13l4.242 4.24a.799.799 0 001.13 0l10.13-10.13a.798.798 0 000-1.129z" transform="translate(-266 -10) translate(266 8) translate(0 2)" />' +
        '</svg>';

        $.each(config.firstSegmentOfFiles, function() {
            menu += '<li data-file="' + this.id_file + '" data-segment="' + this.first_segment + '"><span class="' + CommonUtils.getIconClass(this.file_name.split('.')[this.file_name.split('.').length -1]) + '"></span><a href="#" title="' + this.file_name + '" >' + this.file_name.substring(0,20) + iconTick + '</a></li>';
        });
        menu += '</ul>' +
            '</nav>';
        this.body.append(menu);
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

		if ( d.data.files.length === 0 ) {
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

			if (options.segmentToOpen && UI.segmentIsLoaded(options.segmentToOpen)) {
                SegmentActions.scrollToSegment( options.segmentToOpen );
                SegmentActions.openSegment(options.segmentToOpen);
			}

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
        this.retrieveStatistics();
        $(document).trigger('getSegments_success');

	},

    // Update the translations if job is splitted
	getUpdates: function() {
		if (UI.chunkedSegmentsLoaded()) {
			lastUpdateRequested = UI.lastUpdateRequested;
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
			SegmentActions.replaceEditAreaTextContent(this.sid, null, this.translation);
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
                        '   <div class="article-segments-container article-segments-container"></div>' +
                        '</article>';
			}

			if (articleToAdd) {
                $('#outer').append(newFile);
                $('#outer').append('   <div id="hiddenHtml" style="width: 100%; visibility: hidden; overflow-y: scroll;box-sizing: content-box;">' + self.getSegmentStructure()  + '</div>' );
            }
			segments = segments.concat(this.segments);

            /* Todo: change */
            $('#footer-source-lang').text(this.source);
            $('#footer-target-lang').text(this.target);

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
                    UI.detectTranslationAlternatives(d);
                }
            }
        });
    },

    detectTranslationAlternatives: function(d) {
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
        $.each(d.data.editable, function(ind) {
            if( this.translation == TextUtils.htmlEncode(EditAreaUtils.postProcessEditarea( UI.currentSegment ).replace( /[ \xA0]+$/ , '' )) ) {
                sameContentIndex = ind;
            }
        });
        if(sameContentIndex != -1) d.data.editable.splice(sameContentIndex, 1);

        let sameContentIndex1 = -1;
        $.each(d.data.not_editable, function(ind) {
            //Remove trailing spaces for string comparison
            if( this.translation == TextUtils.htmlEncode(EditAreaUtils.postProcessEditarea( UI.currentSegment ).replace( /[ \xA0]+$/ , '' )) ) {
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
            SegmentActions.setAlternatives(UI.getSegmentId(UI.currentSegment), d.data);
            SegmentActions.activateTab(UI.getSegmentId(UI.currentSegment), 'alternatives');
            SegmentActions.setTabIndex(UI.getSegmentId(UI.currentSegment), 'alternatives', numAlt);
        }
    },

	chunkedSegmentsLoaded: function() {
		return $('section.readonly:not(.ice-locked)').length;
	},
    formatSelection: function(op) {
        var str = CursorUtils.getSelectionHtml();
        var rangeInsert = CursorUtils.insertHtmlAfterSelection('<span class="formatSelection-placeholder"></span>');
        var newStr = '';
        var selection$ = $("<div/>").html(str);
        var rightString = selection$.html();

        $.each($.parseHTML(rightString), function(index) {
			var toAdd, d, jump, capStr;
            if(this.nodeName == '#text') {
				d = this.data;
				jump = ((!index)&&(!selection$));
				capStr = CommonUtils.toTitleCase(d);
				if(jump) {
					capStr = d.charAt(0) + CommonUtils.toTitleCase(d).slice(1);
				}
				toAdd = (op == 'uppercase')? d.toUpperCase() : (op == 'lowercase')? d.toLowerCase() : (op == 'capitalize')? capStr : d;
				newStr += toAdd;
			}
            else if(this.nodeName == 'LXQWARNING') {
                d = this.childNodes[0].data;
                jump = ((!index)&&(!selection$));
				capStr = CommonUtils.toTitleCase(d);
				if(jump) {
					capStr = d.charAt(0) + CommonUtils.toTitleCase(d).slice(1);
				}
                toAdd = (op == 'uppercase')? d.toUpperCase() : (op == 'lowercase')? d.toLowerCase() : (op == 'capitalize')? capStr : d;
				newStr += toAdd;
            }
            else {
				newStr += this.outerHTML;
			}
		});
        if (LXQ.enabled()) {
            $.powerTip.destroy($('.tooltipa',this.currentSegment));
            $.powerTip.destroy($('.tooltipas',this.currentSegment));
            CursorUtils.replaceSelectedHtml(newStr, rangeInsert);
            LXQ.reloadPowertip(this.currentSegment);
        }
        else {
            CursorUtils.replaceSelectedHtml(newStr, rangeInsert);
        }

        $('.editor .editarea .formatSelection-placeholder').after($('.editor .editarea .rangySelectionBoundary'));
        $('.editor .editarea .formatSelection-placeholder').remove();
        $('.editor .editarea').trigger('afterFormatSelection');
    },

	setStatusButtons: function(button) {
		var isTranslatedButton = ($(button).hasClass('translated')) ? true : false;
		var segment = this.currentSegment;

		var statusSwitcher = $(".status", segment);
		statusSwitcher.removeClass("col-approved col-rejected col-done col-draft");

		var nextUntranslatedSegment = $('#segment-' + this.nextUntranslatedSegmentId);
		this.nextUntranslatedSegment = nextUntranslatedSegment;
		if ((!isTranslatedButton) && (!nextUntranslatedSegment.length)) {
			$(".editor:visible").find(".close").trigger('click', 'Save');
			$('.downloadtr-button').focus();
			return false;
		}
		this.byButton = true;
	},
    setTimeToEdit: function($segment) {
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

        $('.downloadtr-button')
            .removeClass("draft translated approved")
            .addClass(t);

        var downloadable = (t === 'translated' || t.indexOf('approved') > -1 ) ;

        var isGDriveFile = false;

        if ( config.isGDriveProject && config.isGDriveProject !== 'false') {
            isGDriveFile = true;
        }

        var label = '';

        if ( downloadable ) {
            if(isGDriveFile){
                label = 'OPEN IN GOOGLE DRIVE';
            } else {
                label = 'DOWNLOAD TRANSLATION';
            }
        } else {
            if(isGDriveFile){
                label = 'PREVIEW IN GOOGLE DRIVE';
            } else {
                label = 'PREVIEW';
            }
        }

        $('.downloadtr-button').removeClass("draft translated approved").addClass(t);

        // var isDownload = (t == 'translated' || t == 'approved') ? 'true' : 'false';
        $('#downloadProject').attr('value', label);
        $('#previewDropdown').attr('data-download', downloadable);
    },
    retrieveStatistics: function () {
        var path = sprintf(
            APP.getRandomUrl() + 'api/app/jobs/%s/%s/stats',
            config.id_job, config.password
        );
        $.ajax({
            url: path,
            xhrFields: { withCredentials: true },
            type: 'get',
        }).done( function( data ) {
            if (data.stats){
                UI.setProgress(data.stats);
                UI.setDownloadStatus(data.stats);
            }
        });
    },
    setProgress: function(stats) {
        var s = stats;
        this.projectStats = stats;
        m = $('footer .meter');
        if( !s.ANALYSIS_COMPLETE ){
            $('#statistics' ).hide();
            $('#analyzing' ).show();
        } else {
            $('#statistics' ).show();
            $('#analyzing' ).hide();
        }

        var t_perc = s.TRANSLATED_PERC;
        var a_perc = s.APPROVED_PERC;
        var d_perc = s.DRAFT_PERC;
        var r_perc = s.REJECTED_PERC;

        var t_perc_formatted = s.TRANSLATED_PERC_FORMATTED;
        var a_perc_formatted = s.APPROVED_PERC_FORMATTED;
        var d_perc_formatted = s.DRAFT_PERC_FORMATTED;
        var r_perc_formatted = s.REJECTED_PERC_FORMATTED;

        var t_formatted = s.TODO_FORMATTED;
        var revise_todo_formatted = Math.round(s.TRANSLATED + s.DRAFT);
        // If second pass enabled
        if ( config.secondRevisionsCount && s.revises ) {
            var reviewedWords = s.revises.find(function ( value ) {
                return value.revision_number === 1;
            });
            if ( reviewedWords ) {
                var approvePerc = parseFloat(reviewedWords.advancement_wc)*100/s.TOTAL;
                a_perc_formatted = _.round(approvePerc, 1);
                a_perc = approvePerc;

            }

            var reviewWordsSecondPass = s.revises.find(function ( value ) {
                return value.revision_number === 2;
            });

            if ( reviewWordsSecondPass ) {
                var approvePerc2ndPass = parseFloat(reviewWordsSecondPass.advancement_wc)*100/s.TOTAL;
                a_perc_2nd_formatted = _.round(approvePerc2ndPass, 1);
                a_perc_2nd = approvePerc2ndPass;
                revise_todo_formatted = (config.revisionNumber === 2) ? revise_todo_formatted + _.round(parseFloat(reviewedWords.advancement_wc)) : revise_todo_formatted;
            }
        }



        var wph = s.WORDS_PER_HOUR;
        var completion = s.ESTIMATED_COMPLETION;

        if (typeof wph == 'undefined') {
            $('#stat-wph').hide();
        } else {
            $('#stat-wph').show();
        }
        if (typeof completion == 'undefined') {
            $('#stat-completion').hide();
        } else {
            $('#stat-completion').show();
        }

        this.progress_perc = s.PROGRESS_PERC_FORMATTED;
        this.done_percentage = this.progress_perc;

        $('.approved-bar', m).css('width', a_perc + '%').attr('title', 'Approved ' + a_perc_formatted + '%');
        $('.translated-bar', m).css('width', t_perc + '%').attr('title', 'Translated ' + t_perc_formatted + '%');
        $('.draft-bar', m).css('width', d_perc + '%').attr('title', 'Draft ' + d_perc_formatted + '%');
        $('.rejected-bar', m).css('width', r_perc + '%').attr('title', 'Rejected ' + r_perc_formatted + '%');
        if ( reviewWordsSecondPass ) {
            $('.approved-bar-2nd-pass', m).css('width', a_perc_2nd + '%').attr('title', 'Approved ' + a_perc_2nd_formatted + '%');
        }

        $('#stat-progress').html(this.progress_perc);
        if ( config.isReview ) {
            $('#stat-todo strong').html(revise_todo_formatted);
        } else {
            $('#stat-todo strong').html(t_formatted);
        }
        $('#stat-wph strong').html(wph);
        $('#stat-completion strong').html(completion);
        $('#total-payable').html(s.TOTAL_FORMATTED);

        $('.bg-loader',m).css('display', 'none');

        $(document).trigger('setProgress:rendered', { stats : stats } );
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
            msg: 'Unresolved issues may prevent downloading your translation. <br>Please fix the issues. <a style="color: #4183C4; font-weight: 700; text-decoration: underline;" href="https://www.matecat.com/support/advanced-features/understanding-fixing-tag-errors-tag-issues-matecat/" target="_blank">How to fix tags in MateCat </a> <br /><br /> If you continue downloading, part of the content may be untranslated - look for the string UNTRANSLATED_CONTENT in the downloaded files.'
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

        var mock = {
            ERRORS: {
                categories: {
                    'TAG': ['23853','23854','23855','23856','23857'],
                }
            },
            WARNINGS: {
                categories: {
                    'TAG': ['23857','23858','23859'],
                    'GLOSSARY': ['23860','23863','23864','23866',],
                    'MISMATCH': ['23860','23863','23864','23866',]
                }
            },
            INFO: {
                categories: {
                }
            }
        };

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
                        Cookies.set('msg-' + elem.token, '', {expires: expireDate});
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

		var dd = new Date();
		ts = dd.getTime();
		var token = segment.sid + '-' + ts.toString();

        segment_status = segment.status;

		if( config.brPlaceholdEnabled ){
			src_content = TagUtils.prepareTextToSend(segment.decoded_source);
			trg_content = TagUtils.prepareTextToSend(segment.decoded_translation);
		} else {
			src_content = segment.decoded_source;
			trg_content = segment.translation;
		}

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
                    SegmentActions.setSegmentWarnings(segment.sid,{}, {});
                }
                $(document).trigger('getWarning:local:success', { resp : d, segment: UI.getSegmentById( segment.sid )    }) ;
			}
		}, 'local');

	},

    translationIsToSave : function( segment ) {
        // add to setTranslation tail
        var alreadySet = this.alreadyInSetTranslationTail( segment.sid );
        var emptyTranslation = ( segment && segment.decoded_translation.length === 0 );

        return ( !alreadySet && !emptyTranslation );
    },

    translationIsToSaveBeforeClose : function( segment ) {
        // add to setTranslation tail
        var alreadySet = this.alreadyInSetTranslationTail( segment.sid );
        var emptyTranslation = ( segment && segment.decoded_translation.length === 0 );

        return ( !alreadySet && !emptyTranslation &&
            (segment.modified || ( segment.status === config.status_labels.NEW.toUpperCase() || segment.status === config.status_labels.DRAFT.toUpperCase() || config.isReview) ));
    },

    setTranslation: function(options) {
        var id_segment = options.id_segment;
        var status = options.status;
        var caller = options.caller || false;
        var callback = options.callback || false;
        var byStatus = options.byStatus || false;
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
            callback: callback,
            byStatus: byStatus,
            propagate: propagate
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
        } else {
            if ( !this.executingSetTranslation )  {
                return this.execSetTranslationTail();
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
                this.byStatus = item.byStatus;
                this.propagate = item.propagate;
            }
        });
    },
    execSetTranslationTail: function ( callback_to_execute ) {
        if ( UI.setTranslationTail.length ) {
            item = UI.setTranslationTail[0];
            UI.setTranslationTail.shift(); // to move on ajax callback
            return UI.execSetTranslation(item);
        }
    },

    execSetTranslation: function(options) {
        var id_segment = options.id_segment;
        var status = options.status;
        var caller = options.caller;
        var callback = options.callback;
        var byStatus = options.byStatus;
        var propagate = options.propagate;
        var sourceSegment;
        this.executingSetTranslation = true;
        var reqArguments = arguments;
		var segment = SegmentStore.getSegmentByIdToJS(id_segment);
		var contextBefore = UI.getContextBefore(id_segment);
        var idBefore = UI.getIdBefore(id_segment);
        var contextAfter = UI.getContextAfter(id_segment);
        var idAfter = UI.getIdAfter(id_segment);

        this.lastTranslatedSegmentId = id_segment;


		caller = (typeof caller == 'undefined') ? false : caller;

		// Attention, to be modified when we will lock tags
        translation = TagUtils.prepareTextToSend(segment.decoded_translation);
        sourceSegment = TagUtils.prepareTextToSend(segment.segment);

		if (translation === '') {
            this.unsavedSegmentsToRecover.push(this.currentSegmentId);
            this.executingSetTranslation = false;
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
            by_status: byStatus,
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
        reqData = this.tempReqArguments;
        reqData.action = 'setTranslation';

        return APP.doRequest({
            data: reqData,
			context: [reqArguments, options],
			error: function(response) {
                if ( response.status ===  409 ) {
                    UI.executingSetTranslation = false;
                    var idSegment = this[0][0].id_segment;
                    SegmentActions.addClassToSegment(idSegment, 'setTranslationError');
                    var callback = function() {
                        UI.lastOpenedSegment = null;
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
                UI.executingSetTranslation = false;
                if ( typeof callback == 'function' ) {
                    callback(data);
                }
                UI.execSetTranslationTail();
				UI.setTranslation_success(data, this[1]);

                data.translation.segment = segment;
                $(document).trigger('translation:change', data.translation);
                data.segment = segment;

                $(document).trigger('setTranslation:success', data);
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
	setTranslation_success: function(d, options) {
        var id_segment = options.id_segment;
        var status = options.status;
        var caller = options.caller || false;
        var callback = options.callback;
        var byStatus = options.byStatus;
        var propagate = options.propagate;
        var segment = $('#segment-' + id_segment);

		if (d.errors.length) {
            this.processErrors(d.errors, 'setTranslation');
        } else if (d.data == 'OK') {
			SegmentActions.setStatus(id_segment, null, status);
			this.setDownloadStatus(d.stats);
			this.setProgress(d.stats);
            SegmentActions.removeClassToSegment(options.id_segment, 'setTranslationPending');

			this.checkWarnings(false);
            $(segment).attr('data-version', d.version);
            if( propagate ) {
                this.tempReqArguments = null;
                SegmentActions.propagateTranslation(options.id_segment, status);
            }
        }
        this.resetRecoverUnsavedSegmentsTimer();
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
                Cookies.set(cookieName + '-' + config.id_job, !this.tagLockEnabled,  { expires: 30 });
            }

        } else {
            Cookies.set(cookieName + '-' + config.id_job, !this.tagLockEnabled , { expires: 30 });
        }

    },
    /**
     * After User click on Translated or T+>> Button
     * @param button
     */
    clickOnTranslatedButton: function (button) {
        var sid = UI.currentSegmentId;
        //??
        $('.temp-highlight-tags').remove();

        var goToNextUntranslated = ($( button ).hasClass( 'next-untranslated' )) ? true : false;
        SegmentActions.removeClassToSegment( sid, 'modified' );
        UI.currentSegment.data( 'modified', false );


        UI.setStatusButtons(button);

        UI.setTimeToEdit(UI.currentSegment);

        var afterTranslateFn = function (  ) {
            if ( !goToNextUntranslated ) {
                UI.gotoNextSegment(); //Others functionality override this function
                // SegmentActions.openSegment(UI.nextSegmentId);
            } else {
                SegmentActions.gotoNextUntranslatedSegment();
            }
        };

        UI.changeStatus(button, 'translated', 0, afterTranslateFn);

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
