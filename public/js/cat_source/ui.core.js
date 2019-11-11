/*
	Component: ui.core
 */
var UI = {
    pee_error_level_map: {
        0: "",
        1: "edit_1",
        2: "edit_2",
        3: "edit_3"
    },

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

        var opts = {
            segment_id      : segment_id,
            status          : status,
            byStatus        : byStatus,
            propagation     : false,
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
                return segment.status !== "NEW" && segment.status !== "DRAFT";
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
            propagate: propagation
        });
        SegmentActions.removeClassToSegment(options.segment_id, 'saved');
        UI.setSegmentModified( UI.getSegmentById(options.segment_id), false ) ;
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

    copySource: function() {
        var source_val = UI.clearMarks($.trim($(".source", this.currentSegment).html()));

        // Attention I use .text to obtain a entity conversion,
        // by I ignore the quote conversion done before adding to the data-original
        // I hope it still works.

        SegmentActions.replaceEditAreaTextContent(UI.getSegmentId(this.currentSegment), UI.getSegmentFileId(this.currentSegment), source_val);
        SegmentActions.highlightEditarea(UI.currentSegment.find(".editarea").data("sid"));
        UI.setSegmentModified(UI.currentSegment, true);
        this.segmentQA(UI.currentSegment );
        if (config.translation_matches_enabled) {
            SegmentActions.setChoosenSuggestion(UI.currentSegmentId, null);
        }
        $(this.currentSegment).trigger('copySourceToTarget');

        if(!config.isReview) {
            var alreadyCopied = false;
            $.each(UI.consecutiveCopySourceNum, function (index) {
                if(this == UI.currentSegmentId) alreadyCopied = true;
            });
            if(!alreadyCopied) {
                this.consecutiveCopySourceNum.push(this.currentSegmentId);
            }
            if(this.consecutiveCopySourceNum.length > 2) {
                this.copyAllSources();
            }
        }

    },

    copyAllSources: function() {
        if(typeof Cookies.get('source_copied_to_target-' + config.id_job + "-" + config.password) == 'undefined') {
            var props = {
                confirmCopyAllSources: UI.continueCopyAllSources.bind(this),
                abortCopyAllSources: UI.abortCopyAllSources.bind(this)
            };

            APP.ModalWindow.showModalComponent(CopySourceModal, props, "Copy source to ALL segments");
        } else {
            this.consecutiveCopySourceNum = [];
        }

    },
    continueCopyAllSources: function () {
        this.consecutiveCopySourceNum = [];
        UI.unmountSegments();
        $('#outer').addClass('loading');
        APP.doRequest({
            data: {
                action: 'copyAllSource2Target',
                id_job: config.id_job,
                pass: config.password,
                revision_number: config.revisionNumber
            },
            error: function() {
                var notification = {
                    title: 'Error',
                    text: 'Error copying all sources to target. Try again!',
                    type: 'error',
                    position: "bl"
                };
                APP.addNotification(notification);
                UI.render({
                    segmentToOpen: UI.currentSegmentId
                });
            },
            success: function(d) {
                if(d.errors.length) {
                    APP.closePopup();
                    var notification = {
                        title: 'Error',
                        text: d.errors[0].message,
                        type: 'error',
                        position: "bl"
                    };
                    APP.addNotification(notification);
                } else {
                    UI.unmountSegments();
                    UI.render({
                        segmentToOpen: UI.currentSegmentId
                    });
                }

            }
        });
    },
    abortCopyAllSources: function () {
        this.consecutiveCopySourceNum = [];
    },
    setComingFrom: function () {
        var page = (config.isReview)? 'revise' : 'translate';
        Cookies.set('comingFrom' , page, { path: '/' });
    },

    clearMarks: function (str) {
        str = str.replace(/(<mark class="inGlossary">)/gi, '').replace(/<\/mark>/gi, '');
        str = str.replace(/<span data-id="[^"]+" class="unusedGlossaryTerm">(.*?)<\/span>/gi, "$1");
        return str;
    },

	copyToNextIfSame: function(nextUntranslatedSegment) {
		if ($('.source', this.currentSegment).data('original') == $('.source', nextUntranslatedSegment).data('original')) {
			if ($('.editarea', nextUntranslatedSegment).hasClass('fromSuggestion')) {
                SegmentActions.replaceEditAreaTextContent(UI.getSegmentId(nextUntranslatedSegment), UI.getSegmentFileId(nextUntranslatedSegment), this.editarea.text());
			}
		}
	},

    createJobMenu: function() {
        var menu = '<nav id="jobMenu" class="topMenu">' +
            '<ul class="gotocurrentsegment">' +
            '<li class="currSegment" data-segment="' + UI.currentSegmentId + '"><a href="javascript:void(0)"><span class="label">Go to current segment</span></a></li>' +
            '<li class="firstSegment" ><a href="#"><span class="label">Go to first segment of the file</span></a></li>' +
            '</ul>' +
            '<div class="separator"></div>' +
            '<ul class="jobmenu-list">';

        var iconTick = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 12">' +
        '<path fill="#FFF" fillRule="evenodd" stroke="none" strokeWidth="1" d="M15.735.265a.798.798 0 00-1.13 0L5.04 9.831 1.363 6.154a.798.798 0 00-1.13 1.13l4.242 4.24a.799.799 0 001.13 0l10.13-10.13a.798.798 0 000-1.129z" transform="translate(-266 -10) translate(266 8) translate(0 2)" />' +
        '</svg>';

        $.each(config.firstSegmentOfFiles, function() {
            menu += '<li data-file="' + this.id_file + '" data-segment="' + this.first_segment + '"><span class="' + UI.getIconClass(this.file_name.split('.')[this.file_name.split('.').length -1]) + '"></span><a href="#" title="' + this.file_name + '" >' + this.file_name.substring(0,20) + iconTick + '</a></li>';
        });
        menu += '</ul>' +
            '</nav>';
        this.body.append(menu);
    },

    getIconClass: function (ext) {
        c = (
            (ext == 'doc') ||
            (ext == 'dot') ||
            (ext == 'docx') ||
            (ext == 'dotx') ||
            (ext == 'docm') ||
            (ext == 'dotm') ||
            (ext == 'odt') ||
            (ext == 'sxw')
        ) ? 'extdoc' :
            (
                (ext == 'pot') ||
                (ext == 'pps') ||
                (ext == 'ppt') ||
                (ext == 'potm') ||
                (ext == 'potx') ||
                (ext == 'ppsm') ||
                (ext == 'ppsx') ||
                (ext == 'pptm') ||
                (ext == 'pptx') ||
                (ext == 'odp') ||
                (ext == 'sxi')
            ) ? 'extppt' :
                (
                    (ext == 'htm') ||
                    (ext == 'html')
                ) ? 'exthtm' :
                    (ext == 'pdf') ? 'extpdf' :
                        (
                            (ext == 'xls') ||
                            (ext == 'xlt') ||
                            (ext == 'xlsm') ||
                            (ext == 'xlsx') ||
                            (ext == 'xltx') ||
                            (ext == 'ods') ||
                            (ext == 'sxc') ||
                            (ext == 'csv')
                        ) ? 'extxls' :
                            (ext == 'txt') ? 'exttxt' :
                                (ext == 'ttx') ? 'extttx' :
                                    (ext == 'itd') ? 'extitd' :
                                        (ext == 'xlf') ? 'extxlf' :
                                            (ext == 'mif') ? 'extmif' :
                                                (ext == 'idml') ? 'extidd' :
                                                    (ext == 'xtg') ? 'extqxp' :
                                                        (ext == 'xml') ? 'extxml' :
                                                            (ext == 'rc') ? 'extrcc' :
                                                                (ext == 'resx') ? 'extres' :
                                                                    (ext == 'sgml') ? 'extsgl' :
                                                                        (ext == 'sgm') ? 'extsgm' :
                                                                            (ext == 'properties') ? 'extpro' :
                                                                                'extxif';
        return c;
    },

	detectFirstLast: function() {
		var s = $('section');
		this.firstSegment = s.first();
		this.lastSegment = s.last();
	},
	detectRefSegId: function(where) {
        return (where == 'after') ? SegmentStore.getLastSegmentId() : (where == 'before') ? SegmentStore.getFirstSegmentId() : '';
	},
	detectStartSegment: function() {
		if (this.segmentToScrollAtRender) {
			this.startSegmentId = this.segmentToScrollAtRender;
		} else {
			var hash = CommonUtils.parsedHash.segmentId;
            config.last_opened_segment = UI.getLastSegmentFromLocalStorage();
            if (!config.last_opened_segment) {
                config.last_opened_segment = config.first_job_segment;
            }
			this.startSegmentId = (hash && hash != "") ? hash : config.last_opened_segment;
		}
	},
    getLastSegmentFromLocalStorage: function () {
        return localStorage.getItem(UI.localStorageCurrentSegmentId);
    },
    setLastSegmentFromLocalStorage: function (segmentId) {
        try {
            localStorage.setItem(UI.localStorageCurrentSegmentId, segmentId);
        } catch (e) {
            UI.clearStorage("currentSegmentId");
            localStorage.setItem(UI.localStorageCurrentSegmentId, segmentId);
        }
    },
    // fixHeaderHeightChange: function() {
    //     var headerHeight = $('header .wrapper').height();
    //     $('#outer').css('margin-top', headerHeight + 'px');
    // },

    nextUnloadedResultSegment: function() {
		var found = '';
		var last = this.getSegmentId($('section').last());
		$.each(SearchUtils.searchResultsSegments, function() {
			if ((!$('#segment-' + this).length) && (parseInt(this) > parseInt(last))) {
				found = parseInt(this);
				return false;
			}
		});
		if (found === '') {
			found = SearchUtils.searchResultsSegments[0];
		}
		return found;
	},
	footerMessage: function(msg, segment) {
		$('.footer-message', segment).remove();
		$('.submenu', segment).append('<li class="footer-message">' + msg + '</div>');
		$('.footer-message', segment).fadeOut(6000);
	},
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

		var segId = this.detectRefSegId(where);

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
				OfflineUtils.failedConnection(0, 'getMoreSegments');
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
			seg = $('#segment-' + this.sid);
			SegmentActions.replaceEditAreaTextContent(this.sid, UI.getSegmentFileId(seg), this.translation);
			status = (this.status == 'DRAFT') ? 'draft' : (this.status == 'TRANSLATED') ? 'translated' : (this.status == 'APPROVED') ? 'approved' : (this.status == 'REJECTED') ? 'rejected' : '';
			UI.setStatus(seg, status);
		});
	},

	justSelecting: function(what) {
		if (window.getSelection().isCollapsed)
			return false;
		var selContainer = $(window.getSelection().getRangeAt(0).startContainer.parentNode);
		if (what == 'editarea') {
			return ((selContainer.hasClass('editarea')) && (!selContainer.is(UI.editarea)));
		} else if (what == 'readonly') {
			return ((selContainer.hasClass('area')) || (selContainer.hasClass('source')));
		}
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

    placeCaretAtEnd: function(el) {

		 $(el).focus();
		 if (typeof window.getSelection != "undefined" && typeof document.createRange != "undefined") {
			var range = document.createRange();
			range.selectNodeContents(el);
			range.collapse(false);
			var sel = window.getSelection();
			sel.removeAllRanges();
			sel.addRange(range);
		 } else if (typeof document.body.createTextRange != "undefined") {
			var textRange = document.body.createTextRange();
			textRange.moveToElementText(el);
			textRange.collapse(false);
			textRange.select();
		 }

	},
	registerQACheck: function() {
		clearTimeout(UI.pendingQACheck);
		UI.pendingQACheck = setTimeout(function() {
			UI.segmentQA(UI.currentSegment);
		}, config.segmentQACheckInterval);
	},
	renderUntranslatedOutOfView: function() {
		config.last_opened_segment = this.nextUntranslatedSegmentId;
		var segmentToOpen = (this.nextUntranslatedSegmentId) ? this.nextUntranslatedSegmentId : this.nextSegmentId;
        window.location.hash = segmentToOpen;
        UI.unmountSegments();
		this.render({
            segmentToOpen: segmentToOpen
        });
	},
	reloadWarning: function() {
		this.renderUntranslatedOutOfView();
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
	removeButtons: function(byButton) {
		var segment = (byButton) ? this.currentSegment : this.lastOpenedSegment;
		$('#' + segment.attr('id') + '-buttons').empty();segmentPointer
		$('p.warnings', segment).empty();
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

    _treatTagsAsBlock: function ( mainStr, transDecoded, replacementsMap ) {

        var placeholderPhRegEx = /(&lt;ph id="mtc_.*?\/&gt;)/g;
        var reverseMapElements = {};

        var listMainStr = mainStr.match( placeholderPhRegEx );

        if( listMainStr === null ){
            return [ mainStr, transDecoded, replacementsMap ];
        }

        /**
         * UI.execDiff works at character level, when a tag differs only for a part of it in the source/translation it breaks the tag
         * Ex:
         *
         * Those 2 tags differs only by their IDs
         *
         * Original string: &lt;ph id="mtc_1" equiv-text="base64:JXt1c2VyX2NvbnRleHQuZGltX2NpdHl8fQ=="/&gt;
         * New String:      &lt;ph id="mtc_2" equiv-text="base64:JXt1c2VyX2NvbnRleHQuZGltX2NpdHl8fQ=="/&gt;
         *
         * After the dom rendering of the TextUtils.dmp.diff_prettyHtml function
         *
         *  <span contenteditable="false" class="locked style-tag ">
         *      <span contenteditable="false" class="locked locked-inside tag-html-container-open">&lt;ph id="mtc_</span>
         *
         *      <!-- ###### the only diff is the ID of the tag ###### -->
         *      <del class="diff">1</del>
         *      <ins class="diff">2</ins>
         *      <!-- ###### the only diff is the ID of the tag ###### -->
         *
         *      <span>" equiv-text="base64:JXt1c2VyX2NvbnRleHQuZGltX2NpdHl8fQ==</span>
         *      <span contenteditable="false" class="locked locked-inside inside-attribute" data-original="base64:JXt1c2VyX2NvbnRleHQuZGltX2NpdHl8fQ=="></span>
         *  </span>
         *
         *  When this happens, the function TagUtils.transformTextForLockTags fails to find the PH tag by regexp and do not lock the tags or lock it in a wrong way
         *
         *  So, transform the string in a single character ( Private Use Unicode char ) for the diff function, place it in a map and reinsert in the diff_obj after the UI.execDiff executed
         *
         * //U+E000..U+F8FF, 6,400 Private-Use Characters Unicode, should be impossible to have those in source/target
         */
        var charCodePlaceholder = 57344;

        listMainStr.forEach( function( element ) {

            var actualCharCode = String.fromCharCode( charCodePlaceholder );

            /**
             * override because we already have an element in the map, so the content is the same
             * ( duplicated TAG, should be impossible but it's easy to cover the case ),
             * use such character
             */
            if ( reverseMapElements[element] ) {
                actualCharCode = reverseMapElements[element];
            }

            replacementsMap[actualCharCode] = element;
            reverseMapElements[element] = actualCharCode; // fill the reverse map with the current element ( override if equal )
            mainStr = mainStr.replace( element, actualCharCode );
            charCodePlaceholder++;
        } );

        var listTransDecoded = transDecoded.match( placeholderPhRegEx );
        listTransDecoded.forEach( function( element ) {

            var actualCharCode = String.fromCharCode( charCodePlaceholder );

            /**
             * override because we already have an element in the map, so the content is the same
             * ( tag is present in source and target )
             * use such character
             */
            if ( reverseMapElements[element] ) {
                actualCharCode = reverseMapElements[element];
            }

            replacementsMap[actualCharCode] = element;
            reverseMapElements[element] = actualCharCode; // fill the reverse map with the current element ( override if equal )
            transDecoded = transDecoded.replace( element, actualCharCode );
            charCodePlaceholder++;
        } );

        return [ mainStr, transDecoded, replacementsMap ];

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
		this.copyToNextIfSame(nextUntranslatedSegment);
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
	fillWarnings: function(segment, warnings) {
		//add Warnings to current Segment
		var parentTag = segment.find('p.warnings').parent();
		var actualWarnings = segment.find('p.warnings');

		$.each(warnings, function(key, value) {

            var warningMessage = '<p class="warnings">' + value.debug;

            if(value.tip != "") {
                warningMessage += '<span class="tip">' + value.tip + '</span>' ;
            }
            warningMessage += '</p>' ;

            parentTag.before(actualWarnings).append( warningMessage );
		});
		actualWarnings.remove();

	},
	/**
	 * Walk Warnings to fill right segment
	 *
	 * @returns {undefined}
	 */
	fillCurrentSegmentWarnings: function(segment, warningDetails, global) {
		if ( !global ) {
            UI.fillWarnings(segment, $.parseJSON(warningDetails.warnings));
		}
	},

	compareArrays: function(i1, i2) {
		$.each(i1, function(key,value) {
			t = value;
			$.each(i2, function(k,v) {
				if(t == v) {
					i1.splice(key, 1);
					i2.splice(k, 1);
					UI.compareArrays(i1, i2);
					return false;
				}
			});
		});
		return i1;
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
	showMessage: function(options) {

        APP.showMessage(options);

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
            guess_tag_used: !UI.checkCurrentSegmentTPEnabled(segment)
        };
        if(isSplitted) {
            this.setStatus(segment.status);
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
    addInStorage: function (key, val, operation) {
        if(this.isPrivateSafari) {
            item = {
                key: key,
                value: val
            };
            this.localStorageArray.push(item);
        } else {
            try {
                localStorage.setItem(key, val);
            } catch (e) {
                UI.clearStorage(operation);
                localStorage.setItem(key, val);
            }
        }
    },
    getFromStorage: function (key) {
        if(this.isPrivateSafari) {
            foundVal = 0;
            $.each(this.localStorageArray, function (index) {
                if(this.key == key) foundVal = this.value;
            });
            return foundVal || false;
        } else {
            return localStorage.getItem(key);
        }
    },
    removeFromStorage: function (key) {
        if(this.isPrivateSafari) {
            foundVal = 0;
            $.each(this.localStorageArray, function (index) {
                if(this.key == key) foundIndex = index;
            });
            this.localStorageArray.splice(foundIndex, 1);
        } else {
            localStorage.removeItem(key);
        }
    },


    isLocalStorageNameSupported: function () {
        var testKey = 'test', storage = window.sessionStorage;
        try {
            storage.setItem(testKey, '1');
            storage.removeItem(testKey);
            return true;
        } catch (error) {
            return false;
        }
    },


    checkInStorage: function(what) {
		var found = false;
		$.each(localStorage, function(k) {
			if(k.substring(0, what.length) === what) {
				found = true;
			}
		});
		return found;
	},

	clearStorage: function(what) {
		$.each(localStorage, function(k) {
			if(k.substring(0, what.length) === what) {
				localStorage.removeItem(k);
			}
		});
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
					callback: 'reloadPage'
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
	reloadPage: function() {
		console.log('reloadPage');
		if(UI.body.hasClass('cattool')) location.reload();
	},

	someSegmentToSave: function() {
		res = ($('section.modified').length) ? true : false;
		return res;
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
			this.setStatus(segment, status);
			this.setDownloadStatus(d.stats);
			this.setProgress(d.stats);
            SegmentActions.removeClassToSegment(options.id_segment, 'setTranslationPending');

			this.checkWarnings(false);
            $(segment).attr('data-version', d.version);
            if((!byStatus)&&(propagate)) {
                this.beforePropagateTranslation(options.id_segment, status);
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


    beforePropagateTranslation: function(segmentId, status) {
        let segment = SegmentStore.getSegmentByIdToJS(segmentId);
        if ( segment.splitted > 2 ) return false;
        UI.propagateTranslation(segment, status);
    },

    propagateTranslation: function(segment, status) {
        this.tempReqArguments = null;
        if( (status == 'translated') || (config.isReview && (status == 'approved'))){

            var segmentsInPropagation = SegmentStore.getSegmentsInPropagation(segment.segment_hash, config.isReview);
            plusApproved = (config.isReview)? ', section[data-hash=' + $(segment).attr('data-hash') + '].status-approved' : '';

            //NOTE: i've added filter .not( segment ) to exclude current segment from list to be set as draft
            $.each(segmentsInPropagation, function() {
                // $('.editarea', $(this)).html( $('.editarea', segment).html() );
                SegmentActions.replaceEditAreaTextContent(this.sid ,null , segment.translation);


                //Tag Projection: disable it if enable
                // UI.disableTPOnSegment(UI.getSegmentById(this.sid));
                SegmentActions.setSegmentAsTagged(this.sid);

                // if status is not set to draft, the segment content is not displayed
                SegmentActions.setStatus(segment.sid, null, status); // now the status, too, is propagated

                SegmentActions.setSegmentPropagation(this.sid, null, true ,segment.sid);

                LXQ.doLexiQA(this,this.sid,true,null);
            });

            //unset actual segment as autoPropagated because now it is translated
            $( segment ).data( 'autopropagated', false );
        }
    },
    setTagLockCustomizeCookie: function (first) {
        if(first && !config.tagLockCustomizable) {
            UI.tagLockEnabled = true;
            return true;
        };
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

    storeClientInfo: function () {
        clientInfo = {
            xRes: window.screen.availWidth,
            yRes: window.screen.availHeight
        };
        Cookies.set('client_info', JSON.stringify(clientInfo), { expires: 3650 });
    },

	clearUndoStack: function() {
		this.undoStack = [];
	},
	updateJobMenu: function() {
		$('#jobMenu li.current').removeClass('current');
		$('#jobMenu li:not(.currSegment)').each(function() {
			if ($(this).attr('data-file') == UI.currentFileId)
				$(this).addClass('current');
		});
		$('#jobMenu li.currSegment').attr('data-segment', UI.currentSegmentId);
	},
    isCJK: function () {
        return config.targetIsCJK;
    },
    isKorean: function () {
        var l = config.target_rfc;
        return l == 'ko-KR';
    },
    /**
     * After User click on Translated or T+>> Button
     * @param button
     */
    clickOnTranslatedButton: function (button) {
        var sid = UI.currentSegmentId;
        //??
        $('.temp-highlight-tags').remove();

        // UI.setSegmentModified( UI.currentSegment, false ) ;
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

    handleClickOnReadOnly : function(section) {
        if ( UI.justSelecting('readonly') )   return;
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
        // Cookies.set('tmpanel-open', 1, { path: '/' });
    },
    closeAllMenus: function (e, fromQA) {
        CatToolActions.closeSubHeader();
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
    /**
     * Executes the replace all for segments if all the params are ok
     * @returns {boolean}
     */
    execReplaceAll: function() {
        SearchUtils.execReplaceAll();
    },
    inputEditAreaEventHandler: function (e) {
        UI.currentSegment.trigger('modified');
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
    }
};

$(document).ready(function() {
    console.time("Time: from start()");
    UI.start();
});

$(window).resize(function() {
    // UI.fixHeaderHeightChange();
    APP.fitText($('#pname-container'), $('#pname'), 25);
});
