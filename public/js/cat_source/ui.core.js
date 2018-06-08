/*
	Component: ui.core
 */
UI = null;

UI = {
    pee_error_level_map: {
        0: "",
        1: "edit_1",
        2: "edit_2",
        3: "edit_3"
    },

    setEditingSegment : function(segment) {
        if ( segment != null ) {
            UI.body.addClass('editing');
        } else {
            UI.body.removeClass('editing');
        }
        $(document).trigger('editingSegment:change', {segment: segment});
    },

	toggleFileMenu: function() {
        var jobMenu = $('#jobMenu');
		if (jobMenu.is(':animated')) {
			return false;
		} else {
            currSegment = jobMenu.find('.currSegment');
            if (this.body.hasClass('editing')) {
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

    activateSegment: function(segment) {
        SegmentActions.createFooter(UI.getSegmentId(segment));
		this.createButtons(segment);

        $(document).trigger('segment:activate', { segment: segment } );
	},

	cacheObjects: function( editarea_or_segment ) {
        var segment;
        if ( editarea_or_segment instanceof UI.Segment ) {
            segment = editarea_or_segment ;
            this.editarea = segment.el.find( '.editarea' );
        }
        else {
            this.editarea = $(".editarea", $(editarea_or_segment).closest('section'));
            segment = new UI.Segment( $(editarea_or_segment).closest('section') );
        }

		this.lastOpenedSegment = this.currentSegment; // this.currentSegment
                                                      // seems to be the previous current segment

		this.currentSegmentId    = segment.id ;
        this.lastOpenedSegmentId = segment.id ;
		this.currentSegment      = segment.el ;
		this.currentFile         = segment.el.closest("article");
		this.currentFileId       = this.currentFile.attr('id').split('-')[1];

        this.evalCurrentSegmentTranslationAndSourceTags( segment.el );
    },


    /**
     * shouldSegmentAutoPropagate
     *
     * Returns whether or not the segment should be propagated. Default is true.
     *
     * @returns {boolean}
     */
    shouldSegmentAutoPropagate : function( $segment, newStatus ) {
        var segmentClassToFind = "status-" + newStatus.toLowerCase();
        var statusChanged = !$segment.hasClass(segmentClassToFind);
        var segmentModified = UI.currentSegmentTranslation.trim() !== UI.editarea.text().trim();
        return statusChanged || segmentModified;
    },

    /**
     *
     * @param el
     * @param status
     * @param byStatus
     */
	changeStatus: function(el, status, byStatus) {
        var segment = $(el).closest("section");
        var segment_id = this.getSegmentId(segment);

        var opts = {
            segment_id      : segment_id,
            status          : status,
            byStatus        : byStatus,
            noPropagation   : ! UI.shouldSegmentAutoPropagate( segment, status )
        };

        if ( byStatus || opts.noPropagation ) {
            opts.noPropagation = true;
            this.execChangeStatus(JSON.stringify(opts)); // no propagation
        } else {

            // ask if the user wants propagation or this is valid only
            // for this segment

            if ( this.autopropagateConfirmNeeded() ) {

                var optionsStr = JSON.stringify(opts);

                APP.confirm({
                    name: 'confirmAutopropagation',
                    cancelTxt: 'Propagate to All',
                    onCancel: 'execChangeStatus',
                    callback: 'preExecChangeStatus',
                    okTxt: 'Only this segment',
                    context: optionsStr,
                    msg: "There are other identical segments. <br><br>Would you " +
                         "like to propagate the translation to all of them, " +
                         "or keep this translation only for this segment?"
                });
            } else {
                this.execChangeStatus( JSON.stringify(opts) ); // autopropagate
            }
        }

	},

    autopropagateConfirmNeeded: function () {
        var segment = UI.currentSegment;
        if(this.currentSegmentTranslation.trim() == this.editarea.text().trim()) { //segment not modified
            return false;
        }

        if(segment.attr('data-propagable') == 'true') {
            if(config.isReview) {
                return true;
            } else {
                return !!segment.is('.status-translated, .status-approved, .status-rejected');
            }
        }
        return false;

    },
    preExecChangeStatus: function (optStr) {
        var opt = $.parseJSON(optStr);
        opt.noPropagation = true;
        this.execChangeStatus(JSON.stringify(opt));
    },
    execChangeStatus: function (optStr) {
        var options = $.parseJSON(optStr);

        var segment = UI.Segment.find( options.segment_id );

        var noPropagation = options.noPropagation;
        var status        = options.status;
        var byStatus      = options.byStatus;

        noPropagation = noPropagation || false;

        // $('.percentuage', segment.el).removeClass('visible');
        SegmentActions.hideSegmentHeader(options.segment_id, UI.getSegmentFileId(segment));

        this.setTranslation({
            id_segment: options.segment_id,
            status: status,
            caller: false,
            byStatus: byStatus,
            propagate: !noPropagation
        });
        SegmentActions.removeClassToSegment(options.segment_id, 'saved');
        UI.setSegmentModified( UI.currentSegment, false ) ;
        $(document).trigger('segment:status:change', [segment, options]);
    },

    getSegmentId: function (segment) {
        if(typeof segment == 'undefined') return false;
        if ( segment.el ) {
            return segment.el.attr('id').replace('segment-', '');
        }

        /*
         sometimes:
         typeof $(segment).attr('id') == 'undefined'

         The preeceding if doesn't works because segment is a list ==
         '[<span class="undoCursorPlaceholder monad" contenteditable="false"></span>]'

         so for now i put a try-catch block here

         TODO FIX
         */

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

    maxNumSegmentsReached : function() {
        return $('section').length > config.maxNumSegments  ;
    },

    checkIfFinished: function(closing) {
       if (((this.progress_perc != this.done_percentage) && (this.progress_perc == '100')) || ((closing) && (this.progress_perc == '100'))) {
               this.body.addClass('justdone');
       } else {
               this.body.removeClass('justdone');
       }
    },

    checkIfFinishedFirst: function() {
       if ($('section').length == $('section.status-translated, section.status-approved').length) {
           this.body.addClass('justdone');
       }
    },
    closeSegment: function(segment, byButton, operation) {
        if ( typeof segment !== 'undefined' ) {
            segment.find('.editarea').attr('contenteditable', 'false');
            SegmentActions.removeClassToSegment(UI.getSegmentId(segment), 'waiting_for_check_result opened editor split-action');

            $(window).trigger({
                type: "segmentClosed",
                segment: segment
            });

            clearTimeout(this.liveConcordanceSearchReq);

            var saveBehaviour = true;
            if (operation != 'noSave') {
                if ((operation == 'translated') || (operation == 'Save'))
                    saveBehaviour = false;
            }

            if ((segment.data('modified')) && (saveBehaviour) && (!config.isReview)) {
                this.saveSegment(segment);
            }
            this.deActivateSegment(byButton, segment);
            this.removeGlossaryMarksFormSource();

            $('span.locked.mismatch', segment).removeClass('mismatch');


            if (!this.opening) {
                this.checkIfFinished(1);
            }

            // close split segment
            $('.sid .actions .split').removeClass('cancel');
            source = $(segment).find('.source');
            $(source).removeAttr('style');

            $('.split-shortcut').html('CTRL + S');
            $('.splitBar, .splitArea').remove();
            $('.sid .actions').hide();
            // end split segment

        }
        return true;
    },

    copySource: function() {
        var source_val = UI.clearMarks($.trim($(".source", this.currentSegment).html()));

        // Test
        //source_val = source_val.replace(/&quot;/g,'"');

        // Attention I use .text to obtain a entity conversion,
        // by I ignore the quote conversion done before adding to the data-original
        // I hope it still works.

        this.saveInUndoStack('copysource');
        SegmentActions.replaceEditAreaTextContent(UI.getSegmentId(this.currentSegment), UI.getSegmentFileId(this.currentSegment), source_val);
        SegmentActions.highlightEditarea(UI.currentSegment.find(".editarea").data("sid"));
        UI.setSegmentModified(UI.currentSegment, true);
        this.segmentQA(UI.currentSegment );
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
            // APP.confirmAndCheckbox({
            //     title: 'Copy source to target',
            //     name: 'confirmCopyAllSources',
            //     okTxt: 'Yes',
            //     cancelTxt: 'No',
            //     callback: 'continueCopyAllSources',
            //     onCancel: 'abortCopyAllSources',
            //     closeOnSuccess: true,
            //     msg: "Copy source to target for all new segments?<br/><b>This action cannot be undone.</b>",
            //     'checkbox-label': "Confirm copy source to target"
            // });
        } else {
            this.consecutiveCopySourceNum = [];
        }

    },
    continueCopyAllSources: function () {
        this.consecutiveCopySourceNum = [];
        APP.doRequest({
            data: {
                action: 'copyAllSource2Target',
                id_job: config.id_job,
                pass: config.password
            },
            error: function() {
                var notification = {
                    title: 'Error',
                    text: 'Error copying all sources to target. Try again!',
                    type: 'error',
                    position: "bl"
                };
                APP.addNotification(notification);
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
	createButtons: function() {

        var button_label = config.status_labels.TRANSLATED ;
        var label_first_letter = button_label[0];
        var nextUntranslated, currentButton;

        //Tag Projection: Identify if is enabled in the current segment
        this.currentSegmentTPEnabled = this.checkCurrentSegmentTPEnabled();

		var disabled = (this.currentSegment.hasClass('loaded')) ? '' : ' disabled="disabled"';
        var nextSegment = this.currentSegment.next();
        var filtering = (SegmentFilter.enabled() && SegmentFilter.filtering() && SegmentFilter.open);
        var sameButton = (nextSegment.hasClass('status-new')) || (nextSegment.hasClass('status-draft'));
        if (this.currentSegmentTPEnabled) {
            nextUntranslated = "";
            currentButton = '<li><a id="segment-' + this.currentSegmentId +
                '-button-guesstags" data-segmentid="segment-' + this.currentSegmentId +
                '" href="#" class="guesstags"' + disabled + ' >' + 'GUESS TAGS' + '</a><p>' +
                ((UI.isMac) ? 'CMD' : 'CTRL') + '+ENTER</p></li>';
        } else {
            nextUntranslated = (sameButton || filtering)? '' : '<li><a id="segment-' + this.currentSegmentId +
                '-nextuntranslated" href="#" class="btn next-untranslated" data-segmentid="segment-' +
                this.currentSegmentId + '" title="Translate and go to next untranslated">' + label_first_letter + '+&gt;&gt;</a><p>' +
                ((UI.isMac) ? 'CMD' : 'CTRL') + '+SHIFT+ENTER</p></li>';
            currentButton = '<li><a id="segment-' + this.currentSegmentId +
                '-button-translated" data-segmentid="segment-' + this.currentSegmentId +
                '" href="#" class="translated"' + disabled + ' >' + button_label + '</a><p>' +
                ((UI.isMac) ? 'CMD' : 'CTRL') + '+ENTER</p></li>';
        }

        if (filtering) {
            var data = SegmentFilter.getStoredState();
            var filterinRepetitions = data.reactState.samplingType === "repetitions";
            if (filterinRepetitions) {
                nextUntranslated ='<li><a id="segment-' + this.currentSegmentId +
                    '-nextrepetition" href="#" class="next-repetition ui primary button" data-segmentid="segment-' +
                    this.currentSegmentId + '" title="Translate and go to next repetition">REP ></a>' +
                    '</li>' +
                    '<li><a id="segment-' + this.currentSegmentId +
                    '-nextgrouprepetition" href="#" class="next-repetition-group ui primary button" data-segmentid="segment-' +
                    this.currentSegmentId + '" title="Translate and go to next repetition group">REP >></a>' +
                    '</li>';

            }
        }

        UI.segmentButtons = nextUntranslated + currentButton;

		var buttonsOb = $('#segment-' + this.currentSegmentId + '-buttons');

        UI.currentSegment.trigger('buttonsCreation');

        buttonsOb.empty().append(UI.segmentButtons);
        buttonsOb.before('<p class="warnings"></p>');

        UI.segmentButtons = null;

	},


	/*createJobMenu: function() {
		var menu = '<nav id="jobMenu" class="topMenu">' +
				'    <ul>';
		$.each(config.firstSegmentOfFiles, function() {
			menu += '<li data-file="' + this.id_file + '" data-segment="' + this.first_segment + '"><span class="' + UI.getIconClass(this.file_name.split('.')[this.file_name.split('.').length -1]) + '"></span><a href="#" title="' + this.file_name + '" >' + this.file_name.substring(0,20).concat("[...]" ).concat((this.file_name).substring(this.file_name.length-20))  + '</a></li>';
		});

		menu += '    </ul>' +
				'	<ul>' +
				'		<li class="currSegment" data-segment="' + UI.currentSegmentId + '"><a href="#">Go to current segment</a></li>' +
				'    </ul>' +
				'</nav>';
		this.body.append(menu);
	},*/

    createJobMenu: function() {
        var menu = '<nav id="jobMenu" class="topMenu">' +
            '<ul class="gotocurrentsegment">' +
            '<li class="currSegment" data-segment="' + UI.currentSegmentId + '"><a href="javascript:void(0)">Go to current segment</a></li>' +
            '</ul>' +
            '<ul class="jobmenu-list">';
        $.each(config.firstSegmentOfFiles, function() {
            menu += '<li data-file="' + this.id_file + '" data-segment="' + this.first_segment + '"><span class="' + UI.getIconClass(this.file_name.split('.')[this.file_name.split('.').length -1]) + '"></span><a href="#" title="' + this.file_name + '" >' + this.file_name.substring(0,20).concat("[...]" ).concat((this.file_name).substring(this.file_name.length-20))  + '</a></li>';
        });
        menu += '</ul>' +
            '</nav>';
        this.body.append(menu);
    },

    handleReturn: function(e) {
        if(!this.hiddenTextEnabled) return;
        e.preventDefault();
        var node = document.createElement("span");
        var br = document.createElement("br");
        node.setAttribute('class', 'monad softReturn ' + config.lfPlaceholderClass);
        node.setAttribute('contenteditable', 'false');
        node.appendChild(br);
        insertNodeAtCursor(node);
        this.unnestMarkers();
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
	deActivateSegment: function(byButton, segment) {
		UI.removeButtons(byButton, segment);

        $(document).trigger('segment:deactivate', {
            deactivated_segment : UI.lastOpenedSegment,
            current_segment : UI.currentSegment
        });

        if( !this.opening && UI.currentSegmentId == segment.data('splitOriginalId') ) {
            Speech2Text.enabled() && Speech2Text.disableContinuousRecognizing();
        }

        Speech2Text.enabled() && Speech2Text.disableMicrophone( segment );
	},
	detectAdjacentSegment: function(segment, direction, times) { // currently unused
		if (!times)
			times = 1;
		if (direction == 'down') {
			adjacent = segment.next();
			if (!adjacent.is('section'))
				adjacent = this.currentFile.next().find('section:first');
		} else {
			adjacent = segment.prev();
			if (!adjacent.is('section'))
				adjacent = $('.editor').parents('article').prev().find('section:last');
		}

		if (adjacent.length) {
			if (times == 1) {
				return adjacent;
			} else {
				this.detectAdjacentSegment(adjacent, direction, times - 1);
                return true;
			}
		} else {
            return true;
		}
	},
	detectFirstLast: function() {
		var s = $('section');
		this.firstSegment = s.first();
		this.lastSegment = s.last();
	},
	detectRefSegId: function(where) {
//		var step = this.moreSegNum;
		var section = $('section');
        var seg = (where == 'after') ? section.last() : (where == 'before') ? section.first() : '';
		var segId = (seg.length) ? this.getSegmentId(seg) : 0;
		return segId;
	},
	detectStartSegment: function() {
		if (this.segmentToScrollAtRender) {
			this.startSegmentId = this.segmentToScrollAtRender;
		} else {
			var hash = UI.parsedHash.segmentId;
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
		$.each(this.searchResultsSegments, function() {
			if ((!$('#segment-' + this).length) && (parseInt(this) > parseInt(last))) {
				found = parseInt(this);
				return false;
			}
		});
		if (found === '') {
			found = this.searchResultsSegments[0];
		}
		return found;
	},
	footerMessage: function(msg, segment) {
		$('.footer-message', segment).remove();
		$('.submenu', segment).append('<li class="footer-message">' + msg + '</div>');
		$('.footer-message', segment).fadeOut(6000);
	},
	getMoreSegments: function(where) {

        console.log('get more segments');

		if ((where == 'after') && (this.noMoreSegmentsAfter)) {
			return;
        }

		if ((where == 'before') && (this.noMoreSegmentsBefore)) {
			return;
        }

		if ( this.loadingMore ) {
			return;
		}

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
				UI.failedConnection(0, 'getMoreSegments');
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
		if (typeof d.data.files != 'undefined') {
			var firstSeg = section.first();
			var lastSeg = section.last();
			var numsegToAdd = 0;
			$.each(d.data.files, function() {
				numsegToAdd = numsegToAdd + this.segments.length;
			});

			this.renderFiles(d.data.files, where, false);

			// if getting segments before, UI points to the segment triggering the event
			if ((where == 'before') && (numsegToAdd)) {
				this.scrollSegment($('#segment-' + this.segMoving), this.segMoving);
			}

			if (this.body.hasClass('searchActive')) {
				segLimit = (where == 'before') ? firstSeg : lastSeg;
				this.markSearchResults({
					where: where,
					seg: segLimit
				});
			}

		}

		if (d.data.files.length === 0) {
			if (where == 'after')
				this.noMoreSegmentsAfter = true;
			if (where == 'before')
				this.noMoreSegmentsBefore = true;
		}
		$('#outer').removeClass('loading loadingBefore');
		this.loadingMore = false;
		this.setWaypoints();
        $(window).trigger('segmentsAdded',{ resp : d.data.files });
	},

	getSegments: function(options) {

		var where = (this.startSegmentId) ? 'center' : 'after';
		var step = this.initSegNum;
		$('#outer').addClass('loading');
		var seg = (options.segmentToScroll) ? options.segmentToScroll : this.startSegmentId;

		return APP.doRequest({
			data: {
				action: 'getSegments',
				jid: config.id_job,
				password: config.password,
				step: step,
				segment: seg,
				where: where
			},
			error: function() {
				UI.failedConnection(0, 'getSegments');
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

        // Why here?? Investigate
        UI.setGlobalTagProjection();

		if (!this.startSegmentId){
            var firstFile = d.data.files[Object.keys(d.data.files)[0]];
            this.startSegmentId = firstFile.segments[0].sid;
        }

		this.body.addClass('loaded');

		if (typeof d.data.files !== 'undefined') {

			this.renderFiles(d.data.files, where, UI.firstLoad);
			if ((options.openCurrentSegmentAfter) && (!options.segmentToScroll) && (!options.segmentToOpen)) {
                var seg = (UI.firstLoad) ? this.currentSegmentId : UI.startSegmentId;
				this.gotoSegment(seg);
			}

			if (options.segmentToScroll && UI.segmentIsLoaded(options.segmentToScroll)) {
			    var segToScrollElem = $('#segment-' + options.segmentToScroll);
				this.scrollSegment(segToScrollElem, options.segmentToScroll, options.highlight );
				UI.openSegment(segToScrollElem);
			} else if (options.segmentToOpen) {
                $('#segment-' + options.segmentToOpen + ' ' + UI.targetContainerSelector()).click();
            }
            // else if ( UI.editarea.length && ($('#segment-' + UI.currentSegmentId).length) && (!$('#segment-' + UI.currentSegmentId).hasClass('opened'))) {
            //     UI.openSegment(UI.editarea);
            // }

			if ($('#segment-' + UI.startSegmentId).hasClass('readonly')) {
                setTimeout(function () {
                    var next = UI.findNextSegment(UI.startSegmentId);
                    if (next) {
                        UI.gotoSegment(next.attr('data-split-original-id'));
                    }
                }, 100);
			}

			if (options.applySearch) {
				$('mark.currSearchItem').removeClass('currSearchItem');
				this.markSearchResults(options);
				if (this.searchMode == 'normal') {
					$('#segment-' + options.segmentToScroll + ' mark.searchMarker').first().addClass('currSearchItem');
				} else {
					$('#segment-' + options.segmentToScroll + ' .editarea mark.searchMarker').first().addClass('currSearchItem');
				}
			}
		}
		$('#outer').removeClass('loading loadingBefore');

		this.loadingMore = false;
		this.setWaypoints();
		this.checkPendingOperations();
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
					UI.failedConnection(0, 'getUpdatedTranslations');
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
        $('[data-mount=translation-issues-button]').each( function() {
            ReactDOM.unmountComponentAtNode(this);
        });
        $('.article-segments-container').each(function (index, value) {
            ReactDOM.unmountComponentAtNode(value);
            delete UI.SegmentsContainers;
        });
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
	reloadToSegment: function(segmentId) {
		this.infiniteScroll = false;
		config.last_opened_segment = segmentId;
        UI.unmountSegments();
		this.render({ segmentToOpen : segmentId });
	},
	renderUntranslatedOutOfView: function() {
		this.infiniteScroll = false;
		config.last_opened_segment = this.nextUntranslatedSegmentId;
		window.location.hash = this.nextUntranslatedSegmentId;
        UI.unmountSegments();
		this.render();
	},
	reloadWarning: function() {
		this.renderUntranslatedOutOfView();
	},
	pointBackToSegment: function(segmentId) {
		if (!this.infiniteScroll)
			return;
		if (segmentId === '') {
			this.startSegmentId = config.last_opened_segment;
            UI.unmountSegments();
			this.render();
		} else {
            UI.unmountSegments();
			this.render();
		}
	},
	pointToOpenSegment: function(quick) {
        quick = quick || false;
        this.gotoOpenSegment(quick);
	},
	removeButtons: function(byButton) {
		var segment = (byButton) ? this.currentSegment : this.lastOpenedSegment;
		$('#' + segment.attr('id') + '-buttons').empty();
		$('p.warnings', segment).empty();
	},
	renderFiles: function(files, where, starting) {
        // If we are going to re-render the articles first we remove them
        if (where === "center" && !starting) {
            this.unmountSegments();
        }
        $.each(files, function(k) {
			var newFile = '';
			var fid = k;
			var articleToAdd = ((where == 'center') || (!$('#file-' + fid).length)) ? true : false;
            var filenametoshow ;

			if (articleToAdd) {
				filenametoshow = truncate_filename(this.filename, 40);
				newFile += '<article id="file-' + fid + '" class="loading mbc-commenting-closed">' +
						'	<ul class="projectbar" data-job="job-' + this.jid + '">' +
						'		<li class="filename">' +
						'			<form class="download" action="/" method="post">' +
						'				<input type=hidden name="action" value="downloadFile">' +
						'				<input type=hidden name="id_job" value="' + this.jid + '">' +
						'				<input type=hidden name="id_file" value="' + fid + '">' +
						'				<input type=hidden name="filename" value="' + this.filename + '">' +
						'				<input type=hidden name="password" value="' + config.password + '">' +
						'				<!--input title="Download file" type="submit" value="" class="downloadfile" id="file-' + fid + '-download" -->' +
						'			</form>' +
						'			<h2 title="' + this.filename + '">' + filenametoshow + '</div>' +
						'		</li>' +
						'		<li style="text-align:center;text-indent:-20px">' +
						'			<strong>' + this.source + '</strong> [<span class="source-lang">' + this.source_code + '</span>]&nbsp;>&nbsp;<strong>' + this.target + '</strong> [<span class="target-lang">' + this.target_code + '</span>]' +
						'		</li>' +
						'		<li class="wordcounter">' +
                        '			Payable Words: <strong>' + config.fileCounter[fid].TOTAL_FORMATTED + '</strong>' +
						'		</li>' +
						'	</ul>' +
                        '   <div class="article-segments-container-' + fid + ' article-segments-container"></div>' +
                        '</article>';
			}

			if (articleToAdd) {
				if (where == 'before') {
					if (typeof lastArticleAdded != 'undefined') {
						$('#file-' + fid).after(newFile);
					} else {
						$('article').first().before(newFile);
					}
					lastArticleAdded = fid;
				} else if (where == 'after') {
					$('article').last().after(newFile);
				} else if (where == 'center') {
					$('#outer').append(newFile);
				}
			} else {
				if (where == 'before') {
					$('#file-' + fid).prepend(newFile);
				} else if (where == 'after') {
					$('#file-' + fid).append(newFile);
				}
			}
            console.time("Time: RenderSegments"+fid);
            UI.renderSegments(this.segments, false, fid, where);
            // console.timeEnd("Time: RenderSegments"+fid);
            // console.timeEnd("Time: from start()");

		});

        $(document).trigger('files:appended');

		if (starting) {
			this.init();
            // LXQ.getLexiqaWarnings();
		}

	},

    renderSegments: function (segments, justCreated, fid, where) {

        if((typeof this.split_points_source == 'undefined') || (!this.split_points_source.length) || justCreated) {
            if ( !this.SegmentsContainers || !this.SegmentsContainers[fid] ) {
                if (!this.SegmentsContainers) {
                    this.SegmentsContainers = [];
                }
                var mountPoint = $(".article-segments-container-" + fid)[0];
                this.SegmentsContainers[fid] = ReactDOM.render(React.createElement(SegmentsContainer, {
                    fid: fid,
                    isReviewImproved: ReviewImproved.enabled() && Review.enabled(),
                    isReviewExtended: ReviewExtended.enabled() && Review.enabled(),
                    reviewType: Review.type,
                    enableTagProjection: UI.enableTagProjection,
                    decodeTextFn: UI.decodeText,
                    tagModesEnabled: UI.tagModesEnabled,
                    speech2textEnabledFn: Speech2Text.enabled,
                }), mountPoint);
                SegmentActions.renderSegments(segments, fid);
            } else {
                SegmentActions.addSegments(segments, fid, where);
            }
            UI.registerFooterTabs();
        }
    },

	renderAndScrollToSegment: function(sid) {
        UI.unmountSegments();
		this.render({
			caller: 'link2file',
			segmentToScroll: sid,
			scrollToFile: true
		});
	},
	addWord: function(word) {
		APP.doRequest({
			data: {
				action: 'setSpellcheck',
				slang: config.target_rfc,
				word: word
			}
		});
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
                UI.failedConnection(this, 'getTranslationMismatches');
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
            if( this.translation == htmlEncode(UI.postProcessEditarea( UI.currentSegment ).replace( /[ \xA0]+$/ , '' )) ) {
                sameContentIndex = ind;
            }
        });
        if(sameContentIndex != -1) d.data.editable.splice(sameContentIndex, 1);

        sameContentIndex1 = -1;
        $.each(d.data.not_editable, function(ind) {
            //Remove trailing spaces for string comparison
            if( this.translation == htmlEncode(UI.postProcessEditarea( UI.currentSegment ).replace( /[ \xA0]+$/ , '' )) ) {
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
            UI.renderAlternatives(d);
            SegmentActions.activateTab(UI.getSegmentId(UI.currentSegment), 'alternatives');
            SegmentActions.setTabIndex(UI.getSegmentId(UI.currentSegment), 'alternatives', numAlt);
        }
    },
    // TODO: refactoring React
    renderAlternatives: function(d) {
        var segment = UI.currentSegment;
        var segment_id = UI.currentSegmentId;
        var escapedSegment = UI.decodePlaceholdersToText(UI.currentSegment.find('.source').html());
        // Take the .editarea content with special characters (Ex: ##$_0A$##) and transform the placeholders
        var mainStr = htmlEncode(UI.postProcessEditarea(UI.currentSegment));
        $('.sub-editor.alternatives .overflow', segment).empty();
        $.each(d.data.editable, function(index) {
            // Decode the string from the server
            var transDecoded = this.translation;
            // Make the diff between the text with the same codification
            var diff_obj = UI.execDiff(mainStr, transDecoded);
            var translation = UI.transformTextForLockTags(UI.dmp.diff_prettyHtml(diff_obj));
            var html =
            $('.sub-editor.alternatives .overflow', segment).append('<ul class="graysmall" data-item="' + (index + 1) + '">' +
                '<li class="sugg-source">' +
                '   <span id="' + segment_id + '-tm-' + this.id + '-source" class="suggestion_source">' +
                escapedSegment + '</span>' +
                '</li>' +
                '<li class="b sugg-target">' +
                '<span class="graysmall-message">CTRL+' + (index + 1) + '</span><span class="translation"></span>' +
                '<span class="realData hide">' + this.translation +
                '</span>' +
                '</li>' +
                '<li class="goto">' +
                '<a href="#" data-goto="' + this.involved_id[0]+ '">View</a>' +
                '</li>' +
            '</ul>');
            $('.sub-editor.alternatives .overflow .graysmall[data-item='+ (index + 1) +']', segment).find('.sugg-target .translation').html(translation);
        });

        $.each(d.data.not_editable, function(index1) {
            var diff_obj = UI.execDiff(mainStr, this.translation);
            var translation = UI.transformTextForLockTags(UI.dmp.diff_prettyHtml(diff_obj));
            $('.sub-editor.alternatives .overflow', segment).append('<ul class="graysmall notEditable" data-item="' + (index1 + d.data.editable.length + 1) + '">' +
                '<li class="sugg-source"><span id="' + segment_id + '-tm-' + this.id + '-source" class="suggestion_source">' + escapedSegment + '</span></li>' +
                '<li class="b sugg-target"><!-- span class="switch-editing">Edit</span --><span class="graysmall-message">CTRL+' + (index1 + d.data.editable.length + 1) + '</span>' +
                '<span class="translation">' + translation + '</span><span class="realData hide">' + this.translation + '</span></li>' +
                '<li class="goto"><a href="#" data-goto="' + this.involved_id[0]+ '">View</a></li></ul>');
        });
    },
    execDiff: function (mainStr, cfrStr) {
        _str = cfrStr.replace( config.lfPlaceholderRegex, "\n" )
            .replace( config.crPlaceholderRegex, "\r" )
            .replace( config.crlfPlaceholderRegex, "\r\n" )
            .replace( config.tabPlaceholderRegex, "\t" )
            .replace( config.nbspPlaceholderRegex, String.fromCharCode( parseInt( 0xA0, 10 ) ) );
        _edit = mainStr.replace( String.fromCharCode( parseInt( 0x21e5, 10 ) ), "\t" );

        //Prepend Unicode Character 'ZERO WIDTH SPACE' invisible, not printable, no spaced character,
        //used to detect initial and final spaces in html diff
        _str  = String.fromCharCode( parseInt( 0x200B, 10 ) ) + _str + String.fromCharCode( parseInt( 0x200B, 10 ) );
        _edit = String.fromCharCode( parseInt( 0x200B, 10 ) ) + _edit + String.fromCharCode( parseInt( 0x200B, 10 ) );

        diff_obj = UI.dmp.diff_main( _edit, _str );
        UI.dmp.diff_cleanupEfficiency( diff_obj );
        return diff_obj;
    },

    chooseAlternative: function(w) {
        this.copyAlternativeInEditarea( UI.decodePlaceholdersToText( $('.sugg-target .realData', w ).html(), true, UI.currentSegmentId, 'choose alternative' ) );
        this.editarea.focus();
        SegmentActions.highlightEditarea(UI.currentSegment.find(".editarea").data("sid"));
        this.disableTPOnSegment();
    },
	copyAlternativeInEditarea: function(translation) {
		if ($.trim(translation) !== '') {
			if (this.body.hasClass('searchActive'))
				this.addWarningToSearchDisplay();
			this.saveInUndoStack('copyalternative');

            SegmentActions.replaceEditAreaTextContent(UI.getSegmentId(UI.currentSegment), UI.getSegmentFileId(UI.currentSegment), translation);

            this.saveInUndoStack('copyalternative');
		}
	},
	setDownloadStatus: function(stats) {
        var t = translationStatus( stats );

        $('.downloadtr-button')
            .removeClass("draft translated approved")
            .addClass(t);

        var downloadable = (t == 'translated' || t == 'approved') ;

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
            '/api/v1/jobs/%s/%s/stats',
            config.id_job, config.password
        );
        $.ajax({
            url: path,
            type: 'get',
        }).done( function( data ) {
            if (data.stats){
                UI.setProgress(data.stats);
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
        this.checkIfFinished();
        this.done_percentage = this.progress_perc;

		$('.approved-bar', m).css('width', a_perc + '%').attr('title', 'Approved ' + a_perc_formatted + '%');
		$('.translated-bar', m).css('width', t_perc + '%').attr('title', 'Translated ' + t_perc_formatted + '%');
		$('.draft-bar', m).css('width', d_perc + '%').attr('title', 'Draft ' + d_perc_formatted + '%');
		$('.rejected-bar', m).css('width', r_perc + '%').attr('title', 'Rejected ' + r_perc_formatted + '%');

		$('#stat-progress').html(this.progress_perc);

		$('#stat-todo strong').html(t_formatted);
		$('#stat-wph strong').html(wph);
		$('#stat-completion strong').html(completion);
        $('#total-payable').html(s.TOTAL_FORMATTED);

        $(document).trigger('setProgress:rendered', { stats : stats } );

    },
	chunkedSegmentsLoaded: function() {
		return $('section.readonly:not(.ice-locked)').length;
	},
    formatSelection: function(op) {
        var str = getSelectionHtml();
        insertHtmlAfterSelection('<span class="formatSelection-placeholder"></span>');
        var newStr = '';
        var selection$ = $("<div/>").html(str);
        selection$.find('.undoCursorPlaceholder').remove();
        var rightString = selection$.html();

        $.each($.parseHTML(rightString), function(index) {
			var toAdd, d, jump, capStr;
            if(this.nodeName == '#text') {
				d = this.data;
				jump = ((!index)&&(!selection$));
				capStr = toTitleCase(d);
				if(jump) {
					capStr = d.charAt(0) + toTitleCase(d).slice(1);
				}
				toAdd = (op == 'uppercase')? d.toUpperCase() : (op == 'lowercase')? d.toLowerCase() : (op == 'capitalize')? capStr : d;
				newStr += toAdd;
			}
            else if(this.nodeName == 'LXQWARNING') {
                d = this.childNodes[0].data;
                jump = ((!index)&&(!selection$));
				capStr = toTitleCase(d);
				if(jump) {
					capStr = d.charAt(0) + toTitleCase(d).slice(1);
				}
                toAdd = (op == 'uppercase')? d.toUpperCase() : (op == 'lowercase')? d.toLowerCase() : (op == 'capitalize')? capStr : d;
				newStr += toAdd;
            }
            else {
				newStr += this.outerHTML;
			}
		});
        // saveSelection();
        if (LXQ.enabled()) {
            $.powerTip.destroy($('.tooltipa',this.currentSegment));
            $.powerTip.destroy($('.tooltipas',this.currentSegment));
            replaceSelectedHtml(newStr);
            LXQ.reloadPowertip(this.currentSegment);
        }
        else {
            replaceSelectedHtml(newStr);
        }
        this.saveInUndoStack('formatSelection');

        $('.editor .editarea .formatSelection-placeholder').after($('.editor .editarea .rangySelectionBoundary'));
        $('.editor .editarea .formatSelection-placeholder').remove();
        $('.editor .editarea').trigger('afterFormatSelection');
        setTimeout(function () {
            setCursorPosition(document.getElementsByClassName("undoCursorPlaceholder")[0]);
        }, 0);
    },

	setStatusButtons: function(button) {
		var isTranslatedButton = ($(button).hasClass('translated')) ? true : false;
		this.editStop = new Date();
		var segment = this.currentSegment;
		var tte = $('.timetoedit', segment);
		this.editTime = this.editStop - this.editStart;
		this.totalTime = this.editTime + tte.data('raw-time-to-edit');
		var editedTime = millisecondsToTime(this.totalTime);
		if (config.time_to_edit_enabled) {
			var editSec = $('.timetoedit .edit-sec', segment);
			var editMin = $('.timetoedit .edit-min', segment);
			editMin.text(APP.zerofill(editedTime[0], 2));
			editSec.text(APP.zerofill(editedTime[1], 2));
		}
		tte.data('raw-time-to-edit', this.totalTime);
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
	collectSegmentErrors: function(segment) {
		var errors = '';
		// tag mismatch
		if (segment.hasClass('mismatch'))
			errors += '01|';
		return errors.substring(0, errors.length - 1);
	},
	goToFirstError: function() {
        $("#point2seg").trigger('mousedown');
        setTimeout(function (  ) {
            $('.qa-issues-container ').first().click()
        }, 300);
	},

    disableDownloadButtonForDownloadStart : function( openOriginalFiles ) {
        var button = $('#downloadProject' ) ;
        var labelDownloading = 'DOWNLOADING';
        if ( config.isGDriveProject && config.isGDriveProject !== 'false') {
            labelDownloading = 'OPENING FILES...';
        }
        button.addClass('disabled' ).data( 'oldValue', button.val() ).val(labelDownloading);
        APP.fitText($('.breadcrumbs'), $('#pname'), 30);

    },

    reEnableDownloadButton : function() {
        var button = $('#downloadProject' ) ;
        button.removeClass('disabled')
            .val( button.data('oldValue') )
            .removeData('oldValue');
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
			UI.checkWarnings(false);
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

        if (UI.logEnabled) dataMix.logs = this.extractLogs();

        const mock = {
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
                UI.failedConnection(0, 'getWarning');
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
    segmentLexiQA: function (_segment) {
        var segment = _segment;
        //new API?
        if (_segment.raw) {
            segment = _segment.raw
        }
        var translation = $('.editarea', segment).text().replace(/\uFEFF/g, '');
        var id_segment = UI.getSegmentId(segment);
        LXQ.doLexiQA(segment, translation, id_segment, false, function () {});
    },
    segmentQA : function( segment ) {
        if ( ! ( segment instanceof UI.Segment) ) {
            segment = new UI.Segment( segment );
        }

        SegmentActions.addClassToSegment(UI.getSegmentId(segment), 'waiting_for_check_result');

		var dd = new Date();
		ts = dd.getTime();
		var token = segment.id + '-' + ts.toString();
        var segment_status_regex = new RegExp("status-([a-z]*)");
        var segment_status = segment.el.attr('class' ).match(segment_status_regex);
        if(segment_status.length > 0){
            segment_status = segment_status[1];
        }

		if( config.brPlaceholdEnabled ){
			src_content = this.postProcessEditarea(segment.el , '.source');
			trg_content = this.postProcessEditarea(segment.el);
		} else {
			src_content = this.getSegmentSource();
			trg_content = this.getSegmentTarget();
		}

		this.checkSegmentsArray[token] = trg_content;
		APP.doRequest({
			data: {
				action: 'getWarning',
				id: segment.id,
				token: token,
                id_job: config.id_job,
				password: config.password,
				src_content: src_content,
				trg_content: trg_content,
                segment_status: segment_status,
			},
			error: function() {
				UI.failedConnection(0, 'getWarning');
			},
			success: function(d) {
			    if(d.details){
                    SegmentActions.setSegmentWarnings(d.details.id_segment,d.details.issues_info);
                }else{
                    SegmentActions.setSegmentWarnings(segment.id,{});
                }
                $(document).trigger('getWarning:local:success', { resp : d, segment: segment }) ;
			}
		}, 'local');

	},

    translationIsToSave : function( segment ) {
        // add to setTranslation tail
        var alreadySet = this.alreadyInSetTranslationTail( segment.id );
        var emptyTranslation = ( segment && segment.el.find('.editarea').text().trim().length )? false : true;

        return ((!alreadySet)&&(!emptyTranslation));
    },

    setTranslation: function(options) {
        var id_segment = options.id_segment;
        var status = options.status;
        var caller = options.caller || false;
        var callback = options.callback || false;
        var byStatus = options.byStatus || false;
        var propagate = options.propagate || false;

        var segment = UI.Segment.findAbsolute( id_segment );

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
        if ( this.offline && config.offlineModeEnabled ) {
            if ( saveTranslation ) {
                this.decrementOfflineCacheRemaining();
                options.callback = UI.incrementOfflineCacheRemaining;
                this.failedConnection( options, 'setTranslation' );
            }
            this.changeStatusOffline( id_segment );
            this.checkConnection( 'Set Translation check Authorized' );
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
		var segment = $('#segment-' + id_segment);
		var contextBefore = UI.getContextBefore(id_segment);
		var contextAfter = UI.getContextAfter(id_segment);

		this.lastTranslatedSegmentId = id_segment;

		caller = (typeof caller == 'undefined') ? false : caller;
		var file = $(segment).parents('article');

		// Attention, to be modified when we will lock tags
		if( config.brPlaceholdEnabled ) {
			translation = this.postProcessEditarea(segment);
            sourceSegment = this.postProcessEditarea(segment, '.source');
		} else {
            translation = $('.editarea', segment ).text();
            sourceSegment = $('.source', segment ).text();
		}

		if (translation === '') {
            this.unsavedSegmentsToRecover.push(this.currentSegmentId);
            this.executingSetTranslation = false;
            return false;
        }
		var time_to_edit = UI.editTime;
		var id_translator = config.id_translator;
		var errors = this.collectSegmentErrors(segment);
		var chosen_suggestion = $('.editarea', segment).data('lastChosenSuggestion');
		var autosave = (caller == 'autosave');

        var isSplitted = (id_segment.split('-').length > 1);
        if(isSplitted) {
            translation = this.collectSplittedTranslations(id_segment);
            sourceSegment = this.collectSplittedTranslations(id_segment, ".source");
        }
        this.tempReqArguments = {
            id_segment: id_segment,
            id_job: config.id_job,
            id_first_file: file.attr('id').split('-')[1],
            password: config.password,
            status: status,
            translation: translation,
            segment : sourceSegment,
            time_to_edit: time_to_edit,
            id_translator: id_translator,
            errors: errors,
            chosen_suggestion_index: chosen_suggestion,
            autosave: autosave,
            version: segment.attr('data-version'),
            propagate: propagate,
            context_before: contextBefore,
            context_after: contextAfter
        };
        if(isSplitted) {
            this.setStatus($('#segment-' + id_segment), status);
            this.tempReqArguments.splitStatuses = this.collectSplittedStatuses(id_segment).toString();
        }
        if(!propagate) {
            this.tempReqArguments.propagate = false;
        }
        reqData = this.tempReqArguments;
        reqData.action = 'setTranslation';

        return APP.doRequest({
            data: reqData,
			context: [reqArguments, options],
			error: function() {
                UI.addToSetTranslationTail(this[1]);
                UI.changeStatusOffline(this[0][0].id_segment);
                UI.failedConnection(this[0], 'setTranslation');
                UI.decrementOfflineCacheRemaining();
            },
			success: function( data ) {
                UI.executingSetTranslation = false;
                if ( typeof callback == 'function' ) {
                    callback(data);
                }
                UI.execSetTranslationTail();
				UI.setTranslation_success(data, this[1]);

                var record = MateCat.db.segments.by('sid', data.translation.sid);
                MateCat.db.segments.update( _.extend(record, data.translation) );
                data.translation.segment = segment;
                $(document).trigger('translation:change', data.translation);
                data.segment = segment;

                $(document).trigger('setTranslation:success', data);
			}
		});
	},

    collectSplittedStatuses: function (sid) {
        statuses = [];
        segmentsIds = $('#segment-' + sid).attr('data-split-group').split(',');
        $.each(segmentsIds, function (index) {
            segment = $('#segment-' + this);
            status = UI.getStatus(segment);
            statuses.push(status);
        });
        return statuses;
    },
    collectSplittedTranslations: function (sid, selector) {
        totalTranslation = '';
        segmentsIds = $('#segment-' + sid).attr('data-split-group').split(',');
        $.each(segmentsIds, function (index) {
            segment = $('#segment-' + this);
            totalTranslation += UI.postProcessEditarea(segment, selector);
            if(index < (segmentsIds.length - 1)) totalTranslation += UI.splittedTranslationPlaceholder;
        });
        return totalTranslation;
    },

    /**
     * @deprecated
     */
    checkPendingOperations: function() {
        if(this.checkInStorage('pending')) {
            UI.execAbortedOperations();
        }
    },
    addInStorage: function (key, val, operation) {
        if(this.isPrivateSafari) {
            item = {
                key: key,
                value: val
            }
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

    log: function(operation, d) {
        if(!UI.logEnabled) return false;
        data = d;
        var dd = new Date();
        logValue = {
            "data": data,
            "stack": stackTrace()
        };
        UI.addInStorage('log-' + operation + '-' + dd.getTime(), JSON.stringify(logValue), 'log');
    },

    extractLogs: function() {
        if(this.isPrivateSafari) return;
        var pendingLogs = [];
        inp = 'log';
        $.each(localStorage, function(k,v) {
            if(k.substring(0, inp.length) === inp) {
                pendingLogs.push('{"operation": "' + k.split('-')[1] + '", "time": "' + k.split('-')[2] + '", "log":' + v + '}');
            }
        });
        logs = JSON.stringify(pendingLogs);
        this.clearStorage('log');

        return logs;
    },

    targetContainerSelector : function() {
        return '.targetarea';
    },

    /**
     * Method overwritten in the file ui.contribution.js
     */
    processContributions : function() {
    },

    /**
     * This function is overwritten in ui.contribution.js. This version is meant to be used by
     * customisations that do not make use of contributions.
     *
     * @param segment
     * @param next
     */
    getContribution : function(segment, next) {
        UI.blockButtons = false ;
        SegmentActions.addClassToSegment( UI.getSegmentId( segment ), 'loaded' ) ;
        this.segmentQA(segment);
        var deferred = new jQuery.Deferred() ;
        return deferred.resolve();
    },
    /**
     * Called when a Segment string returned by server has to be visualized, it replace placeholders with tags
     * @param str
     * @returns {XML|string}
     */
    decodePlaceholdersToText: function (str) {
        if(!UI.hiddenTextEnabled) return str;
		var _str = str;
        if(UI.markSpacesEnabled) {
            if(jumpSpacesEncode) {
                _str = this.encodeSpacesAsPlaceholders(htmlDecode(_str), true);
            }
        }

		_str = _str.replace( config.lfPlaceholderRegex, '<span class="monad marker softReturn ' + config.lfPlaceholderClass +'"><br /></span>' )
					.replace( config.crPlaceholderRegex, '<span class="monad marker ' + config.crPlaceholderClass +'"><br /></span>' )
		_str = _str.replace( config.lfPlaceholderRegex, '<span class="monad marker softReturn ' + config.lfPlaceholderClass +'" contenteditable="false"><br /></span>' )
					.replace( config.crPlaceholderRegex, '<span class="monad marker ' + config.crPlaceholderClass +'" contenteditable="false"><br /></span>' )
					.replace( config.crlfPlaceholderRegex, '<br class="' + config.crlfPlaceholderClass +'" />' )
					.replace( config.tabPlaceholderRegex, '<span class="tab-marker monad marker ' + config.tabPlaceholderClass +'" contenteditable="false">&#8677;</span>' )
					.replace( config.nbspPlaceholderRegex, '<span class="nbsp-marker monad marker ' + config.nbspPlaceholderClass +'" contenteditable="false">&nbsp;</span>' )
                    .replace(/(<\/span\>)$/gi, "</span><br class=\"end\">"); // For rangy cursor after a monad marker

		return _str;
    },

	encodeSpacesAsPlaceholders: function(str, root) {
        if(!UI.hiddenTextEnabled) return str;

		var newStr = '';
		$.each($.parseHTML(str), function() {

			if(this.nodeName == '#text') {
				newStr += $(this).text().replace(/\s/gi, '<span class="space-marker marker monad" contenteditable="false"> </span>');
			} else {
				match = this.outerHTML.match(/<.*?>/gi);
				if(match.length == 1) { // se è 1 solo, è un tag inline

				} else if(match.length == 2) { // se sono due, non ci sono tag innestati
					newStr += htmlEncode(match[0]) + this.innerHTML.replace(/\s/gi, '#@-lt-@#span#@-space-@#class="space-marker#@-space-@#marker#@-space-@#monad"#@-space-@#contenteditable="false"#@-gt-@# #@-lt-@#/span#@-gt-@#') + htmlEncode(match[1]);
				} else { // se sono più di due, ci sono tag innestati

					newStr += htmlEncode(match[0]) + UI.encodeSpacesAsPlaceholders(this.innerHTML) + htmlEncode(match[1], false);

				}
			}
		});
		if(root) {
			newStr = newStr.replace(/#@-lt-@#/gi, '<').replace(/#@-gt-@#/gi, '>').replace(/#@-space-@#/gi, ' ');
		}
		return newStr;
	},

	unnestMarkers: function() {
		$('.editor .editarea .marker .marker').each(function() {
			$(this).parents('.marker').after($(this));
		});
	},

	processErrors: function(err, operation) {
		$.each(err, function() {
			if (operation == 'setTranslation') {
				if (this.code != '-10') { // is not a password error
					APP.alert({msg: "Error in saving the translation. Try the following: <br />1) Refresh the page (Ctrl+F5 twice) <br />2) Clear the cache in the browser <br />If the solutions above does not resolve the issue, please stop the translation and report the problem to <b>support@matecat.com</b>"});
				}
			}

			if (this.code == '-10' && operation != 'getSegments' ) {
//				APP.alert("Job canceled or assigned to another translator");
				APP.alert({
					msg: 'Job canceled or assigned to another translator',
					callback: 'reloadPage'
				});
				//FIXME
				// This Alert, will be NEVER displayed because are no-blocking
				// Transform location.reload(); to a callable function passed as callback to alert
			}
			if (this.code == '-1000') {
				console.log('ERROR -1000');
				console.log('operation: ', operation);
                UI.startOfflineMode();
//				UI.failedConnection(0, 'no');
			}
            if (this.code == '-101') {
                console.log('ERROR -101');
                UI.startOfflineMode();
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

		if (d.errors.length)
			this.processErrors(d.errors, 'setTranslation');
        if (typeof d.pee_error_level != 'undefined') {
            //TODO: we must check the quality fo th Revision Algorithm For now commented
            //$('#edit_log_link' ).removeClass( "edit_1 edit_2 edit_3" ). addClass( UI.pee_error_level_map[d.pee_error_level] );
            UI.body.addClass('peeError');
        }
		if (d.data == 'OK') {
			this.setStatus(segment, status);
			this.setDownloadStatus(d.stats);
			this.setProgress(d.stats);
            SegmentActions.removeClassToSegment(options.id_segment, 'setTranslationPending');

			this.checkWarnings(false);
            $(segment).attr('data-version', d.version);
            if((!byStatus)&&(propagate)) {
                this.beforePropagateTranslation(segment, status);
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


    beforePropagateTranslation: function(segment, status) {
        if($(segment).attr('id').split('-').length > 2) return false;
        UI.propagateTranslation(segment, status);
    },

    propagateTranslation: function(segment, status) {
        this.tempReqArguments = null;
        if( (status == 'translated') || (config.isReview && (status == 'approved'))){
            plusApproved = (config.isReview)? ', section[data-hash=' + $(segment).attr('data-hash') + '].status-approved' : '';

            //NOTE: i've added filter .not( segment ) to exclude current segment from list to be set as draft
            $.each($('section[data-hash=' + $(segment).attr('data-hash') + '].status-new, section[data-hash=' + $(segment).attr('data-hash') + '].status-draft, section[data-hash=' + $(segment).attr('data-hash') + '].status-rejected' + ', section[data-hash=' + $(segment).attr('data-hash') + '].status-translated' + plusApproved ).not( segment ), function() {
                // $('.editarea', $(this)).html( $('.editarea', segment).html() );
                SegmentActions.replaceEditAreaTextContent(UI.getSegmentId($(this)), UI.getSegmentFileId($(this)), $('.editarea', segment).html());


                //Tag Projection: disable it if enable
                UI.disableTPOnSegment($(this));

                // if status is not set to draft, the segment content is not displayed
                UI.setStatus($(this), status); // now the status, too, is propagated

                SegmentActions.setSegmentPropagation(UI.getSegmentId(this), UI.getSegmentFileId(this), true ,UI.getSegmentId(segment));

                var trans = $('.editarea', this ).text().replace(/\uFEFF/g,'');
                LXQ.doLexiQA(this,trans,UI.getSegmentId(this),true,null);
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

    setWaypoints: function() {
        if (this.settedWaypoints) {
            Waypoint.destroyAll();
        }
		this.detectFirstLast();
		this.lastSegmentWaypoint = this.lastSegment.waypoint(function(direction) {
			if (direction === 'down') {
				this.destroy();
				if (UI.infiniteScroll) {
					if (!UI.blockGetMoreSegments) {
						UI.blockGetMoreSegments = true;
						UI.getMoreSegments('after');
						setTimeout(function() {
							UI.blockGetMoreSegments = false;
						}, 1000);
					}
				}
			}
		}, UI.downOpts);

        this.firstSegmentWaypoint = this.firstSegment.waypoint(function(direction) {
			if (direction === 'up') {
                this.destroy();
				UI.getMoreSegments('before');
			}
		}, UI.upOpts);
        this.settedWaypoints = true;
	},

    storeClientInfo: function () {
        clientInfo = {
            xRes: window.screen.availWidth,
            yRes: window.screen.availHeight
        };
        Cookies.set('client_info', JSON.stringify(clientInfo), { expires: 3650 });
    },

	browserScrollPositionRestoreCorrection: function() {
		// detect if the scroll is a browser generated scroll position restore, and if this is the case rescroll to the segment
		if (this.firstOpenedSegment == 1) { // if the current segment is the first opened in the current UI
			if (!$('.editor').isOnScreen()) { // if the current segment is out of the current viewport
				if (this.autoscrollCorrectionEnabled) { // if this is the first correction and we are in the initial 2 seconds since page init
					this.scrollSegment(this.currentSegment);
					this.autoscrollCorrectionEnabled = false;
				}
			}
		}
	},
	undoInSegment: function() {
		console.log('undoInSegment');
		if (this.undoStackPosition === 0)
			this.saveInUndoStack('undo');
		var ind = 0;
		if (this.undoStack[this.undoStack.length - 1 - this.undoStackPosition - 1])
			ind = this.undoStack.length - 1 - this.undoStackPosition - 1;
        SegmentActions.replaceEditAreaTextContent(UI.getSegmentId(this.editarea), UI.getSegmentFileId(this.editarea), this.undoStack[ind]);
        // this.editarea.html(this.undoStack[ind]);
        setTimeout(function () {
            setCursorPosition(document.getElementsByClassName("undoCursorPlaceholder")[0]);
            $('.undoCursorPlaceholder').remove();
        }, 100);
		if (this.undoStackPosition < (this.undoStack.length - 1))
			this.undoStackPosition++;
        SegmentActions.removeClassToSegment(UI.getSegmentId(this.currentSegment), 'waiting_for_check_result');
		this.registerQACheck();
	},
	redoInSegment: function() {
        var html = this.undoStack[this.undoStack.length - 1 - this.undoStackPosition - 1 + 2]
        SegmentActions.replaceEditAreaTextContent(UI.getSegmentId(this.editarea), UI.getSegmentFileId(this.editarea), html);
        setTimeout(function () {
            setCursorPosition(document.getElementsByClassName("undoCursorPlaceholder")[0]);
            $('.undoCursorPlaceholder').remove();
        }, 100);
		// this.editarea.html();
		if (this.undoStackPosition > 0) {
            this.undoStackPosition--;
        }
        SegmentActions.removeClassToSegment(UI.getSegmentId(this.currentSegment), 'waiting_for_check_result');
		this.registerQACheck();
	},
	saveInUndoStack: function(action) {
		var currentItem = this.undoStack[this.undoStack.length - 1 - this.undoStackPosition];

        if (typeof currentItem != 'undefined') {
            var regExp = /(<\s*\/*\s*(span class="undoCursorPlaceholder|span id="selectionBoundary)\s*.*span>)/gmi;
            var editAreaText = this.editarea.html().replace(regExp, '');
            var itemText = currentItem.replace(regExp, '');
            if (itemText.trim() == editAreaText.trim())
                return;
        }

        if (this.editarea === '') return;
		if (this.editarea.html() === '') return;
        if (this.editarea.length === 0 ) return ;

		var ss = this.editarea.html().match(/<span.*?contenteditable\="false".*?\>/gi);
		var tt = this.editarea.html().match(/&lt;/gi);
        if ( tt ) {
            if ( (tt.length) && (!ss) )
                return;
        }
        // var diff = 'null';
        //
        // if( typeof currentItem != 'undefined'){
        //     diff = this.dmp.diff_main( currentItem, this.editarea.html() );
        //
        //     // diff_main can return an array of one element (why?) , hence diff[1] could not exist.
        //     // for that we chooiff[0] as a fallback
        //     if(typeof diff[1] != 'undefined') {
        //         diff = diff[1][1];
        //     }
        //     else {
        //         diff = diff[0][1];
        //     }
        // }
        //
        // if ( diff == ' selected' )
        //     return;

		var pos = this.undoStackPosition;
		if (pos > 0) {
			this.undoStack.splice(this.undoStack.length - pos, pos);
			this.undoStackPosition = 0;
		}
        if(action !== 'paste'){
            saveSelection();
        }

        // var cursorPos = APP.getCursorPosition(this.editarea.get(0));
        $('.undoCursorPlaceholder').remove();
        if ($('.rangySelectionBoundary').closest('.editarea').length) {
            $('.rangySelectionBoundary').after('<span class="undoCursorPlaceholder monad" contenteditable="false"></span>');
        }
        if(action !== 'paste'){
            restoreSelection();
        }

        var htmlToSave = this.editarea.html();
        this.undoStack.push(htmlToSave);
        // $('.undoCursorPlaceholder').remove();

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
    start: function () {

        // TODO: the following variables used to be set in UI.init() which is called
        // very during rendering. Those have been moved here because of the init change
        // of SegmentFilter, see below.
        UI.firstLoad = true;
        UI.body = $('body');
        UI.checkSegmentsArray = {} ;
        UI.localStorageCurrentSegmentId = "currentSegmentId-"+config.id_job+config.password;
        UI.setShortcuts();
        // If some icon is added on the top header menu, the file name is resized
        APP.addDomObserver($('.header-menu')[0], function() {
            APP.fitText($('.breadcrumbs'), $('#pname'), 30);
        });
        setBrowserHistoryBehavior();
        $("article").each(function() {
            APP.fitText($('.filename h2', $(this)), $('.filename h2', $(this)), 30);
        });

        var initialRenderPromise = UI.render();

        initialRenderPromise.done(function() {
            if ( SegmentFilter.enabled() && SegmentFilter.getStoredState().reactState ) {
                SegmentFilter.openFilter();
            }
            UI.checkWarnings(true);
        });

        $('html').trigger('start');

        if (LXQ.enabled()) {
            $('#lexiqabox').removeAttr("style");
            LXQ.initPopup();
        }
    },
    restart: function () {
        UI.unmountSegments();
        this.start();
    },
    /**
     * After User click on Translated or T+>> Button
     * @param e
     * @param button
     */
    clickOnTranslatedButton: function (button) {
        var buttonValue = ($(button).hasClass('translated')) ? 'translated' : 'next-untranslated';
        //??
        $('.test-invisible').remove();

        // UI.setSegmentModified( UI.currentSegment, false ) ;

        var skipChange = false;
        if (buttonValue == 'next-untranslated') {
            if (!UI.segmentIsLoaded(UI.nextUntranslatedSegmentId)) {
                UI.changeStatus(button, 'translated', 0);
                skipChange = true;
                if (!UI.nextUntranslatedSegmentId) {
                    $('#' + $(button).attr('data-segmentid') + '-close').click();
                } else {
                    UI.reloadWarning();
                }
            }
        } else {
            if (!$(UI.currentSegment).nextAll('section:not(.readonly)').length) {
                UI.changeStatus(button, 'translated', 0);
                skipChange = true;
            }

        }

        if ( UI.maxNumSegmentsReached() && !UI.offline ) {
            // TODO: argument should be next segment to open
            UI.reloadToSegment( UI.currentSegmentId );
            return ;
        }

        if ( UI.blockButtons ) {
            if (UI.segmentIsLoaded(UI.nextUntranslatedSegmentId) || UI.nextUntranslatedSegmentId === '') {
            } else {

                if (!UI.noMoreSegmentsAfter) {
                    UI.reloadWarning();
                }
            }
            return;
        }
        if(!UI.offline) UI.blockButtons = true;

        UI.setStatusButtons(button);

        if (!skipChange) {
            UI.changeStatus(button, 'translated', 0);
        }

        if (buttonValue == 'translated') {
            UI.gotoNextSegment();
        } else {
            // TODO: investigate why this trigger click is necessary.
            // See function closeSegment (line 271) ??
            $(".editarea", UI.nextUntranslatedSegment).trigger("click", "translated")
        }
    },

    handleClickOnReadOnly : function(section) {
        if ( UI.justSelecting('readonly') )   return;
        if ( UI.someUserSelection )           return;
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

    showFixWarningsOnDownload( continueDownloadFunction ) {
        APP.confirm({
            name: 'confirmDownload', // <-- this is the name of the function that gets invoked?
            cancelTxt: 'Fix errors',
            onCancel: 'goToFirstError',
            callback: continueDownloadFunction,
            okTxt: 'Download anyway',
            msg: 'Unresolved issues may prevent downloading your translation. <br>Please fix the issues. <a style="color: #4183C4; font-weight: 700; text-decoration: underline;" href="https://www.matecat.com/support/advanced-features/understanding-fixing-tag-errors-tag-issues-matecat/" target="_blank">How to fix tags in MateCat </a> <br /><br /> If you continue downloading, part of the content may be untranslated - look for the string UNTRANSLATED_CONTENT in the downloaded files.'
        });
    }
};

$(document).ready(function() {
    console.time("Time: from start()");
    UI.start();
});

$(window).resize(function() {
    // UI.fixHeaderHeightChange();
    APP.fitText($('.breadcrumbs'), $('#pname'), 30);
});
