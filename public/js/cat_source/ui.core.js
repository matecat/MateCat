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
	toggleFileMenu: function() {
        jobMenu = $('#jobMenu');
		if (jobMenu.is(':animated')) {
			return false;
		} else {
            currSegment = jobMenu.find('.currSegment');
            if (this.body.hasClass('editing')) {
                currSegment.show();
            } else {
                currSegment.hide();
            }
            var menuHeight = jobMenu.height();
//		var startTop = 47 - menuHeight;
            var messageBarIsOpen = UI.body.hasClass('incomingMsg');
            messageBarHeight = (messageBarIsOpen)? $('#messageBar').height() + 5 : 0;
            console.log('messageBarHeight: ', messageBarHeight);
            var searchBoxIsOpen = UI.body.hasClass('filterOpen');
            console.log('searchBoxIsOpen: ', searchBoxIsOpen);
            searchBoxHeight = (searchBoxIsOpen)? $('.searchbox').height() + 1 : 0;
            console.log('searchBoxHeight: ', searchBoxHeight);

            jobMenu.css('top', (messageBarHeight + searchBoxHeight + 43 - menuHeight) + "px");
//            jobMenu.css('top', (47 - menuHeight) + "px");

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
	activateSegment: function(isNotSimilar) {
		this.createFooter(this.currentSegment, isNotSimilar);
		this.createButtons();
		this.createHeader();
	},
	cacheObjects: function(editarea) {
		this.editarea = $(editarea);
        // current and last opened object reference caching
		this.lastOpenedSegment = this.currentSegment;
		this.lastOpenedEditarea = $('.editarea', this.currentSegment);
		this.currentSegmentId = this.lastOpenedSegmentId = this.editarea.data('sid');
		this.currentSegment = segment = $('#segment-' + this.currentSegmentId);
		this.currentFile = segment.parent();
		this.currentFileId = this.currentFile.attr('id').split('-')[1];
		var sourceTags = $('.source', this.currentSegment).html().match(/(&lt;\s*\/*\s*(g|x|bx|ex|bpt|ept|ph|it|mrk)\s*.*?&gt;)/gi);
        this.sourceTags = sourceTags || [];
        this.currentSegmentTranslation = this.editarea.text();
        $(window).trigger('cachedSegmentObjects');
    },
	changeStatus: function(ob, status, byStatus) {
        var segment = (byStatus) ? $(ob).parents("section") : $('#' + $(ob).data('segmentid'));
        segment_id = this.getSegmentId(segment);
//        this.consecutiveCopySourceNum = [];
        var options = {
            segment_id: segment_id,
            status: status,
            byStatus: byStatus,
            noPropagation: false
        };
        if(byStatus) { // if this comes from a click on the status bar
            options.noPropagation = true;
            this.execChangeStatus(JSON.stringify(options)); // no propagation
        } else {
            if(this.autopropagateConfirmNeeded()) { // ask if the user wants propagation or this is valid only for this segment
                optionsStr = JSON.stringify(options)
                APP.confirm({
                    name: 'confirmAutopropagation',
                    cancelTxt: 'Propagate to All',
                    onCancel: 'execChangeStatus',
                    callback: 'preExecChangeStatus',
                    okTxt: 'Only this segment',
                    context: optionsStr,
/*
                    context: {
                        options: options,
                        noPropagation: false
                    },
*/
                    msg: "There are other identical segments. <br><br>Would you like to propagate the translation to all of them, or keep this translation only for this segment?"
                });
            } else {
                this.execChangeStatus(JSON.stringify(options)); // autopropagate
            }
        }

	},
    autopropagateConfirmNeeded: function () {
        segment = UI.currentSegment;
        if(this.currentSegmentTranslation.trim() == this.editarea.text().trim()) { //segment not modified
            return false;
        }
        if(segment.attr('data-propagable') == 'true') {
            if(config.isReview) {
                return true;
            } else {
                if(segment.is('.status-translated, .status-approved, .status-rejected')) {
                    return true;
                } else {
                    return false;
                }
            }
        } else {
            return false;
        }
    },
    preExecChangeStatus: function (optStr) {
        opt = $.parseJSON(optStr);
        opt.noPropagation = true;
        this.execChangeStatus(JSON.stringify(opt));
    },
    execChangeStatus: function (optStr) {
        opt = $.parseJSON(optStr);
        options = opt;
        noPropagation = opt.noPropagation;

        segment_id = options.segment_id;
        segment = $('#segment-' + segment_id);
        status = options.status;
        byStatus = options.byStatus;
        noPropagation = noPropagation || false;

        $('.percentuage', segment).removeClass('visible');
//		if (!segment.hasClass('saved'))
        this.setTranslation({
            id_segment: segment_id,
            status: status,
            caller: false,
            byStatus: byStatus,
            propagate: !noPropagation
        });
//        this.setTranslation(segment_id, status, false);
        segment.removeClass('saved');
        this.setContribution(segment_id, status, byStatus);
        this.setContributionMT(segment_id, status, byStatus);
        this.getNextSegment(this.currentSegment, 'untranslated');
        if(!this.nextUntranslatedSegmentId) {
            $(window).trigger({
                type: "allTranslated"
            });
        }
        $(window).trigger({
            type: "statusChanged",
            segment: segment,
            status: status
        });
    },

    getSegmentId: function (segment) {
        if(typeof segment == 'undefined') return false;

        /*
         sometimes:
         typeof $(segment).attr('id') == 'undefined'

         The preeceding if doesn't works because segment is a list ==
         '[<span class="undoCursorPlaceholder monad" contenteditable="false"></span>]'

         so for now i put a try-catch block here

         TODO FIX
         */
        try {
            return $(segment).attr('id').replace('segment-', '');
        } catch( e ){
            return false;
        }

//        return $(segment).attr('id').split('-')[1];

    },

    checkHeaviness: function() {
        if ($('section').length > config.maxNumSegments) {
            UI.reloadToSegment(UI.currentSegmentId);
        }
//		console.log('UI.hasToBeRerendered: ', this.hasToBeRerendered);
//		console.log(this.initSegNum + ' - ' + this.numOpenedSegments + ' - ' + (this.initSegNum/this.numOpenedSegments));
//		if (($('section').length > 500)||(this.numOpenedSegments > 2)) {
/*
		if (($('section').length > 500)||((this.initSegNum/this.numOpenedSegments) < 2)||(this.hasToBeRerendered)) {
			UI.reloadToSegment(UI.currentSegmentId);
		}
*/
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
/*
	checkTutorialNeed: function() {
		if (!Loader.detect('tutorial'))
			return false;
		if (!$.cookie('noTutorial')) {
			$('#dialog').dialog({
			});
			$('#hideTutorial').bind('change', function(e) {
				if ($('#hideTutorial').attr('checked')) {
					$.cookie('noTutorial', true);
				} else {
					$.removeCookie('noTutorial');
				}
			});
		}
	},
*/
    closeSegment: function(segment, byButton, operation) {
        console.log('CLOSE SEGMENT');

		if ((typeof segment == 'undefined') || (typeof UI.toSegment != 'undefined')) {
			this.toSegment = undefined;
			return true;
		} else {
//		    var closeStart = new Date();
            this.autoSave = false;

            $(window).trigger({
                type: "segmentClosed",
                segment: segment
            });

            clearTimeout(this.liveConcordanceSearchReq);

            var saveBrevior = true;
            if (operation != 'noSave') {
                if ((operation == 'translated') || (operation == 'Save'))
                    saveBrevior = false;
            }
//            console.log('segment.hasClass(modified): ', segment.hasClass('modified'));
//            console.log('saveBrevior: ', saveBrevior);
//            console.log('!config.isReview: ', !config.isReview);
            if ((segment.hasClass('modified')) && (saveBrevior) && (!config.isReview)) {
                this.saveSegment(segment);
            }
            this.deActivateSegment(byButton);
            this.removeGlossaryMarksFormSource();

            this.lastOpenedEditarea.attr('contenteditable', 'false');

            this.body.removeClass('editing');
            $(segment).removeClass("editor waiting_for_check_result opened");
            $('span.locked.mismatch', segment).removeClass('mismatch');
            if (!this.opening) {
                this.checkIfFinished(1);
            }

// close split segment
        	$('.sid .actions .split').removeClass('cancel');
        	source = $(segment).find('.source');
        	$(source).removeAttr('style');
        	$('section').removeClass('split-action');
        	$('.split-shortcut').html('CTRL + S');
        	$('.splitBar, .splitArea').remove();
        	$('.sid .actions').hide();
// end split segment
		return true;
        }
	},
    copySource: function() {
        var source_val = UI.clearMarks($.trim($(".source", this.currentSegment).html()));
//		var source_val = $.trim($(".source", this.currentSegment).text());
        // Test
        //source_val = source_val.replace(/&quot;/g,'"');

        // Attention I use .text to obtain a entity conversion, by I ignore the quote conversion done before adding to the data-original
        // I hope it still works.

        this.saveInUndoStack('copysource');
        $(".editarea", this.currentSegment).html(source_val).keyup().focus();
//		$(".editarea", this.currentSegment).text(source_val).keyup().focus();
        this.saveInUndoStack('copysource');
//		$(".editarea", this.currentSegment).effect("highlight", {}, 1000);
        this.highlightEditarea();

        this.currentSegmentQA();
        $(this.currentSegment).trigger('copySourceToTarget');
        if(!config.isReview) {
            var alreadyCopied = false;
            $.each(UI.consecutiveCopySourceNum, function (index) {
                if(this == UI.currentSegmentId) alreadyCopied = true;
            });
            if(!alreadyCopied) {
                this.consecutiveCopySourceNum.push(this.currentSegmentId);
            }
//        this.consecutiveCopySourceNum++;
            if(this.consecutiveCopySourceNum.length > 2) {
                this.copyAllSources();
            }
        }

    },
    copyAllSources: function() {
        console.log('copy all sources');
        if(typeof $.cookie('source_copied_to_target-' + config.job_id + "-" + config.password) == 'undefined') {
            APP.confirmAndCheckbox({
                title: 'Copy all new segments',
                name: 'confirmCopyAllSources',
                okTxt: 'Yes',
                cancelTxt: 'No',
                callback: 'continueCopyAllSources',
                onCancel: 'abortCopyAllSources',
                closeOnSuccess: true,
                msg: "Copy source to target for all new segments?<br/><b>This action cannot be undone.</b>",
                'checkbox-label': "I want to fill all untranslated target segments with a copy "+
                                    "of the corresponding source segments."
            });
        } else {
            this.consecutiveCopySourceNum = [];
        }

    },
    continueCopyAllSources: function () {
        var mod = $('.modal .popup');
        mod.find('.btn-ok, .btn-cancel').remove();
        mod.find('p').addClass('waiting').text('Copying...');
        APP.doRequest({
            data: {
                action: 'copyAllSource2Target',
                id_job: config.job_id,
                pass: config.password
            },
            error: function() {
                console.log('error');
                APP.closePopup();
                UI.showMessage({
                    msg: 'Error copying all sources to target. Try again!'
                });
            },
            success: function(d) {
                if(d.errors.length) {
                    APP.closePopup();
                    UI.showMessage({
                        msg: d.errors[0].message
                    });
                } else {
                    $.cookie('source_copied_to_target-' + config.job_id + "-" + config.password, '1', { expires:1 });
                    APP.closePopup();
                    $('#outer').empty();
                    UI.render({
                        firstLoad: false,
                        segmentToOpen: UI.currentSegmentId
                    });
                }

            }
        });
    },
    abortCopyAllSources: function () {
        this.consecutiveCopySourceNum = [];
        //$.cookie('source_copied_to_target-' + config.job_id +"-" + config.password, '0', { expires: 1 });
    },
    setComingFrom: function () {
        var page = (config.isReview)? 'revise' : 'translate';
        $.cookie('comingFrom' , page, { path: '/' });
    },

    clearMarks: function (str) {
        str = str.replace(/(<mark class="inGlossary">)/gi, '').replace(/<\/mark>/gi, '');
        return str;
    },
	highlightEditarea: function(seg) {
		segment = seg || this.currentSegment;
		segment.addClass('highlighted1');
		setTimeout(function() {
			$('.highlighted1').addClass('modified highlighted2');
		}, 300);
		setTimeout(function() {
			$('.highlighted1, .highlighted2').removeClass('highlighted1 highlighted2');
		}, 2000);
	},

	confirmDownload: function(res) {
		if (res) {
			if (UI.isChrome) {
				$('.download-chrome').addClass('d-open');
				setTimeout(function() {
					$('.download-chrome').removeClass('d-open');
				}, 7000);

			}
		}
	},
	copyToNextIfSame: function(nextUntranslatedSegment) {
		if ($('.source', this.currentSegment).data('original') == $('.source', nextUntranslatedSegment).data('original')) {
			if ($('.editarea', nextUntranslatedSegment).hasClass('fromSuggestion')) {
				$('.editarea', nextUntranslatedSegment).text(this.editarea.text());
			}
		}
	},
	createButtons: function() {
		var disabled = (this.currentSegment.hasClass('loaded')) ? '' : ' disabled="disabled"';
        var nextSegment = this.currentSegment.next();
        var sameButton = (nextSegment.hasClass('status-new')) || (nextSegment.hasClass('status-draft'));
        var nextUntranslated = (sameButton)? '' : '<li><a id="segment-' + this.currentSegmentId + '-nextuntranslated" href="#" class="btn next-untranslated" data-segmentid="segment-' + this.currentSegmentId + '" title="Translate and go to next untranslated">T+&gt;&gt;</a><p>' + ((UI.isMac) ? 'CMD' : 'CTRL') + '+SHIFT+ENTER</p></li>';
		UI.segmentButtons = nextUntranslated + '<li><a id="segment-' + this.currentSegmentId + '-button-translated" data-segmentid="segment-' + this.currentSegmentId + '" href="#" class="translated"' + disabled + ' >TRANSLATED</a><p>' + ((UI.isMac) ? 'CMD' : 'CTRL') + '+ENTER</p></li>';
		buttonsOb = $('#segment-' + this.currentSegmentId + '-buttons');
        UI.currentSegment.trigger('buttonsCreation');
        buttonsOb.empty().append(UI.segmentButtons);
        buttonsOb.before('<p class="warnings"></p>');
        UI.segmentButtons = null;
	},

    /**
     * createFooter is invoked each time the footer is to be rendered for a
     * given segment. During the activation of a segment, footer for the next
     * segment is also rendered, so to be cached and ready when the user moves
     * to the next segment.
     *
     * @param segment DOMElement the <section> tag of the segment
     * @param forceEmptyContribution boolean default true. not sure what it
     * does.
     */
	createFooter: function(segment, forceEmptyContribution) {
        var sid = UI.getSegmentId( segment );

		forceEmptyContribution = (typeof forceEmptyContribution == 'undefined')? true : forceEmptyContribution;

		if ( $('.matches .overflow', segment).text() !== '' ) { // <-- XXX unnamed intention
			if (!forceEmptyContribution) {
				$('.matches .overflow', segment).empty();
                $(document).trigger('createFooter:skipped', segment);
				return false;
			}
		}

		if ( $('.footer ul.submenu', segment).length ) {  // <--- XXX unnamed intention
            $(document).trigger('createFooter:skipped:cached', segment);
            return false;
        }

        var segmentFooter = new UI.SegmentFooter( segment );
        $('.footer', segment).append( segmentFooter.html() );

        UI.currentSegment.trigger('afterFooterCreation', segment);

        // FIXME: arcane. Whatever it does, it should go in the contribution module.
		if ($(segment).hasClass('loaded') && (segment === this.currentSegment) && ($(segment).find('.matches .overflow').text() === '')) {
            var d = JSON.parse( UI.getFromStorage('contribution-' + config.job_id + '-' + sid ) );
			UI.processContributions( d, segment );
		}
	},

    createHeader: function(forceCreation) {

        forceCreation = forceCreation || false;

        if ( $('h2.percentuage', this.currentSegment).length && !forceCreation ) {
            return;
        }
		var header = '<h2 title="" class="percentuage"><span></span></h2><a href="/referenceFile/' + config.job_id + '/' + config.password + '/' + this.currentSegmentId + '" id="segment-' + this.currentSegmentId + '-context" class="context" title="Open context" target="_blank">Context</a>';
		$('#' + this.currentSegment.attr('id') + '-header').html(header);

        if ( this.currentSegment.data( 'autopropagated' ) && !$( '.header .repetition', this.currentSegment ).length ) {
            $( '.header', this.currentSegment ).prepend( '<span class="repetition">Autopropagated</span>' );
        }

    },
	createJobMenu: function() {
		var menu = '<nav id="jobMenu" class="topMenu">' +
				'    <ul>';
		$.each(config.firstSegmentOfFiles, function() {
			menu += '<li data-file="' + this.id_file + '" data-segment="' + this.first_segment + '"><span class="' + UI.getIconClass(this.file_name.split('.')[this.file_name.split('.').length -1]) + '"></span><a href="#" title="' + this.file_name + '" >' + this.file_name + '</a></li>';
		});

		menu += '    </ul>' +
				'	<ul>' +
				'		<li class="currSegment" data-segment="' + UI.currentSegmentId + '"><a href="#">Go to current segment</a></li>' +
				'    </ul>' +
				'</nav>';
		this.body.append(menu);
	},
	displaySurvey: function(s) {
        if(this.surveyDisplayed) return;
        survey = '<div class="modal survey" data-type="view">' +
                '	<div class="popup-outer"></div>' +
                '	<div class="popup survey">' +
                '		<a href="#" class="x-popup"></a>' +
                '		<h1>Translation Completed - Take a Survey</h1>' +
                '		<p class="surveynotice">To stop displaying the survey, click on the <b>X</b> icon on the top right corner of this popup.</p>' +
                '		<div class="popup-box">' +
                '			<iframe src="' + s + '" width="100%" height="670" frameborder="0" marginheight="0" marginwidth="0">Loading ...</iframe>' +
                '		</div>' +
                '	</div>' +
                '</div>';
        this.body.append(survey);
        $('.modal.survey').show();
	},
	surveyAlreadyDisplayed: function() {
		if(typeof $.cookie('surveyedJobs') != 'undefined') {
			var c = $.cookie('surveyedJobs');
			surv = c.split('||')[0];
			if(config.survey === surv) {
				jobs = $.cookie('surveyedJobs').split('||')[1].split(',');
				var found = false;
				$.each(jobs, function() {
					if(this == config.job_id) {
						found = true;
					}
				});
				return found;
			} else {
                return true;
            }
		} else {
			return false;
		}
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

    getIconClass: function(ext) {
		c =		(
					(ext == 'doc')||
					(ext == 'dot')||
					(ext == 'docx')||
					(ext == 'dotx')||
					(ext == 'docm')||
					(ext == 'dotm')||
					(ext == 'odt')||
					(ext == 'sxw')
				)?				'extdoc' :
				(
					(ext == 'pot')||
					(ext == 'pps')||
					(ext == 'ppt')||
					(ext == 'potm')||
					(ext == 'potx')||
					(ext == 'ppsm')||
					(ext == 'ppsx')||
					(ext == 'pptm')||
					(ext == 'pptx')||
					(ext == 'odp')||
					(ext == 'sxi')
				)?				'extppt' :
				(
					(ext == 'htm')||
					(ext == 'html')
				)?				'exthtm' :
				(ext == 'pdf')?		'extpdf' :
				(
					(ext == 'xls')||
					(ext == 'xlt')||
					(ext == 'xlsm')||
					(ext == 'xlsx')||
					(ext == 'xltx')||
					(ext == 'ods')||
					(ext == 'sxc')||
					(ext == 'csv')
				)?				'extxls' :
				(ext == 'txt')?		'exttxt' :
				(ext == 'ttx')?		'extttx' :
				(ext == 'itd')?		'extitd' :
				(ext == 'xlf')?		'extxlf' :
				(ext == 'mif')?		'extmif' :
				(ext == 'idml')?	'extidd' :
				(ext == 'xtg')?		'extqxp' :
				(ext == 'xml')?		'extxml' :
				(ext == 'rc')?		'extrcc' :
				(ext == 'resx')?		'extres' :
				(ext == 'sgml')?	'extsgl' :
				(ext == 'sgm')?		'extsgm' :
				(ext == 'properties')? 'extpro' :
								'extxif';
		return c;
	},
	createStatusMenu: function(statusMenu) {
		$("ul.statusmenu").empty().hide();
		var menu = '<li class="arrow"><span class="arrow-mcolor"></span></li><li><a href="#" class="f" data-sid="segment-' + this.currentSegmentId + '" title="set draft as status">DRAFT</a></li><li><a href="#" class="d" data-sid="segment-' + this.currentSegmentId + '" title="set translated as status">TRANSLATED</a></li><li><a href="#" class="a" data-sid="segment-' + this.currentSegmentId + '" title="set approved as status">APPROVED</a></li><li><a href="#" class="r" data-sid="segment-' + this.currentSegmentId + '" title="set rejected as status">REJECTED</a></li>';
		statusMenu.html(menu).show();
	},
	deActivateSegment: function(byButton) {
		this.removeButtons(byButton);
		this.removeHeader(byButton);
		this.removeFooter(byButton);
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
			this.startSegmentId = (hash) ? hash : config.last_opened_segment;
		}
	},
// temp
//	enableSearch: function() {
//		$('#filterSwitch').show();
//		this.searchEnabled = true;
//	},
    fixHeaderHeightChange: function() {
        headerHeight = $('header .wrapper').height() + ((this.body.hasClass('filterOpen'))? $('header .searchbox').height() : 0) + ((this.body.hasClass('incomingMsg'))? $('header #messageBar').height() : 0);
        $('#outer').css('margin-top', headerHeight + 'px');
    },

    nextUnloadedResultSegment: function() {
		var found = '';
		var last = this.getSegmentId($('section').last());
//		var last = $('section').last().attr('id').split('-')[1];
		$.each(this.searchResultsSegments, function() {
//            var start = new Date().getTime();
//            for (var i = 0; i < 1e7; i++) {
//                if ((new Date().getTime() - start) > 2000 ){
//                    break;
//                }
//            }

			//controlla che il segmento non sia nell'area visualizzata?
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
		if ((where == 'after') && (this.noMoreSegmentsAfter))
			return;
		if ((where == 'before') && (this.noMoreSegmentsBefore))
			return;
		if (this.loadingMore) {
			return;
		}
		this.loadingMore = true;

		var segId = this.detectRefSegId(where);

		if (where == 'before') {
			$("section").each(function() {
				if ($(this).offset().top > $(window).scrollTop()) {
//				if ($(this).offset().top > $(window).scrollTop()) {
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
				jid: config.job_id,
				password: config.password,
				step: UI.moreSegNum,
				segment: segId,
				where: where
			},
			error: function() {
				UI.failedConnection(0, 'getMoreSegments');
			},
			success: function(d) {
				UI.getMoreSegments_success(d);
			}
		});
	},
	getMoreSegments_success: function(d) {
		if (d.errors.length)
			this.processErrors(d.errors, 'getMoreSegments');
		where = d.data.where;
        section = $('section');
		if (typeof d.data.files != 'undefined') {
			firstSeg = section.first();
			lastSeg = section.last();
			var numsegToAdd = 0;
			$.each(d.data.files, function() {
				numsegToAdd = numsegToAdd + this.segments.length;
			});

            SegmentNotes.enabled() && SegmentNotes.registerSegments ( d.data );

			this.renderFiles(d.data.files, where, false);

			// if getting segments before, UI points to the segment triggering the event
			if ((where == 'before') && (numsegToAdd)) {
				this.scrollSegment($('#segment-' + this.segMoving));
			}

			if (this.body.hasClass('searchActive')) {
				segLimit = (where == 'before') ? firstSeg : lastSeg;
				this.markSearchResults({
					where: where,
					seg: segLimit
				});
			} else {
				this.markTags();
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
        $(window).trigger('segmentsAdded');
	},
	getNextSegment: function(segment, status) {//console.log('getNextSegment: ', segment);
		var seg = this.currentSegment;

		var rules = (status == 'untranslated') ? 'section.status-draft:not(.readonly), section.status-rejected:not(.readonly), section.status-new:not(.readonly)' : 'section.status-' + status + ':not(.readonly)';
		var n = $(seg).nextAll(rules).first();
//		console.log('$(seg).nextAll().length: ', $(seg).nextAll().length);
//		console.log('n.length 1: ', n.length);
		if (!n.length) {
			n = $(seg).parents('article').next().find(rules).first();
		}
//		console.log('n.length 2: ', n.length);
//		console.log('UI.nextUntranslatedSegmentIdByServer: ', UI.nextUntranslatedSegmentIdByServer);
//		console.log('UI.noMoreSegmentsAfter: ', UI.noMoreSegmentsAfter);
		if (n.length) { // se ci sono sotto segmenti caricati con lo status indicato
			this.nextUntranslatedSegmentId = this.getSegmentId($(n));
		} else {
			this.nextUntranslatedSegmentId = UI.nextUntranslatedSegmentIdByServer;
		}
//		} else if ((UI.nextUntranslatedSegmentIdByServer) && (!UI.noMoreSegmentsAfter)) {
//			console.log('2');
//			this.nextUntranslatedSegmentId = UI.nextUntranslatedSegmentIdByServer;
//		} else {
//			console.log('3');
//			this.nextUntranslatedSegmentId = 0;
//		}
//		console.log('UI.nextUntranslatedSegmentId: ', UI.nextUntranslatedSegmentId);
//console.log('seg: ', seg);
        var i = $(seg).next();
//console.log('i: ', i);
        if (!i.length) {
			i = $(seg).parents('article').next().find('section').first();
		}
		if (i.length) {
			this.nextSegmentId = this.getSegmentId($(i));
		} else {
			this.nextSegmentId = 0;
		}
	},
	getPercentuageClass: function(match) {
		var percentageClass = "";
		m_parse = parseInt(match);
		if (!isNaN(m_parse)) {
			match = m_parse;
		}

		switch (true) {
			case (match == 100):
				percentageClass = "per-green";
				break;
			case (match == 101):
				percentageClass = "per-blue";
				break;
			case(match > 0 && match <= 99):
				percentageClass = "per-orange";
				break;
			case (match == "MT"):
				percentageClass = "per-yellow";
				break;
			default :
				percentageClass = "";
		}
		return percentageClass;
	},
	getSegments: function(options) {
        console.log('get segments');

//        console.log('options: ', options);
		where = (this.startSegmentId) ? 'center' : 'after';
		var step = this.initSegNum;
		$('#outer').addClass('loading');
		var seg = (options.segmentToScroll) ? options.segmentToScroll : this.startSegmentId;

		APP.doRequest({
			data: {
				action: 'getSegments',
				jid: config.job_id,
				password: config.password,
				step: step,
				segment: seg,
				where: where
			},
			error: function() {
				UI.failedConnection(0, 'getSegments');
			},
			success: function(d) {
                if($.cookie('tmpanel-open') == '1') UI.openLanguageResourcesPanel();
				UI.getSegments_success(d, options);
			}
		});
	},
	getSegments_success: function(d, options) {
        if (d.errors.length)
			this.processErrors(d.errors, 'getSegments');
		where = d.data.where;

        SegmentNotes.enabled() && SegmentNotes.registerSegments ( d.data );

		$.each(d.data.files, function() {
			startSegmentId = this.segments[0].sid;
		});
		if (typeof this.startSegmentId == 'undefined')
			this.startSegmentId = startSegmentId;
		this.body.addClass('loaded');


		if (typeof d.data.files != 'undefined') {
			this.renderFiles(d.data.files, where, this.firstLoad);
			if ((options.openCurrentSegmentAfter) && (!options.segmentToScroll) && (!options.segmentToOpen)) {
                seg = (UI.firstLoad) ? this.currentSegmentId : UI.startSegmentId;
				this.gotoSegment(seg);
			}
			if (options.segmentToScroll) {
//                seg = (UI.firstLoad)? this.currentSegmentId : UI.startSegmentId;
				this.scrollSegment($('#segment-' + options.segmentToScroll));
			}
			if (options.segmentToOpen) {
				$('#segment-' + options.segmentToOpen + ' .editarea').click();
			}

			if (($('#segment-' + UI.currentSegmentId).length) && (!$('section.editor').length)) {
				UI.openSegment(UI.editarea);
			}
			if (options.caller == 'link2file') {
				if (UI.segmentIsLoaded(UI.currentSegmentId)) {
					UI.openSegment(UI.editarea);
				}
			}

			if ($('#segment-' + UI.startSegmentId).hasClass('readonly')) {
				this.scrollSegment($('#segment-' + UI.startSegmentId));
			}

			if (options.applySearch) {
				$('mark.currSearchItem').removeClass('currSearchItem');
				this.markSearchResults();
				if (this.searchMode == 'normal') {
					$('#segment-' + options.segmentToScroll + ' mark.searchMarker').first().addClass('currSearchItem');
//				} else if (this.searchMode == 'source&target') {
//					$('#segment-' + options.segmentToScroll).addClass('currSearchSegment');
				} else {
					$('#segment-' + options.segmentToScroll + ' .editarea mark.searchMarker').first().addClass('currSearchItem');
//					$('#segment-' + options.segmentToScroll).addClass('currSearchSegment');
				}
			}
		}
		$('#outer').removeClass('loading loadingBefore');
		if(options.highlight) {
			UI.highlightEditarea($('#segment-' + options.segmentToScroll));
		}
		this.loadingMore = false;
		this.setWaypoints();
//		console.log('prova a: ', $('#segment-13655401 .editarea').html());
		this.markTags();
//		console.log('prova b: ', $('#segment-13655401 .editarea').html());
		this.checkPendingOperations();
        $(document).trigger('getSegments_success');

	},
	getSegmentSource: function(seg) {
		segment = (typeof seg == 'undefined') ? this.currentSegment : seg;
		return $('.source', segment).text();
	},
	getStatus: function(segment) {
		status = ($(segment).hasClass('status-new') ? 'new' : $(segment).hasClass('status-draft') ? 'draft' : $(segment).hasClass('status-translated') ? 'translated' : $(segment).hasClass('status-approved') ? 'approved' : 'rejected');
		return status;
	},
	getSegmentTarget: function(seg) {
		editarea = (typeof seg == 'undefined') ? this.editarea : $('.editarea', seg);
		return editarea.text();
	},
	getUpdates: function() {
		if (UI.chunkedSegmentsLoaded()) {
			lastUpdateRequested = UI.lastUpdateRequested;
			UI.lastUpdateRequested = new Date();
			APP.doRequest({
				data: {
					action: 'getUpdatedTranslations',
					last_timestamp: lastUpdateRequested.getTime(),
					first_segment: UI.getSegmentId($('section').first()),
//					first_segment: $('section').first().attr('id').split('-')[1],
					last_segment: UI.getSegmentId($('section').last())
//					last_segment: $('section').last().attr('id').split('-')[1]
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
			$('.editarea, .area', seg).text(this.translation);
//			if (UI.body.hasClass('searchActive'))
//				UI.markSearchResults({
//					singleSegment: segment,
//					where: 'no'
//				})
			status = (this.status == 'DRAFT') ? 'draft' : (this.status == 'TRANSLATED') ? 'translated' : (this.status == 'APPROVED') ? 'approved' : (this.status == 'REJECTED') ? 'rejected' : '';
			UI.setStatus(seg, status);
		});
	},
	test: function(params) {
		console.log('params: ', params);
		console.log('giusto');
	},
	gotoNextSegment: function() {
		var next = $('.editor').next();
		if (next.is('section')) {
			this.scrollSegment(next);
			$('.editarea', next).trigger("click", "moving");
		} else {
			next = this.currentFile.next().find('section:first');
			if (next.length) {
				this.scrollSegment(next);
				$('.editarea', next).trigger("click", "moving");
			} else {
                UI.closeSegment(UI.currentSegment, 1, 'save');
            }
		}
	},
	gotoNextUntranslatedSegment: function() {console.log('gotoNextUntranslatedSegment');
		if (!UI.segmentIsLoaded(UI.nextUntranslatedSegmentId)) {
			if (!UI.nextUntranslatedSegmentId) {
				UI.closeSegment(UI.currentSegment);
			} else {
				UI.reloadWarning();
			}
		} else {
			$("#segment-" + UI.nextUntranslatedSegmentId + " .editarea").trigger("click");
		}
	},

	gotoOpenSegment: function(quick) {
        quick = quick || false;

        if ($('#segment-' + this.currentSegmentId).length) {
			this.scrollSegment(this.currentSegment, false, quick);
		} else {
			$('#outer').empty();
			this.render({
				firstLoad: false,
				segmentToOpen: this.currentSegmentId
			});
		}
		$(window).trigger({
			type: "scrolledToOpenSegment",
			segment: this.currentSegment
		});
	},
	gotoPreviousSegment: function() {
		var prev = $('.editor').prev();
		if (prev.is('section')) {
			$('.editarea', prev).click();
		} else {
			prev = $('.editor').parents('article').prev().find('section:last');
			if (prev.length) {
				$('.editarea', prev).click();
			} else {
				this.topReached();
			}
		}
		if (prev.length)
			this.scrollSegment(prev);
	},
	gotoSegment: function(id) {
        if ( !this.segmentIsLoaded(id) && UI.parsedHash.splittedSegmentId ) {
            id = UI.parsedHash.splittedSegmentId ;
        }

        if ( MBC.enabled() && MBC.wasAskedByCommentHash( id ) ) {
            MBC.openSegmentComment( UI.Segment.findEl( id ) ) ;
        } else {
            // TODO: question: why search for #segment-{id}-target
            // instead of #segment-{id} as usual?
            var el = $("#segment-" + id + "-target").find(".editarea");
            $(el).click();
        }
    },
	initSegmentNavBar: function() {
		if (config.firstSegmentOfFiles.length == 1) {
			$('#segmentNavBar .prevfile, #segmentNavBar .nextfile').addClass('disabled');
		}
	},
	justSelecting: function(what) {
		if (window.getSelection().isCollapsed)
			return false;
		var selContainer = $(window.getSelection().getRangeAt(0).startContainer.parentNode);
		console.log(selContainer);
		if (what == 'editarea') {
			return ((selContainer.hasClass('editarea')) && (!selContainer.is(UI.editarea)));
		} else if (what == 'readonly') {
			return ((selContainer.hasClass('area')) || (selContainer.hasClass('source')));
		}
	},
/*
// not used anymore?

	closeInplaceEditor: function(ed) {
		$(ed).removeClass('editing');
		$(ed).attr('contenteditable', false);
		$('.graysmall .edit-buttons').remove();
	},
	openInplaceEditor: function(ed) {
		$('.graysmall .translation.editing').each(function() {
			UI.closeInplaceEditor($(this));
		});
		$(ed).addClass('editing').attr('contenteditable', true).after('<span class="edit-buttons"><button class="cancel">Cancel</button><button class="save">Save</button></span>');
		$(ed).focus();
	},
*/
	millisecondsToTime: function(milli) {
//		var milliseconds = milli % 1000;
		var seconds = Math.round((milli / 1000) % 60);
		var minutes = Math.floor((milli / (60 * 1000)) % 60);
		return [minutes, seconds];
	},
	closeContextMenu: function() {
		$('#contextMenu').hide();
		$('#spellCheck .words').remove();
	},
	openSegment: function(editarea, operation) {
        // TODO: check why this global var is needed
		segment_id = $(editarea).attr('data-sid');
		var segment = $('#segment-' + segment_id);

        if (Review.enabled() && !Review.evalOpenableSegment( segment )) {
            return false ;
        }

        this.openSegmentStart = new Date();
		if(UI.warningStopped) {
			UI.warningStopped = false;
			UI.checkWarnings(false);
		}
		if (!this.byButton) {
			if (this.justSelecting('editarea'))
				return;
		}

        this.numOpenedSegments++;
		this.firstOpenedSegment = (this.firstOpenedSegment === 0) ? 1 : 2;
		this.byButton = false;
		this.cacheObjects(editarea);
		this.updateJobMenu();

		this.clearUndoStack();
		this.saveInUndoStack('open');
		this.autoSave = true;

		var s1 = $('#segment-' + this.lastTranslatedSegmentId + ' .source').text();
		var s2 = $('.source', segment).text();
		var isNotSimilar = lev(s1,s2)/Math.max(s1.length,s2.length)*100 >50;
		var isEqual = (s1 == s2);

		getNormally = isNotSimilar || isEqual;

		this.activateSegment(getNormally);

        segment.trigger('open');

        $('section').first().nextAll('.undoCursorPlaceholder').remove();

        this.getNextSegment(this.currentSegment, 'untranslated');


		if ((!this.readonly)&&(!getNormally)) {
			$('#segment-' + segment_id + ' .alternatives .overflow').hide();
		}

		this.setCurrentSegment();

		if (!this.readonly) {
            // XXX Arcane, what's this code for?
			if(getNormally) {
				this.getContribution(segment, 0);
			} else {
				console.log('riprova dopo 3 secondi');
				$(segment).removeClass('loaded');
				$(".loader", segment).addClass('loader_on');
				setTimeout(function() {
					$('.alternatives .overflow', segment).show();
					UI.getContribution(segment, 0);
				}, 3000);
			}
		}

		this.currentSegment.addClass('opened');

		this.currentSegment.attr('data-searchItems', ($('mark.searchMarker', this.editarea).length));

		this.fillCurrentSegmentWarnings(this.globalWarnings, true);
		this.setNextWarnedSegment();

		this.focusEditarea = setTimeout(function() {
			UI.editarea.focus();
			clearTimeout(UI.focusEditarea);
            UI.currentSegment.trigger('EditAreaFocused');
		}, 100);
		this.currentIsLoaded = false;
		this.nextIsLoaded = false;



		if(!this.noGlossary) this.getGlossary(segment, true, 0);
		this.opening = true;
		if (!(this.currentSegment.is(this.lastOpenedSegment))) {
			var lastOpened = $(this.lastOpenedSegment).attr('id');
			if (lastOpened != 'segment-' + this.currentSegmentId)
				this.closeSegment(this.lastOpenedSegment, 0, operation);
		}
		this.opening = false;
		this.body.addClass('editing');

		segment.addClass("editor");
		if (!this.readonly)
			this.editarea.attr('contenteditable', 'true');
		this.editStart = new Date();
		$(editarea).removeClass("indent");

		this.lockTags();
		if (!this.readonly) {
			this.getContribution(segment, 1);
			this.getContribution(segment, 2);

			if(!this.noGlossary) this.getGlossary(segment, true, 1);
			if(!this.noGlossary) this.getGlossary(segment, true, 2);
		}
		if (this.debug)
			console.log('close/open time: ' + ((new Date()) - this.openSegmentStart));

        $(window).trigger({
            type: "segmentOpened",
            segment: segment
        });
    },

    reactivateJob: function() {
        APP.doRequest({
            data: {
                action:         "changeJobsStatus",
                new_status:     "active",
                res:            "job",
                id:             config.job_id,
                password:      config.password,
            },
            success: function(d){
                if(d.data == 'OK') {
                    setTimeout(function() {
                        location.reload(true);
                    }, 300);
                }
            }
        });
    },
    placeCaretAtEnd: function(el) {
//		console.log(el);
//		console.log($(el).first().get().className);
//		var range = document.createRange();
//		var sel = window.getSelection();
//		range.setStart(el, 1);
//		range.collapse(true);
//		sel.removeAllRanges();
//		sel.addRange(range);
//		el.focus();

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
			UI.currentSegmentQA();
		}, config.segmentQACheckInterval);
	},
	reloadToSegment: function(segmentId) {
		this.infiniteScroll = false;
		config.last_opened_segment = segmentId;
		window.location.hash = segmentId;
		$('#outer').empty();
		this.render({
			firstLoad: false
		});
	},
	renderUntranslatedOutOfView: function() {
		this.infiniteScroll = false;
		config.last_opened_segment = this.nextUntranslatedSegmentId;
		window.location.hash = this.nextUntranslatedSegmentId;
		$('#outer').empty();
		this.render({
			firstLoad: false
		});
	},
	reloadWarning: function() {
		this.renderUntranslatedOutOfView();
//        APP.confirm({msg: 'The next untranslated segment is outside the current view.', callback: 'renderUntranslatedOutOfView' });
	},
	pointBackToSegment: function(segmentId) {
		if (!this.infiniteScroll)
			return;
		if (segmentId === '') {
			this.startSegmentId = config.last_opened_segment;
			$('#outer').empty();
			this.render({
				firstLoad: false
			});
		} else {
			$('#outer').empty();
			this.render({
				firstLoad: false
			});
		}
	},
	pointToOpenSegment: function(quick) {
        quick = quick || false;
        this.gotoOpenSegment(quick);
	},
	removeButtons: function(byButton) {
		var segment = (byButton) ? this.currentSegment : this.lastOpenedSegment;
		$('#' + segment.attr('id') + '-buttons').empty();
		$('p.warnings', segment).remove();
//		$('p.alternatives', segment).remove();
	},
	removeFooter: function(byButton) {
		var segment = (byButton) ? this.currentSegment : this.lastOpenedSegment;
		$('#' + segment.attr('id') + ' .footer').empty();
	},
	removeHeader: function(byButton) {
		var segment = (byButton) ? this.currentSegment : this.lastOpenedSegment;
		$('#' + segment.attr('id') + '-header').empty();
	},
	removeStatusMenu: function(statusMenu) {
		statusMenu.empty().hide();
	},
	renderFiles: function(files, where, starting) {

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
//                '			To-do: <strong>' + fs['DRAFT_FORMATTED'] + '</strong>'+
//						'			<span id="rejected" class="hidden">Rejected: <strong>' + fs.REJECTED_FORMATTED + '</strong></span>' +
						'		</li>' +
						'	</ul>';
			}

            newSegments = UI.renderSegments(this.segments, false);
            newFile += newSegments;
			if (articleToAdd) {
				newFile += '</article>';
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
		});
		if (starting) {
			this.init();
		}
	},
    stripSpans: function (str) {
        return str.replace(/<span(.*?)>/gi, '').replace(/<\/span>/gi, '');
    },
    normalizeSplittedSegments: function (segments) {
//        console.log('segments: ', segments);

        newSegments = [];
        $.each(segments, function (index) {
//            console.log('seg: ', this.segment.split(UI.splittedTranslationPlaceholder));
//            console.log('aaa: ', this.segment);
            splittedSourceAr = this.segment.split(UI.splittedTranslationPlaceholder);
//            console.log('splittedSourceAr: ', splittedSourceAr);
            if(splittedSourceAr.length > 1) {
//            if(this.split_points_source.length) {
//                console.log('a');
                segment = this;
                splitGroup = [];
                $.each(splittedSourceAr, function (i) {
                    splitGroup.push(segment.sid + '-' + (i + 1));
                });

                $.each(splittedSourceAr, function (i) {
//                    console.log('bbb: ', this);
//                    console.log('source?: ', segment.segment.substring(segment.split_points_source[i], segment.split_points_source[i+1]));
                    translation = segment.translation.split(UI.splittedTranslationPlaceholder)[i];
//                    translation = (segment.translation == '')? '' : segment.translation.substring(segment.split_points_target[i], segment.split_points_target[i+1]);
//                    console.log('ddd: ', this);
                    //temp
                    //segment.target_chunk_lengths = {"len":[0,9,13],"statuses":["TRANSLATED","APPROVED"]};
                    //end temp
                    status = segment.target_chunk_lengths.statuses[i];
//                    console.log('vediamo status: ', status);
                    segData = {
                        autopropagated_from: "0",
                        has_reference: "false",
                        parsed_time_to_edit: ["00", "00", "00", "00"],
                        readonly: "false",
                        segment: splittedSourceAr[i],
//                        segment: segment.segment.substring(segment.split_points_source[i], segment.split_points_source[i+1]),
                        segment_hash: segment.segment_hash,
                        sid: segment.sid + '-' + (i + 1),
                        split_group: splitGroup,
                        split_points_source: [],
                        status: status,
                        time_to_edit: "0",
                        translation: translation,
                        version: segment.version,
                        warning: "0"
                    }
                    newSegments.push(segData);
                    segData = null;
                });
            } else {
//                console.log('b');
                newSegments.push(this);
            }

        });
// console.log('newsegments 1: ', newSegments);
        return newSegments;
    },

    renderSegments: function (segments, justCreated, splitAr, splitGroup) {
        segments = this.normalizeSplittedSegments(segments);
        splitAr = splitAr || [];
        splitGroup = splitGroup || [];
        var t = config.time_to_edit_enabled;
        newSegments = '';
        $.each(segments, function(index) {
//                this.readonly = true;
            var readonly = ((this.readonly == 'true')||(UI.body.hasClass('archived'))) ? true : false;
            var autoPropagated = this.autopropagated_from != 0;
            // temp, simulation
//            this.same_source_segments = true;
            // end temp
            var autoPropagable = (this.repetitions_in_chunk == "1")? false : true;
//            console.log('this: ', this);
            if(typeof this.segment == 'object') console.log(this);
//            console.log('this.segment: ', this);

            try {
                if($.parseHTML(this.segment).length) {
                    this.segment = UI.stripSpans(this.segment);
                };
            } catch ( e ){
                //if we split a segment in more than 3 parts and reload the cattool
                //this exception is raised:
                // Uncaught TypeError: Cannot read property 'length' of null
                //so SKIP in a catched exception
            }

//            console.log('corrected: ', this.segment.replace(/<span(.*?)>/gi, ''));
            var escapedSegment = htmlEncode(this.segment.replace(/\"/g, "&quot;"));
//            console.log('escapedSegment: ', escapedSegment);

            /* this is to show line feed in source too, because server side we replace \n with placeholders */
            escapedSegment = escapedSegment.replace( config.lfPlaceholderRegex, "\n" );
            escapedSegment = escapedSegment.replace( config.crPlaceholderRegex, "\r" );
            escapedSegment = escapedSegment.replace( config.crlfPlaceholderRegex, "\r\n" );
            originalId = this.sid.split('-')[0];
            if((typeof this.split_points_source == 'undefined') || (!this.split_points_source.length) || justCreated) {
                newSegments += UI.getSegmentMarkup(this, t, readonly, autoPropagated, autoPropagable, escapedSegment, splitAr, splitGroup, originalId, 0);
            } else {

            }

        });
        return newSegments;
    },

    saveSegment: function(segment) {
		var status = (segment.hasClass('status-translated')) ? 'translated' : (segment.hasClass('status-approved')) ? 'approved' : (segment.hasClass('status-rejected')) ? 'rejected' : (segment.hasClass('status-new')) ? 'new' : 'draft';
		if (status == 'new') {
			status = 'draft';
		}
		console.log('SAVE SEGMENT');
		this.setTranslation({
            id_segment: this.getSegmentId(segment),
            status: status,
            caller: 'autosave'
        });
//		this.setTranslation(this.getSegmentId(segment), status, 'autosave');
		segment.addClass('saved');
	},
	renderAndScrollToSegment: function(sid) {
		$('#outer').empty();
		this.render({
			firstLoad: false,
			caller: 'link2file',
			segmentToScroll: sid,
			scrollToFile: true
		});
//        this.render(false, segment.selector.split('-')[1]);
	},

	spellCheck: function(ed) {
		if (!UI.customSpellcheck)
			return false;
		editarea = (typeof ed == 'undefined') ? UI.editarea : $(ed);
		if ($('#contextMenu').css('display') == 'block')
			return true;

		APP.doRequest({
			data: {
				action: 'getSpellcheck',
				lang: config.target_rfc,
				sentence: UI.editarea.text()
			},
			context: editarea,
			error: function() {
				UI.failedConnection(0, 'getSpellcheck');
			},
			success: function(data) {
				ed = this;
				$.each(data.result, function(key, value) { //key --> 0: { 'word': { 'offset':20, 'misses':['word1','word2'] } }

					var word = Object.keys(value)[0];
					replacements = value[word].misses.join(",");

//					var Position = [
//						parseInt(value[word].offset),
//						parseInt(value[word].offset) + parseInt(word.length)
//					];

//					var sentTextInPosition = ed.text().substring(Position[0], Position[1]);
					//console.log(sentTextInPosition);

					var re = new RegExp("(\\b" + word + "\\b)", "gi");
					$(ed).html($(ed).html().replace(re, '<span class="misspelled" data-replacements="' + replacements + '">$1</span>'));
					// fix nested encapsulation
					$(ed).html($(ed).html().replace(/(<span class=\"misspelled\" data-replacements=\"(.*?)\"\>)(<span class=\"misspelled\" data-replacements=\"(.*?)\"\>)(.*?)(<\/span\>){2,}/gi, "$1$5</span>"));
//
//                    });
				});
			}
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
	setCurrentSegment: function(closed) {
		reqArguments = arguments;
		var id_segment = this.currentSegmentId;
		if (closed) {
			id_segment = 0;
			UI.currentSegment = undefined;
		} else {
			setTimeout(function() {
//				var hash_value = window.location.hash;
				window.location.hash = UI.currentSegmentId;
			}, 300);
		}
//        if(id_segment.toString().split('-').length > 1) id_segment = id_segment.toString().split('-');
//		var file = this.currentFile;
		if (this.readonly)
			return;
		APP.doRequest({
			data: {
				action: 'setCurrentSegment',
				password: config.password,
				id_segment: id_segment.toString(),
//				id_segment: id_segment.toString().split('-')[0],
				id_job: config.job_id
			},
			context: [reqArguments, id_segment],
			error: function() {
				UI.failedConnection(this[0], 'setCurrentSegment');
			},
			success: function(d) {
				UI.setCurrentSegment_success(this[1], d);
			}
		});
	},
	setCurrentSegment_success: function(id_segment, d) {
		if (d.errors.length) {
			this.processErrors(d.errors, 'setCurrentSegment');
        }

		this.nextUntranslatedSegmentIdByServer = d.nextSegmentId;
        this.propagationsAvailable = d.data.prop_available;
		this.getNextSegment(this.currentSegment, 'untranslated');

        if (config.alternativesEnabled) {
            this.getTranslationMismatches(id_segment);
        }
console.log('VEDIAMO: ', id_segment);
        $('html').trigger('setCurrentSegment_success', [d, id_segment]);
    },
    getTranslationMismatches: function (id_segment) {
        APP.doRequest({
            data: {
                action: 'getTranslationMismatches',
                password: config.password,
                id_segment: id_segment.toString(),
                id_job: config.job_id
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
//		if(d.data.editable.length + d.data.not_editable.length) {
//			if(!$('.header .repetition', UI.currentSegment).length) $('.header', UI.currentSegment).prepend('<span class="repetition">Autopropagated</span>');
//		}
        sameContentIndex = -1;
        $.each(d.data.editable, function(ind) {
            //Remove trailing spaces for string comparison
//            console.log( "PostProcessEditArea: " + UI.postProcessEditarea( UI.currentSegment ).replace( /[ \xA0]+$/ , '' ) );
//            console.log( "SetCurrSegmentValue: " + this.translation );
            if( this.translation == UI.postProcessEditarea( UI.currentSegment ).replace( /[ \xA0]+$/ , '' ) ) {
                sameContentIndex = ind;
            }
        });
        if(sameContentIndex != -1) d.data.editable.splice(sameContentIndex, 1);

        sameContentIndex1 = -1;
        $.each(d.data.not_editable, function(ind) {
            //Remove trailing spaces for string comparison
            if( this.translation == UI.postProcessEditarea( UI.currentSegment ).replace( /[ \xA0]+$/ , '' ) ) sameContentIndex1 = ind;
        });
        if(sameContentIndex1 != -1) d.data.not_editable.splice(sameContentIndex1, 1);

        numAlt = d.data.editable.length + d.data.not_editable.length;
        numSeg = 0;
        $.each(d.data.editable, function() {
            numSeg += this.involved_id.length;
        });
//		console.log('numAlt: ', numAlt);
//		console.log('numSeg: ', numSeg);
        if(numAlt) {
//            UI.currentSegment.find('.status-container').after('<p class="alternatives"><a href="#">Already translated in ' + ((numAlt > 1)? 'other ' + numAlt + ' different' : 'another') + ' way' + ((numAlt > 1)? 's' : '') + '</a></p>');
            tab = UI.currentSegment.find('.tab-switcher-al');
            tab.find('.number').text('(' + numAlt + ')');
            UI.renderAlternatives(d);
            tab.show();
//            tab.trigger('click');
        }
    },
    renderAlternatives: function(d) {
//        console.log('renderAlternatives d: ', d);
//		console.log($('.editor .submenu').length);
//		console.log(UI.currentSegmentId);
        segment = UI.currentSegment;
        segment_id = UI.currentSegmentId;
        escapedSegment = UI.decodePlaceholdersToText(UI.currentSegment.find('.source').html(), false, segment_id, 'render alternatives');
//        console.log('escapedSegment: ', escapedSegment);
/*
		function prepareTranslationDiff( translation ){
			_str = translation.replace( config.lfPlaceholderRegex, "\n" )
					.replace( config.crPlaceholderRegex, "\r" )
					.replace( config.crlfPlaceholderRegex, "\r\n" )
					.replace( config.tabPlaceholderRegex, "\t" )
				//.replace( config.tabPlaceholderRegex, String.fromCharCode( parseInt( 0x21e5, 10 ) ) )
					.replace( config.nbspPlaceholderRegex, String.fromCharCode( parseInt( 0xA0, 10 ) ) );

			_str  = htmlDecode(_str );
			_edit = UI.currentSegment.find('.editarea').text().replace( String.fromCharCode( parseInt( 0x21e5, 10 ) ), "\t" );

			//Prepend Unicode Character 'ZERO WIDTH SPACE' invisible, not printable, no spaced character,
			//used to detect initial and final spaces in html diff
			_str  = String.fromCharCode( parseInt( 0x200B, 10 ) ) + _str + String.fromCharCode( parseInt( 0x200B, 10 ) );
			_edit = String.fromCharCode( parseInt( 0x200B, 10 ) ) + _edit + String.fromCharCode( parseInt( 0x200B, 10 ) );

			diff_obj = UI.dmp.diff_main( _edit, _str );
			UI.dmp.diff_cleanupEfficiency( diff_obj );
			return diff_obj;
		}
*/
        mainStr = UI.currentSegment.find('.editarea').text();
        $.each(d.data.editable, function(index) {
//            console.log('this.translation: ', this.translation);
            diff_obj = UI.execDiff(mainStr, this.translation);
//            diff_obj = prepareTranslationDiff( this.translation );
            $('.sub-editor.alternatives .overflow', segment).append('<ul class="graysmall" data-item="' + (index + 1) + '"><li class="sugg-source"><span id="' + segment_id + '-tm-' + this.id + '-source" class="suggestion_source">' + escapedSegment + '</span></li><li class="b sugg-target"><!-- span class="switch-editing">Edit</span --><span class="graysmall-message">CTRL+' + (index + 1) + '</span><span class="translation">' + UI.dmp.diff_prettyHtml(diff_obj) + '</span><span class="realData hide">' + this.translation + '</span></li><li class="goto"><a href="#" data-goto="' + this.involved_id[0]+ '">View</a></li></ul>');
        });

        $.each(d.data.not_editable, function(index1) {
            diff_obj = UI.execDiff(mainStr, this.translation);
            $('.sub-editor.alternatives .overflow', segment).append('<ul class="graysmall notEditable" data-item="' + (index1 + d.data.editable.length + 1) + '"><li class="sugg-source"><span id="' + segment_id + '-tm-' + this.id + '-source" class="suggestion_source">' + escapedSegment + '</span></li><li class="b sugg-target"><!-- span class="switch-editing">Edit</span --><span class="graysmall-message">CTRL+' + (index1 + d.data.editable.length + 1) + '</span><span class="translation">' + UI.dmp.diff_prettyHtml(diff_obj) + '</span><span class="realData hide">' + this.translation + '</span></li><li class="goto"><a href="#" data-goto="' + this.involved_id[0]+ '">View</a></li></ul>');
        });

    },
    execDiff: function (mainStr, cfrStr) {
        _str = cfrStr.replace( config.lfPlaceholderRegex, "\n" )
            .replace( config.crPlaceholderRegex, "\r" )
            .replace( config.crlfPlaceholderRegex, "\r\n" )
            .replace( config.tabPlaceholderRegex, "\t" )
            //.replace( config.tabPlaceholderRegex, String.fromCharCode( parseInt( 0x21e5, 10 ) ) )
            .replace( config.nbspPlaceholderRegex, String.fromCharCode( parseInt( 0xA0, 10 ) ) );
//        _str  = htmlDecode(_str );
        _edit = mainStr.replace( String.fromCharCode( parseInt( 0x21e5, 10 ) ), "\t" );
//        _edit = UI.currentSegment.find('.editarea').text().replace( String.fromCharCode( parseInt( 0x21e5, 10 ) ), "\t" );

        //Prepend Unicode Character 'ZERO WIDTH SPACE' invisible, not printable, no spaced character,
        //used to detect initial and final spaces in html diff
        _str  = String.fromCharCode( parseInt( 0x200B, 10 ) ) + _str + String.fromCharCode( parseInt( 0x200B, 10 ) );
        _edit = String.fromCharCode( parseInt( 0x200B, 10 ) ) + _edit + String.fromCharCode( parseInt( 0x200B, 10 ) );

        diff_obj = UI.dmp.diff_main( _edit, _str );
        UI.dmp.diff_cleanupEfficiency( diff_obj );
        return diff_obj;
    },

    chooseAlternative: function(w) {console.log('chooseAlternative');
//        console.log( $('.sugg-target .realData', w ) );
        this.copyAlternativeInEditarea( UI.decodePlaceholdersToText( $('.sugg-target .realData', w ).text(), true, UI.currentSegmentId, 'choose alternative' ) );
        this.lockTags(this.editarea);
        this.editarea.focus();
        this.highlightEditarea();
    },
	copyAlternativeInEditarea: function(translation) {
		console.log('translation: ', translation);
		if ($.trim(translation) !== '') {
			if (this.body.hasClass('searchActive'))
				this.addWarningToSearchDisplay();
			this.saveInUndoStack('copyalternative');
			$(UI.editarea).html(translation).addClass('fromAlternative');
			this.saveInUndoStack('copyalternative');
		}
	},
	setDownloadStatus: function(stats) {
        var t = translationStatus( stats );

        $('.downloadtr-button')
            .removeClass("draft translated approved")
            .addClass(t);

        var downloadable = (t == 'translated' || t == 'approved') ;

        if ( downloadable ) {
            var label = 'DOWNLOAD TRANSLATION';
        } else {
            var label = 'PREVIEW';
        }

        $('.downloadtr-button').removeClass("draft translated approved").addClass(t);

        // var isDownload = (t == 'translated' || t == 'approved') ? 'true' : 'false';
		$('#downloadProject').attr('value', label);
        $('#previewDropdown').attr('data-download', downloadable);
	},
	setProgress: function(stats) {
		var s = stats;
		m = $('footer .meter');
        if( !s.ANALYSIS_COMPLETE ){
            $('#statistics' ).hide();
            $('#analyzing' ).show();
        } else {
            $('#statistics' ).show();
            $('#analyzing' ).hide();
        }
//		var status = 'approved';
//		var total = s.TOTAL;
		var t_perc = s.TRANSLATED_PERC;
		var a_perc = s.APPROVED_PERC;
		var d_perc = s.DRAFT_PERC;
		var r_perc = s.REJECTED_PERC;

		var t_perc_formatted = s.TRANSLATED_PERC_FORMATTED;
		var a_perc_formatted = s.APPROVED_PERC_FORMATTED;
		var d_perc_formatted = s.DRAFT_PERC_FORMATTED;
		var r_perc_formatted = s.REJECTED_PERC_FORMATTED;

//		var d_formatted = s.DRAFT_FORMATTED;
//		var r_formatted = s.REJECTED_FORMATTED;
		var t_formatted = s.TODO_FORMATTED;

		var wph = s.WORDS_PER_HOUR;
		var completion = s.ESTIMATED_COMPLETION;
//        console.log('WPH: ', wph);
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

    },
	chunkedSegmentsLoaded: function() {
		return $('section.readonly').length;
	},
	showEditToolbar: function() {
		$('.editor .editToolbar').addClass('visible');
	},
	hideEditToolbar: function() {
		$('.editor .editToolbar').removeClass('visible');
	},

	formatSelection: function(op) {
		selection = window.getSelection();
		range = selection.getRangeAt(0);

		prova = $(range.commonAncestorContainer).text().charAt(range.startOffset - 1);
        str = getSelectionHtml();
        insertHtmlAfterSelection('<span class="formatSelection-placeholder"></span>');
		aa = prova.match(/\W$/gi);
        newStr = '';
        var aa = $("<div/>").html(str);
        aa.find('.undoCursorPlaceholder').remove();
        var rightString = aa.html();

        $.each($.parseHTML(rightString), function(index) {
			if(this.nodeName == '#text') {
				d = this.data;
//				console.log(index + ' - ' + d);
//				console.log(!index);
//				console.log(!aa);
				jump = ((!index)&&(!aa));
//				console.log(d.charAt(0));
				capStr = toTitleCase(d);
				if(jump) {
					capStr = d.charAt(0) + toTitleCase(d).slice(1);
				}
/*
				if(op == 'uppercase') {
					toAdd = d.toUpperCase();
				} else if(op == 'lowercase') {
					toAdd = d.toLowerCase();
				} else if(op == 'capitalize') {
					console.log(index + ' - ' + d);
					if(index == 0) {
						if(!aa) {
							toAdd = d;
						} else {
							toAdd = toTitleCase(d);
						}
					} else {
						toAdd = toTitleCase(d);
					}
				}
*/
				toAdd = (op == 'uppercase')? d.toUpperCase() : (op == 'lowercase')? d.toLowerCase() : (op == 'capitalize')? capStr : d;
				newStr += toAdd;
			} else {
				newStr += this.outerHTML;
//				newStr += this.innerText;
			}
		});
        console.log('x');
//        console.log('newStr: ', newStr);
		replaceSelectedText(newStr);
        console.log('newStr: ', newStr);
//		replaceSelectedHtml(newStr);
        console.log('a: ', UI.editarea.html());
		UI.lockTags();
        console.log('b: ', UI.editarea.html());
		this.saveInUndoStack('formatSelection');
		saveSelection();
		$('.editor .editarea .formatSelection-placeholder').after($('.editor .editarea .rangySelectionBoundary'));
		$('.editor .editarea .formatSelection-placeholder').remove();
        $('.editor .editarea').trigger('afterFormatSelection');
	},

	setStatus: function(segment, status) {
//        console.log('setStatus - segment: ', segment);
//        console.log('setStatus - status: ', status);
		segment.removeClass("status-draft status-translated status-approved status-rejected status-new").addClass("status-" + status);
	},
	setStatusButtons: function(button) {
		isTranslatedButton = ($(button).hasClass('translated')) ? true : false;
		this.editStop = new Date();
		var segment = this.currentSegment;
		tte = $('.timetoedit', segment);
		this.editTime = this.editStop - this.editStart;
		this.totalTime = this.editTime + tte.data('raw-time-to-edit');
		var editedTime = this.millisecondsToTime(this.totalTime);
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
		this.buttonClickStop = new Date();
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
		location.href = $('#point2seg').attr('href');
	},

    continueDownload: function() {

        //check if we are in download status
        if ( !$('#downloadProject').hasClass('disabled') ) {

            //disable download button
            $('#downloadProject').addClass('disabled' ).data( 'oldValue', $('#downloadProject' ).val() ).val('DOWNLOADING');

            //create an iFrame element
            var iFrameDownload = $( document.createElement( 'iframe' ) ).hide().prop({
                id:'iframeDownload',
                src: ''
            });
console.log('iFrameDownload: ', iFrameDownload);
            //append iFrame to the DOM
            $("body").append( iFrameDownload );

            //generate a token download
            var downloadToken = new Date().getTime() + "_" + parseInt( Math.random( 0, 1 ) * 10000000 );

            //set event listner, on ready, attach an interval that check for finished download
            iFrameDownload.ready(function () {

                //create a GLOBAL setInterval so in anonymous function it can be disabled
                downloadTimer = window.setInterval(function () {

                    //check for cookie
                    var token = $.cookie( downloadToken );
console.log('eccolo: ', typeof token);
                    //if the cookie is found, download is completed
                    //remove iframe an re-enable download button
                    if ( typeof token != 'undefined' ) {
                        /*
                         * the token is a json and must be read with "parseJSON"
                         * in case of failure:
                         *      error_message = Object {code: -110, message: "Download failed. Please contact the owner of this MateCat instance"}
                         *
                         * in case of success:
                         *      error_message = Object {code: 0, message: "Download Complete."}
                         *
                         */
                        tokenData = $.parseJSON(token);
                        if(parseInt(tokenData.code) < 0) {
                            UI.showMessage({msg: tokenData.message})
                        }
                        $('#downloadProject').removeClass('disabled').val( $('#downloadProject' ).data('oldValue') ).removeData('oldValue');
                        window.clearInterval( downloadTimer );
                        $.cookie( downloadToken, null, { path: '/', expires: -1 });
                        iFrameDownload.remove();
                    }

                }, 2000);

            });

            //clone the html form and append a token for download
            var iFrameForm = $("#fileDownload").clone().append(
                    $( document.createElement( 'input' ) ).prop({
                        type:'hidden',
                        name:'downloadToken',
                        value: downloadToken
                    })
            );

            //append from to newly created iFrame and submit form post
            iFrameDownload.contents().find('body').append( iFrameForm );
            iFrameDownload.contents().find("#fileDownload").submit();

        } else {
            //we are in download status
        }

    },
	/**
	 * fill segments with relative errors from polling
	 *
	 * @param {type} segment
	 * @param {type} warnings
	 * @returns {undefined}
	 */
	setNextWarnedSegment: function(sid) {
		sid = sid || UI.currentSegmentId;
		idList = UI.globalWarnings;
		idList.sort();
		found = false;
		$.each(idList, function() {
			if (this > sid) {
				$('#point2seg').attr('href', '#' + this);
				found = true;
				return false;
			}
		});
		if(!found) {
			$('#point2seg').attr('href', '#' + UI.firstWarnedSegment);
		}
	},
	fillWarnings: function(segment, warnings) {
		//console.log( 'fillWarnings' );
		//console.log( warnings);

		//add Warnings to current Segment
		var parentTag = segment.find('p.warnings').parent();
		var actualWarnings = segment.find('p.warnings');

		$.each(warnings, function(key, value) {

            var warningMessage = '<p class="warnings">' + value.debug;
			//console.log(warnings[key]);


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
	fillCurrentSegmentWarnings: function(warningDetails, global) {
		if(global) {
//			$.each(warningDetails, function(key, value) {
//				console.log()
//				if ('segment-' + value.id_segment === UI.currentSegment[0].id) {
//					UI.fillWarnings(UI.currentSegment, $.parseJSON(value.warnings));
//				}
//			});
		} else {
			UI.fillWarnings(UI.currentSegment, $.parseJSON(warningDetails.warnings));
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
            id_job: config.job_id,
            password: config.password,
            token: token
        };

        if (UI.logEnabled) dataMix.logs = this.extractLogs();

		APP.doRequest({
			data: dataMix,
			error: function() {
				UI.warningStopped = true;
				UI.failedConnection(0, 'getWarning');
			},
			success: function(data) {//console.log('check warnings success');
				UI.startWarning();
				var warningPosition = '';
				UI.globalWarnings = data.details.sort();
				UI.firstWarnedSegment = UI.globalWarnings[0];
				UI.translationMismatches = data.translation_mismatches;

				//check for errors
				if (UI.globalWarnings.length > 0) {
					//for now, put only last in the pointer to segment id
					warningPosition = '#' + data.details[ Object.keys(data.details).sort().shift() ].id_segment;

					if (openingSegment)
						UI.fillCurrentSegmentWarnings(data.details, true);

					//switch to css for warning
					$('#notifbox').attr('class', 'warningbox').attr("title", "Click to see the segments with potential issues").find('.numbererror').text(UI.globalWarnings.length);

				} else {
					//if everything is ok, switch css to ok
					$('#notifbox').attr('class', 'notific').attr("title", "Well done, no errors found!").find('.numbererror').text('');
					//reset the pointer to offending segment
					$('#point2seg').attr('href', '#');
				}

				// check for messages
				if ( data.messages ) {
					var msgArray = $.parseJSON(data.messages);
					if (msgArray.length > 0) {
						UI.displayMessage(msgArray);
					}
				}


				UI.setNextWarnedSegment();

				//                $('#point2seg').attr('href', warningPosition);
			}
		});
	},
	displayMessage: function(messages) {
		if($('body').hasClass('incomingMsg')) return false;
        $.each(messages, function() {
            if(typeof $.cookie('msg-' + this.token) == 'undefined' && ( new Date( this.expire ) > ( new Date() ) )  ) {
                UI.showMessage({
                    msg: this.msg,
                    token: this.token,
                    showOnce: true,
                    expire: this.expire
                });
                return false;
            }
        });
	},
	showMessage: function(options) {
		APP.showMessage(options);
	},
	checkVersion: function() {
		if(this.version != config.build_number) {
			UI.showMessage({
				msg: 'A new version of MateCat has been released. Please <a href="#" class="reloadPage">click here</a> or clic CTRL+F5 (or CMD+R on Mac) to update.',
				token: false,
				fixed: true
			});
		}
	},
	currentSegmentQA: function() {
		this.currentSegment.addClass('waiting_for_check_result');
		var dd = new Date();
		ts = dd.getTime();
		var token = this.currentSegmentId + '-' + ts.toString();
        var segment_status_regex = new RegExp("status-([a-z]*)");
        var segment_status = this.currentSegment.attr('class' ).match(segment_status_regex);
        if(segment_status.length > 0){
            segment_status = segment_status[1];
        }

		//var src_content = $('.source', this.currentSegment).attr('data-original');
		if( config.brPlaceholdEnabled ){
			src_content = this.postProcessEditarea(this.currentSegment, '.source');
			trg_content = this.postProcessEditarea(this.currentSegment);
		} else {
			src_content = this.getSegmentSource();
			trg_content = this.getSegmentTarget();
		}

		this.checkSegmentsArray[token] = trg_content;
        var glossarySourcesAr = [];
        $('section.editor .tab.glossary .results .sugg-target .translation').each(function () {
            glossarySourcesAr.push($(this).text());
        })
//        console.log('glossarySourcesAr: ', glossarySourcesAr);
//        console.log(JSON.stringify(glossarySourcesAr));

		APP.doRequest({
			data: {
				action: 'getWarning',
				id: this.currentSegmentId,
				token: token,
				password: config.password,
				src_content: src_content,
				trg_content: trg_content,
                segment_status: segment_status,
                glossaryList: glossarySourcesAr
//                glossaryList: JSON.stringify(glossarySourcesAr)
			},
			error: function() {
				UI.failedConnection(0, 'getWarning');
			},
			success: function(d) {
				if (UI.currentSegment.hasClass('waiting_for_check_result')) {
					// check conditions for results discard
					if (!d.total) {
						$('p.warnings', UI.currentSegment).empty();
						$('span.locked.mismatch', UI.currentSegment).removeClass('mismatch');
                        $('.editor .editarea .order-error').removeClass('order-error');
						return;
					}
/*
                    escapedSegment = UI.checkSegmentsArray[d.token].trim().replace( config.lfPlaceholderRegex, "\n" );
                    escapedSegment = escapedSegment.replace( config.crPlaceholderRegex, "\r" );
                    escapedSegment = escapedSegment.replace( config.crlfPlaceholderRegex, "\r\n" );
                    escapedSegment = escapedSegment.replace( config.tabPlaceholderRegex, "\t" );
                    escapedSegment = escapedSegment.replace( config.nbspPlaceholderRegex, $( document.createElement('span') ).html('&nbsp;').text() );


                    if (UI.editarea.text().trim() != escapedSegment ){
                        console.log('ecco qua');

//                        console.log( UI.editarea.text().trim() );
//                        console.log( UI.checkSegmentsArray[d.token].trim() );
//                        console.log( escapedSegment  );
                        return;
                    }
*/
					UI.fillCurrentSegmentWarnings(d.details, false); // update warnings
					UI.markTagMismatch(d.details);
					delete UI.checkSegmentsArray[d.token]; // delete the token from the tail
					UI.currentSegment.removeClass('waiting_for_check_result');
				}
			}
		}, 'local');
	},

    setTranslation: function(options) {
        id_segment = options.id_segment;
        status = options.status;
        caller = options.caller || false;
        callback = options.callback || false;
        byStatus = options.byStatus || false;
        propagate = options.propagate || false;

        // add to setTranslation tail
        alreadySet = this.alreadyInSetTranslationTail(id_segment);
//        console.log('prova: ', '"' + $('#segment-' + id_segment + ' .editarea').text().trim().length + '"');
        emptyTranslation = ($('#segment-' + id_segment + ' .editarea').text().trim().length)? false : true;
        toSave = ((!alreadySet)&&(!emptyTranslation));
//        console.log('alreadySet: ', alreadySet);
//        console.log('emptyTranslation: ', emptyTranslation);

        //REMOVED Check for to save
        //Send ALL to the queue
        item = {
            id_segment: id_segment,
            status: status,
            caller: caller,
            callback: callback,
            byStatus: byStatus,
            propagate: propagate
        };
        if( toSave ) {
            this.addToSetTranslationTail(item);
//            this.addToSetTranslationTail( id_segment, status, caller, callback = callback || {} );
        } else {
            this.updateToSetTranslationTail(item)
        }

//        console.log('this.alreadyInSetTranslationTail(id_segment): ', this.alreadyInSetTranslationTail(id_segment));
//        this.addToSetTranslationTail(id_segment, status, caller);
//        if(UI.setTranslationTail.length) console.log('UI.setTranslationTail 3: ', UI.setTranslationTail.length);
//        console.log('UI.offline: ', UI.offline);
//        console.log('config.offlineModeEnabled: ', config.offlineModeEnabled);
        if ( this.offline && config.offlineModeEnabled ) {

            if ( toSave ) {
                this.decrementOfflineCacheRemaining();
                this.failedConnection( [ id_segment, status, false ], 'setTranslation' );
            }

            this.changeStatusOffline( id_segment );
            this.checkConnection( 'Set Translation check Authorized' );

        } else {
//            console.log('this.executingSetTranslation: ', this.executingSetTranslation);
            if ( !this.executingSetTranslation ) this.execSetTranslationTail();
        }
    },
    alreadyInSetTranslationTail: function (sid) {
//        console.log('qqqq');
//        console.log('UI.setTranslationTail.length: ', UI.setTranslationTail.length);
        alreadySet = false;
        $.each(UI.setTranslationTail, function (index) {
            if(this.id_segment == sid) alreadySet = true;
        });
        return alreadySet;
    },

    changeStatusOffline: function (sid) {
        if($('#segment-' + sid + ' .editarea').text() != '') {
            $('#segment-' + sid).removeClass('status-draft status-approved status-new status-rejected').addClass('status-translated');
        }
    },
    addToSetTranslationTail: function (item) {
//        console.log('addToSetTranslationTail ' + id_segment);
        $('#segment-' + id_segment).addClass('setTranslationPending');
/*
        var item = {
            id_segment: options.id_segment,
            status: options.status,
            caller: options.caller,
            callback: options.callback,
            byStatus: options.false,
            propagate: options.false
        }
*/
        this.setTranslationTail.push(item);
    },
    updateToSetTranslationTail: function (item) {
//        console.log('addToSetTranslationTail ' + id_segment);
        $('#segment-' + id_segment).addClass('setTranslationPending');
/*
        var item = {
            id_segment: id_segment,
            status: status,
            caller: caller,
            callback: callback
        }
*/
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
//        console.log('execSetTranslationTail');
        if(UI.setTranslationTail.length) {
            item = UI.setTranslationTail[0];
            UI.setTranslationTail.shift(); // to move on ajax callback
            UI.execSetTranslation(item);
//            UI.execSetTranslation( item.id_segment, item.status, item.caller, item.callback );
        }
    },

    execSetTranslation: function(options) {
        id_segment = options.id_segment;
        status = options.status;
        caller = options.caller;
        callback = options.callback;
        byStatus = options.byStatus;
        propagate = options.propagate;

        this.executingSetTranslation = true;
        reqArguments = arguments;
		segment = $('#segment-' + id_segment);
		this.lastTranslatedSegmentId = id_segment;
		caller = (typeof caller == 'undefined') ? false : caller;
		var file = $(segment).parents('article');

		// Attention, to be modified when we will lock tags
		if( config.brPlaceholdEnabled ) {
			translation = this.postProcessEditarea(segment);
		} else {
            translation = $('.editarea', segment ).text();
		}

		if (translation === '') {
            this.unsavedSegmentsToRecover.push(this.currentSegmentId);
            return false;
        }
		var time_to_edit = UI.editTime;
		var id_translator = config.id_translator;
		var errors = '';
		errors = this.collectSegmentErrors(segment);
		var chosen_suggestion = $('.editarea', segment).data('lastChosenSuggestion');
//		if(caller != 'replace') {
//			if(this.body.hasClass('searchActive')) {
//				console.log('aaa');
//				console.log(segment);
//				this.applySearch(segment);
//				oldNum = parseInt($(segment).attr('data-searchitems'));
//				newNum = parseInt($('mark.searchMarker', segment).length);
//				numRes = $('.search-display .numbers .results');
//				numRes.text(parseInt(numRes.text()) - oldNum + newNum);
//			}
//		}
		autosave = (caller == 'autosave') ? true : false;
        isSplitted = (id_segment.split('-').length > 1) ? true : false;
        if(isSplitted) translation = this.collectSplittedTranslations(id_segment);
//        console.log('isSplitted: ', isSplitted);
//        sidToSend = (isSplitted)? id_segment.split('-')[0] : id_segment;
        this.tempReqArguments = {
//            id_segment: sidToSend,
            id_segment: id_segment,
//            id_segment: id_segment.split('-')[0],
            id_job: config.id_job,
            id_first_file: file.attr('id').split('-')[1],
            password: config.password,
            status: status,
            translation: translation,
            time_to_edit: time_to_edit,
            id_translator: id_translator,
            errors: errors,
            chosen_suggestion_index: chosen_suggestion,
            autosave: autosave,
            version: segment.attr('data-version'),
            propagate: propagate
        };
        if(isSplitted) {
            this.tempReqArguments.splitStatuses = this.collectSplittedStatuses(id_segment).toString();
            this.setStatus($('#segment-' + id_segment), 'translated');
        }
        if(!propagate) {
            this.tempReqArguments.propagate = false;
        }
        reqData = this.tempReqArguments;
        reqData.action = 'setTranslation';
        this.log('setTranslation', reqData);
        segment = $('#segment-' + id_segment);

        APP.doRequest({
            data: reqData,
			context: [reqArguments, options],
			error: function() {
                UI.addToSetTranslationTail(this[1]);
                UI.changeStatusOffline(this[0][0]);
                UI.failedConnection(this[0], 'setTranslation');
                UI.decrementOfflineCacheRemaining();
            },
			success: function( d ) {
                UI.executingSetTranslation = false;
                UI.execSetTranslationTail();
				UI.setTranslation_success(d, this[1]);
                $(document).trigger('setTranslation:success', d);
			}
		});

        if( typeof( callback ) === "function" ) {
            callback.call();
        }

	},
    collectSplittedStatuses: function (sid) {
        statuses = [];
        segmentsIds = $('#segment-' + sid).attr('data-split-group').split(',');
        $.each(segmentsIds, function (index) {
            segment = $('#segment-' + this);
            status = (this == sid)? 'translated' : UI.getStatus(segment);
            statuses.push(status);
        });
        return statuses;
    },
    collectSplittedTranslations: function (sid) {
        totalTranslation = '';
        segmentsIds = $('#segment-' + sid).attr('data-split-group').split(',');
        $.each(segmentsIds, function (index) {
            segment = $('#segment-' + this);
            translation = UI.postProcessEditarea(segment);
            totalTranslation += translation;
//            totalTranslation += $(segment).find('.editarea').html();
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
    checkAddTMEnable: function() {
        console.log('checkAddTMEnable');
        if(
            ($('#addtm-tr-key').val().length > 19)&&
                UI.checkTMgrants($('.addtm-tr'))
            ) {
            $('#addtm-add').removeAttr('disabled').removeClass('disabled');
        } else {
            $('#addtm-add').attr('disabled', 'disabled').addClass('disabled');
        }
    },
    checkManageTMEnable: function() {
        console.log($('#addtm-tr-key').val().length);
        if($('#addtm-tr-key').val().length > 19) {
            $('.manageTM').removeClass('disabled');
            $('#addtm-tr-read, #addtm-tr-write, #addtm-select-file').removeAttr('disabled');
        } else {
            $('.manageTM').addClass('disabled');
            $('#addtm-tr-read, #addtm-tr-write, #addtm-select-file').attr('disabled', 'disabled');
        }
    },

    clearAddTMpopup: function() {
        $('#addtm-tr-key').val('');
        $('.addtm-select-file').val('');
        $('#addtm-tr-read, #addtm-tr-write').prop( "checked", true );
        $('#uploadTMX').text('').hide();
        $('.addtm-tr .error-message, .addtm-tr .warning-message').hide();
        $('.manageTM').addClass('disabled');
        $('#addtm-tr-read, #addtm-tr-write, #addtm-select-file').attr('disabled', 'disabled');
    },

    /**
     * This function is used when a string has to be sent to the server
     * It works over a clone of the editarea ( translation area ) and manage the text()
     * @param segment
     * @returns {XML|string}
     */
//    getTranslationWithBrPlaceHolders: function(segment) {
//        return UI.getTextContentWithBrPlaceHolders( segment );
//    },
    /**
     * This function is used when a string has to be sent to the server
     * It works over a clone of the editarea ( source area ) and manage the text()
     * @param segment
     * @returns {XML|string}
     */
//    getSourceWithBrPlaceHolders: function(segment) {
//        return UI.getTextContentWithBrPlaceHolders( segment, '.source' );
//    },

    /**
     * Called when a translation is sent to the server
     *
     * This method get the translation edit area TEXT and place the right placeholders
     * after br tags
     *
     * @param context
     * @param selector
     * @returns {XML|string}
     */
/*
	fixBR: function(txt) {
		var ph = '<br class="' + config.crPlaceholderClass + '">';
		var re = new RegExp(ph + '$', "gi");
		return txt.replace(/<div><br><\/div>/g, ph).replace(/<div>/g, '<br class="' + config.crPlaceholderClass + '">').replace(/<\/div>/g, '').replace(/<br>/g, ph).replace(re, '');
//		return txt.replace(/<br>/g, '').replace(/<div>/g, '<br class="' + config.crPlaceholderClass + '">').replace(/<\/div>/g, '').replace(re, '');
	},
*/

    log: function(operation, d) {
        if(!UI.logEnabled) return false;
        data = d;
        var dd = new Date();
//        console.log('stored log-' + operation + '-' + dd.getTime());
//        console.log('data: ', JSON.stringify(d));
//        console.log(stackTrace());
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

    postProcessEditarea: function(context, selector){//console.log('postprocesseditarea');
        selector = (typeof selector === "undefined") ? '.editarea' : selector;
        area = $( selector, context ).clone();
        /*
         console.log($(area).html());
         var txt = this.fixBR($(area).html());
         console.log(txt);
         return txt;
         */
        var divs = $( area ).find( 'div' );
        if( divs.length ){
            divs.each(function(){
                $(this).find( 'br:not([class])' ).remove();
                $(this).prepend( $('<span class="placeholder">' + config.crPlaceholder + '</span>' ) ).replaceWith( $(this).html() );
            });
        } else {
//			console.log('post process 1: ', $(area).html());
//			console.log($(area).find( 'br:not([class])' ).length);
            $(area).find( 'br:not([class])' ).replaceWith( $('<span class="placeholder">' + config.crPlaceholder + '</span>') );
            $(area).find('br.' + config.crlfPlaceholderClass).replaceWith( '<span class="placeholder">' + config.crlfPlaceholder + '</span>' );
            $(area).find('span.' + config.lfPlaceholderClass).replaceWith( '<span class="placeholder">' + config.lfPlaceholder + '</span>' );
            $(area).find('span.' + config.crPlaceholderClass).replaceWith( '<span class="placeholder">' + config.crPlaceholder + '</span>' );

//			$(area).find( 'br:not([class])' ).replaceWith( $('[BR]') );
//			console.log('post process 2: ', $(area).html());
        }

        $(area).find('span.' + config.tabPlaceholderClass).replaceWith(config.tabPlaceholder);
        $(area).find('span.' + config.nbspPlaceholderClass).replaceWith(config.nbspPlaceholder);
        $(area).find('span.space-marker').replaceWith(' ');
        $(area).find('span.rangySelectionBoundary, span.undoCursorPlaceholder').remove();

        return $(area).text();

    },

    /**
     * Called when a Segment string returned by server has to be visualized, it replace placeholders with br tags
     * @param str
     * @returns {XML|string}
     */
    decodePlaceholdersToText: function (str, jumpSpacesEncode) {
        if(!UI.hiddenTextEnabled) return str;
		jumpSpacesEncode = jumpSpacesEncode || false;
		var _str = str;
        if(UI.markSpacesEnabled) {
            if(jumpSpacesEncode) {
                _str = this.encodeSpacesAsPlaceholders(htmlDecode(_str), true);
            }
        }

		_str = _str.replace( config.lfPlaceholderRegex, '<span class="monad marker softReturn ' + config.lfPlaceholderClass +'" contenteditable="false"><br /></span>' )
					.replace( config.crPlaceholderRegex, '<span class="monad marker ' + config.crPlaceholderClass +'" contenteditable="false"><br /></span>' )
					.replace( config.crlfPlaceholderRegex, '<br class="' + config.crlfPlaceholderClass +'" />' )
					.replace( config.tabPlaceholderRegex, '<span class="tab-marker monad marker ' + config.tabPlaceholderClass +'" contenteditable="false">&#8677;</span>' )
					.replace( config.nbspPlaceholderRegex, '<span class="nbsp-marker monad marker ' + config.nbspPlaceholderClass +'" contenteditable="false">&nbsp;</span>' );

//		if(toLog) console.log('_str: ', _str);
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
//					newStr += htmlEncode(match[0]) + this.innerHTML.replace(/\s/gi, '#@-lt-@#span class="space-marker" contenteditable="false"#@-gt-@#.#@-lt-@#/span#@-gt-@#') + htmlEncode(match[1]);
				} else {

					newStr += htmlEncode(match[0]) + UI.encodeSpacesAsPlaceholders(this.innerHTML) + htmlEncode(match[1], false);

//					newStr += htmlEncode(match[0]) + UI.prova(this.innerHTML.replace(/\s/gi, '#@-lt-@#span#@-space-@#class="space-marker"#@-space-@#contenteditable="false"#@-gt-@#.#@-lt-@#/span#@-gt-@#')) + htmlEncode(match[1], false);

//					newStr += htmlEncode(match[0]) + UI.prova(this.innerHTML.replace(/\s/gi, '#@-lt-@#span class="space-marker" contenteditable="false"#@-gt-@#.#@-lt-@#/span#@-gt-@#')) + htmlEncode(match[1], false);
				}


				// se sono più di due, ci sono tag innestati
			}
		});
		if(root) {
			newStr = newStr.replace(/#@-lt-@#/gi, '<').replace(/#@-gt-@#/gi, '>').replace(/#@-space-@#/gi, ' ');
		}
		return newStr;
	},
/*
	prova: function(str, root) {
		var newStr = '';
		$.each($.parseHTML(str), function(index) {
			if(this.nodeName == '#text') {
				newStr += $(this).text().replace(/\s/gi, '<span class="space-marker" contenteditable="false">.</span>');
			} else {
				match = this.outerHTML.match(/<.*?>/gi);
				console.log('match: ', match);
				if(match.length == 1) { // se è 1 solo, è un tag inline

				} else if(match.length == 2) { // se sono due, non ci sono tag innestati
					newStr += htmlEncode(match[0]) + this.innerHTML.replace(/\s/gi, '#@-lt-@#span#@-space-@#class="space-marker"#@-space-@#contenteditable="false"#@-gt-@#.#@-lt-@#/span#@-gt-@#') + htmlEncode(match[1]);
//					newStr += htmlEncode(match[0]) + this.innerHTML.replace(/\s/gi, '#@-lt-@#span class="space-marker" contenteditable="false"#@-gt-@#.#@-lt-@#/span#@-gt-@#') + htmlEncode(match[1]);
				} else {
					console.log('vediamo: ', $.parseHTML(this.outerHTML));

					newStr += htmlEncode(match[0]) + UI.prova(this.innerHTML) + htmlEncode(match[1], false);

//					newStr += htmlEncode(match[0]) + UI.prova(this.innerHTML.replace(/\s/gi, '#@-lt-@#span#@-space-@#class="space-marker"#@-space-@#contenteditable="false"#@-gt-@#.#@-lt-@#/span#@-gt-@#')) + htmlEncode(match[1], false);

//					newStr += htmlEncode(match[0]) + UI.prova(this.innerHTML.replace(/\s/gi, '#@-lt-@#span class="space-marker" contenteditable="false"#@-gt-@#.#@-lt-@#/span#@-gt-@#')) + htmlEncode(match[1], false);
				}


				// se sono più di due, ci sono tag innestati
			}
		});
		if(root) {
			newStr = newStr.replace(/#@-lt-@#/gi, '<').replace(/#@-gt-@#/gi, '>').replace(/#@-space-@#/gi, ' ');
		}
		return newStr;
	},
*/

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

			if (operation == 'setContribution' && this.code != '-10' && UI.savingMemoryErrorNotificationEnabled) { // is not a password error
				APP.alert({msg: "Error in saving the segment to the translation memory.<br />Try refreshing the page and click on Translated again.<br />Contact <b>support@matecat.com</b> if this happens often."});
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
	setContextMenu: function() {
		var alt = (this.isMac) ? '&#x2325;' : 'Alt ';
		var cmd = (this.isMac) ? '&#8984;' : 'Ctrl ';
		$('#contextMenu .shortcut .alt').html(alt);
		$('#contextMenu .shortcut .cmd').html(cmd);
	},
	setTranslation_success: function(d, options) {
        id_segment = options.id_segment;
        status = options.status;
        caller = options.caller || false;
        callback = options.callback;
        byStatus = options.byStatus;
        propagate = options.propagate;
        segment = $('#segment-' + id_segment);

		if (d.errors.length)
			this.processErrors(d.errors, 'setTranslation');
        if(typeof d.pee_error_level != 'undefined') {
            $('#edit_log_link' ).removeClass( "edit_1 edit_2 edit_3" ). addClass( UI.pee_error_level_map[d.pee_error_level] );
            UI.body.addClass('peeError');
        }
		if (d.data == 'OK') {
			this.setStatus(segment, status);
			this.setDownloadStatus(d.stats);
			this.setProgress(d.stats);
            $( segment ).removeClass( 'setTranslationPending' );

			this.checkWarnings(false);
            $(segment).attr('data-version', d.version);
            if((!byStatus)&&(propagate)) {
                this.beforePropagateTranslation(segment, status);
            }
        }
        this.resetRecoverUnsavedSegmentsTimer();
    },
    recoverUnsavedSetTranslations: function() {
//        console.log('AAA recoverUnsavedSetTranslations');
//        console.log('segments to recover: ', UI.unsavedSegmentsToRecover);
        $.each(UI.unsavedSegmentsToRecover, function (index) {
            if($('#segment-' + this + ' .editarea').text() === '') {
//                console.log(this + ' è ancora vuoto');
                UI.resetRecoverUnsavedSegmentsTimer();
            } else {
//                console.log(this + ' non è più vuoto, si può mandare');
                UI.setTranslation({
                    id_segment: this.toString(),
                    status: 'translated'
                });
//                UI.setTranslation(this.toString(), 'translated');
                // elimina l'item dall'array
                UI.unsavedSegmentsToRecover.splice(index, 1);
//                console.log('eliminato ' + this.toString());
            }
            // se non è vuoto rifai il timeout, clearing l'altro
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
        UI.propagateTranslation(segment, status, false);
/*
        return false;

        if ( UI.propagationsAvailable ){

            if ( typeof $.cookie('_auto-propagation-' + config.job_id + '-' + config.password) != 'undefined' ) { // cookie already set
                if($.cookie('_auto-propagation-' + config.job_id + '-' + config.password) == '1') {
                    UI.propagateTranslation(segment, status, true);
                } else {
                    UI.propagateTranslation(segment, status, false);
                }

            } else {
//            var sid = segment.attr('id').split('-')[1];
                APP.popup({
                    name: 'confirmPropagation',
                    title: 'Warning',
                    buttons: [
                        {
                            type: 'ok',
                            text: 'Yes',
                            callback: 'doPropagate',
                            params: 'true',
                            closeOnClick: 'true'
                        },
                        {
                            type: 'cancel',
                            text: 'No, thanks',
                            callback: 'doPropagate',
                            params: 'false',
                            closeOnClick: 'true'
                        }
                    ],
                    content: "Do you want to extend the autopropagation of this translation even to " + UI.propagationsAvailable + " already translated segments?"
                });
            }

        }
*/
  /*
        if ($.cookie('_auto-propagation-' + config.job_id + '-' + config.password)) {
            console.log('cookie already set');

        } else {
            console.log('cookie not yet set');
            APP.popup({
                name: 'confirmPropagation',
                title: 'Warning',
                buttons: [
                    {
                        type: 'ok',
                        text: 'Yes',
                        callback: 'doPropagate',
                        params: 'true',
                        closeOnClick: 'true'
                    },
                    {
                        type: 'cancel',
                        text: 'No, thanks',
                        callback: 'doPropagate',
                        params: 'false',
                        closeOnClick: 'true'
                    }
                ],
                content: "Dou you want to extend the autopropagation of this translation even to already translated segments?"
            });
        }
        checkBefore = false;
        if(checkBefore) {

        } else {
            this.propagateTranslation(segment, status);
        }
        */
    },

    propagateTranslation: function(segment, status, evenTranslated) {
//        console.log($(segment).attr('data-hash'));
        this.tempReqArguments = null;
        console.log('status: ', status);
        console.log(status == 'translated');
        console.log(config.isReview && (status == 'approved'));
        if( (status == 'translated') || (config.isReview && (status == 'approved'))){
            plusApproved = (config.isReview)? ', section[data-hash=' + $(segment).attr('data-hash') + '].status-approved' : '';

            //NOTE: i've added filter .not( segment ) to exclude current segment from list to be set as draft
            $.each($('section[data-hash=' + $(segment).attr('data-hash') + '].status-new, section[data-hash=' + $(segment).attr('data-hash') + '].status-draft, section[data-hash=' + $(segment).attr('data-hash') + '].status-rejected' + ', section[data-hash=' + $(segment).attr('data-hash') + '].status-translated' + plusApproved ).not( segment ), function() {
                $('.editarea', this).html( $('.editarea', segment).html() );

                // if status is not set to draft, the segment content is not displayed
                UI.setStatus($(this), status); // now the status, too, is propagated
//                UI.setStatus($(this), 'draft');
                //set segment as autoPropagated
                $( this ).data( 'autopropagated', true );
            });

            //unset actual segment as autoPropagated because now it is translated
            $( segment ).data( 'autopropagated', false );

            //update current Header of Just Opened Segment
            //NOTE: because this method is called after OpenSegment
            // AS callback return for setTranslation ( whe are here now ),
            // currentSegment pointer was already advanced by openSegment and header already created
            //Needed because two consecutives segments can have the same hash
            this.createHeader(true);

        }
//        $('section[data-hash=' + $(segment).attr('data-hash') + ']');
    },
    doPropagate: function (trans) {
        reqData = this.tempReqArguments;
        reqData.action = 'setAutoPropagation';
        reqData.propagateAll = trans;

        this.tempReqArguments = null;

        APP.doRequest({
            data: reqData,
            context: [reqData, trans],
            error: function() {
            },
            success: function() {
                console.log('success setAutoPropagation');
                UI.propagateTranslation($('#segment-' + this[0].id_segment), this[0].status, this[1]);
            }
        });

    },
    switchFooter: function() {
        console.log('switchFooter');
        this.currentSegment.find('.footer').removeClass('showMatches');
        this.body.toggleClass('hideMatches');
        var cookieName = (config.isReview)? 'hideMatchesReview' : 'hideMatches';
        $.cookie(cookieName + '-' + config.job_id, this.body.hasClass('hideMatches'), { expires: 30 });
    },
    setHideMatches: function () {
        var cookieName = (config.isReview)? 'hideMatchesReview' : 'hideMatches';

        if(typeof $.cookie(cookieName + '-' + config.job_id) != 'undefined') {
            if($.cookie(cookieName + '-' + config.job_id) == 'true') {
                UI.body.addClass('hideMatches')
            } else {
                UI.body.removeClass('hideMatches')
            }
        } else {
            $.cookie(cookieName + '-' + config.job_id, this.body.hasClass('hideMatches'), { expires: 30 });
        }

    },
    setTagLockCustomizeCookie: function (first) {
        if(first && !config.tagLockCustomizable) return;
        var cookieName = 'tagLockDisabled';

        if(typeof $.cookie(cookieName + '-' + config.job_id) != 'undefined') {
            if(first) {
                if($.cookie(cookieName + '-' + config.job_id) == 'true') {
                    UI.body.addClass('tagmarkDisabled');
                    setTimeout(function() {
                        $('.editor .tagLockCustomize').addClass('unlock');
                    }, 100);
                } else {
                    UI.body.removeClass('tagmarkDisabled')
                }
            } else {
                cookieVal = (UI.body.hasClass('tagmarkDisabled'))? 'true' : 'false';
                $.cookie(cookieName + '-' + config.job_id, cookieVal,  { expires: 30 });
            }

        } else {
            $.cookie(cookieName + '-' + config.job_id, this.body.hasClass('tagmarkDisabled'), { expires: 30 });
        }

    },


    setWaypoints: function() {
		this.firstSegment.waypoint('remove');
		this.lastSegment.waypoint('remove');
		this.detectFirstLast();
		this.lastSegment.waypoint(function(event, direction) {
			if (direction === 'down') {
				UI.lastSegment.waypoint('remove');
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

		this.firstSegment.waypoint(function(event, direction) {
			if (direction === 'up') {
				UI.firstSegment.waypoint('remove');
				UI.getMoreSegments('before');
			}
		}, UI.upOpts);
	},
	showContextMenu: function(str, ypos, xpos) {
		if (($('#contextMenu').width() + xpos) > $(window).width())
			xpos = $(window).width() - $('#contextMenu').width() - 30;
		$('#contextMenu').css({
			"top": (ypos + 13) + "px",
			"left": xpos + "px"
		}).show();
	},

	/*
	 // for future implementation

	 getSegmentComments: function(segment) {
	 var id_segment = $(segment).attr('id').split('-')[1];
	 var id_translator = config.id_translator;
	 $.ajax({
	 url: config.basepath + '?action=getSegmentComment',
	 data: {
	 action: 'getSegmentComment',
	 id_segment: id_segment,
	 id_translator: id_translator
	 },
	 type: 'POST',
	 dataType: 'json',
	 context: segment,
	 success: function(d){
	 $('.numcomments',this).text(d.data.length);
	 $.each(d.data, function() {
	 $('.comment-area ul .newcomment',segment).before('<li><p><strong>'+this.author+'</strong><span class="date">'+this.date+'</span><br />'+this.text+'</p></li>');
	 });
	 }
	 });
	 },

	 addSegmentComment: function(segment) {
	 var id_segment = $(segment).attr('id').split('-')[1];
	 var id_translator = config.id_translator;
	 var text = $('.newcomment textarea',segment).val();
	 $.ajax({
	 url: config.basepath + '?action=addSegmentComment',
	 data: {
	 action: 'addSegmentComment',
	 id_segment: id_segment,
	 id_translator: id_translator,
	 text: text
	 },
	 type: 'POST',
	 dataType: 'json',
	 success: function(d){
	 }
	 });
	 },
	 */
    storeClientInfo: function () {
        clientInfo = {
            xRes: window.screen.availWidth,
            yRes: window.screen.availHeight
        };
        $.cookie('client_info', JSON.stringify(clientInfo), { expires: 3650 });
    },

    topReached: function() {
//        var jumpto = $(this.currentSegment).offset().top;
//        $("html,body").animate({
//            scrollTop: 0
//        }, 200).animate({
//            scrollTop: jumpto - 50
//        }, 200);
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

		this.editarea.html(this.undoStack[ind]);
        console.log('vediamo: ', document.getElementsByClassName("undoCursorPlaceholder")[0]);
		setCursorPosition(document.getElementsByClassName("undoCursorPlaceholder")[0]);
		$('.undoCursorPlaceholder').remove();

		if (!ind)
			this.lockTags();

		if (this.undoStackPosition < (this.undoStack.length - 1))
			this.undoStackPosition++;
		this.currentSegment.removeClass('waiting_for_check_result');
		this.registerQACheck();
	},
	redoInSegment: function() {
		this.editarea.html(this.undoStack[this.undoStack.length - 1 - this.undoStackPosition - 1 + 2]);
		if (this.undoStackPosition > 0)
			this.undoStackPosition--;
		this.currentSegment.removeClass('waiting_for_check_result');
		this.registerQACheck();
	},
	saveInUndoStack: function() {
//		noRestore = (typeof noRestore == 'undefined')? 0 : 1;
		currentItem = this.undoStack[this.undoStack.length - 1 - this.undoStackPosition];

		if (typeof currentItem != 'undefined') {
			if (currentItem.trim() == this.editarea.html().trim())
				return;
		} else {
//            return;
        }

        if(this.editarea === '') return;

		if (this.editarea.html() === '') return;

		var ss = this.editarea.html().match(/<span.*?contenteditable\="false".*?\>/gi);
		var tt = this.editarea.html().match(/&lt;/gi);
        if ( tt ) {
            if ( (tt.length) && (!ss) )
                return;
        }
        var diff = ( typeof currentItem == 'undefined') ? 'null' : this.dmp.diff_main( currentItem, this.editarea.html() )[1][1];
        if ( diff == ' selected' )
            return;

		var pos = this.undoStackPosition;
		if (pos > 0) {
			this.undoStack.splice(this.undoStack.length - pos, pos);
			this.undoStackPosition = 0;
		}
		saveSelection();
		$('.undoCursorPlaceholder').remove();
        $('.rangySelectionBoundary').after('<span class="undoCursorPlaceholder monad" contenteditable="false"></span>');
		restoreSelection();
		this.undoStack.push(this.editarea.html().replace(/(<.*?)\s?selected\s?(.*?\>)/gi, '$1$2'));
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
    findCommonPartInSegmentIds: function () {
        var a = config.first_job_segment;
        var b = config.last_job_segment;
        for(x=0;x<a.length;x++){
            if(a[x] != b[x]) {
                n = x;
                break;
            }
        }

        //when the job has one segment only
        if( typeof n === 'undefined' ) {
            n = a.length -1;
        }

//        console.log('n: ' + x);
//        console.log(a.substring(0,n));
//        var coso = a.substring(0,n);
        this.commonPartInSegmentIds = a.substring(0,n);
//        console.log(a.replace(coso, '<span class="implicit">' + coso + '</span>'))
    },
    shortenId: function(id) {
        return id.replace(UI.commonPartInSegmentIds, '<span class="implicit">' + UI.commonPartInSegmentIds + '</span>');
    },
    isCJK: function () {
        var l = config.target_rfc;
        if( (l=='zh-CN') || (l=='zh-TW') || (l=='ja-JP') || (l=='ko-KR') ) {
            return true;
        } else {
            return false;
        }
    },
    isKorean: function () {
        var l = config.target_rfc;
        if(l=='ko-KR') {
            return true;
        } else {
            return false;
        }
    },

    start: function () {
        APP.init();
        APP.fitText($('.breadcrumbs'), $('#pname'), 30);
        setBrowserHistoryBehavior();
        $("article").each(function() {
            APP.fitText($('.filename h2', $(this)), $('.filename h2', $(this)), 30);
        });
        UI.render({
            firstLoad: true
        });
        //launch segments check on opening
        UI.checkWarnings(true);
        $('html').trigger('start');
    },
    restart: function () {
        $('#outer').empty();
        this.start();
    },
};

$(document).ready(function() {
    UI.start();
});

$(window).resize(function() {
    UI.fixHeaderHeightChange();
    APP.fitText($('.breadcrumbs'), $('#pname'), 30);
});


(function($, UI) {
    $.extend(UI, {
        focusSegment: function(segment) {
            var clickableEditArea = segment.find('.editarea:not(.opened)');
            if ( clickableEditArea.length == 0 || ( Review.enabled() && !isTranslated( segment ) ) ) {
                UI.scrollSegment( segment );
            }
            else {
                clickableEditArea.trigger('click');
            }
            $(document).trigger('ui:segment:focus', UI.getSegmentId( segment ) );
        },

        getSegmentById: function(id) {
            return $('#segment-' + id);
        },

        getEditAreaBySegmentId: function(id) {
            return $('#segment-' + id + ' .editarea');
        },

        segmentIsLoaded: function(segmentId) {
            return UI.getSegmentById(segmentId).length > 0 ;
        }
    });
})(jQuery,UI);
