/*
	Component: ui.core
 */
UI = null;

UI = {

    /*showDownloadCornerTip : function() {
        if (UI.isChrome) {
            var newNotification = {
                text: "Your downloaded file will appear on the bar below in a few seconds.<br><img src='/public/img/arrowdown_blu.png' width='18px' style='margin: 0 auto; display: block;'> ",
                title: "Download",
                allowHtml: true
            };
            APP.addNotification(newNotification);
        }
    },*/

    setEditingSegment : function(segment) {
        if ( segment != null ) {
            UI.body.addClass('editing');
            // console.debug('editing addClass');
        } else {
            UI.body.removeClass('editing');
            // console.debug('editing removeClass');
        }

        UI._editingSegment = segment ;
        $(document).trigger('editingSegment:change', {segment: segment});
    },

    get editingSegment() {
        return UI._editingSegment ;
    },

    statusHandleTitleAttr : function( status ) {
        status = status.toUpperCase();
        return config.status_labels[ status ] + ', click to change it';
    },
    showPostRevisionStatuses : false,
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
            var searchBoxIsOpen = UI.body.hasClass('filterOpen');
            searchBoxHeight = (searchBoxIsOpen)? $('.searchbox').height() + 1 : 0;
            if (LXQ.enabled()) {
                var lexiqaBoxIsOpen = $('#lexiqa-popup').hasClass('lxq-visible');
                var lxqBoxHeight =  (lexiqaBoxIsOpen)? $('#lexiqa-popup').outerHeight() + 8 : 0;
                jobMenu.css('top', (messageBarHeight + lxqBoxHeight + searchBoxHeight + 43 - menuHeight) + "px");
            }
            else {
                jobMenu.css('top', (messageBarHeight + searchBoxHeight + 43 - menuHeight) + "px");
            }
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
	activateSegment: function(segment, isNotSimilar) {
		this.createFooter(segment.el, isNotSimilar);
		this.createButtons(segment);
		this.createHeader(segment.el);
	},
    evalCurrentSegmentTranslationAndSourceTags : function( segment ) {
        if ( segment.length == 0 ) return ;

        var sourceTags = $('.source', segment).html()
            .match(/(&lt;\s*\/*\s*(g|x|bx|ex|bpt|ept|ph|it|mrk)\s*.*?&gt;)/gi);
        this.sourceTags = sourceTags || [];
        this.currentSegmentTranslation = segment.find( UI.targetContainerSelector() ).text();
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

		this.lastOpenedEditarea = $( UI.targetContainerSelector(), this.currentSegment);

		this.currentSegmentId    = segment.id ;
        this.lastOpenedSegmentId = segment.id ;
		this.currentSegment      = segment.el ;
		this.currentFile         = segment.el.parent();
		this.currentFileId       = this.currentFile.attr('id').split('-')[1];

        this.evalCurrentSegmentTranslationAndSourceTags( segment.el );
    },

    /**
     *
     * @param el
     * @param status
     * @param byStatus
     * @param options
     */
	changeStatus: function(el, status, byStatus, options) {
        if ( typeof options == 'undefined') options = {};

        var segment = $(el).closest("section");
        var segment_id = this.getSegmentId(segment);

        var opts = {
            segment_id: segment_id,
            status: status,
            byStatus: byStatus,
            noPropagation: options.noPropagation || false
        };

        if ( byStatus || opts.noPropagation ) {
            opts.noPropagation = true;
            this.execChangeStatus(JSON.stringify(opts)); // no propagation
        } else {

            // ask if the user wants propagation or this is valid only
            // for this segment
            if (this.autopropagateConfirmNeeded()) {

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
        // TODO: this is relying on a comparison between strings to determine if the segment
        // was modified. There should be a more consistent way to read this state, see UI.setSegmentModified .
        if (this.currentSegmentTranslation.trim() == this.editarea.text().trim()) { //segment not modified
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

        $('.percentuage', segment.el).removeClass('visible');

        this.setTranslation({
            id_segment: segment.id,
            status: status,
            caller: false,
            byStatus: byStatus,
            propagate: !noPropagation
        });

        segment.el.removeClass('saved');

        $(document).trigger('segment:status:change', [segment, options]);
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

    },

    checkHeaviness: function() {
        if ($('section').length > config.maxNumSegments && !UI.offline) {
            UI.reloadToSegment(UI.currentSegmentId);
        }

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
		if ( typeof segment == 'undefined' ) {

			return true;

		} else {
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

            if ((segment.hasClass('modified')) && (saveBrevior) && (!config.isReview)) {
                this.saveSegment(segment);
            }
            this.deActivateSegment(byButton, segment);
            this.removeGlossaryMarksFormSource();

            segment.find('.editarea').attr('contenteditable', 'false');

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

        // Test
        //source_val = source_val.replace(/&quot;/g,'"');

        // Attention I use .text to obtain a entity conversion,
        // by I ignore the quote conversion done before adding to the data-original
        // I hope it still works.

        this.saveInUndoStack('copysource');
        $(".editarea", this.currentSegment).html(source_val).keyup().focus();

        this.saveInUndoStack('copysource');

        this.highlightEditarea();

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
//        this.consecutiveCopySourceNum++;
            if(this.consecutiveCopySourceNum.length > 2) {
                this.copyAllSources();
            }
        }

    },
    copyAllSources: function() {
        if(typeof $.cookie('source_copied_to_target-' + config.id_job + "-" + config.password) == 'undefined') {
            APP.confirmAndCheckbox({
                title: 'Copy source to target',
                name: 'confirmCopyAllSources',
                okTxt: 'Yes',
                cancelTxt: 'No',
                callback: 'continueCopyAllSources',
                onCancel: 'abortCopyAllSources',
                closeOnSuccess: true,
                msg: "Copy source to target for all new segments?<br/><b>This action cannot be undone.</b>",
                'checkbox-label': "Confirm copy source to target"
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
                id_job: config.id_job,
                pass: config.password
            },
            error: function() {
                console.log('error');
                APP.closePopup();
                var notification = {
                    title: 'Error',
                    text: 'Error copying all sources to target. Try again!',
                    type: 'error',
                    position: "bl"
                };
                APP.addNotification(notification);
                /*UI.showMessage({
                    msg: 'Error copying all sources to target. Try again!'
                });*/
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
                    /*UI.showMessage({
                        msg: d.errors[0].message
                    });*/
                } else {
                    $.cookie('source_copied_to_target-' + config.id_job + "-" + config.password, '1', { expires:1 });
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
        if ( typeof dont_show != 'undefined' && dont_show) {
            $.cookie('source_copied_to_target-' + config.job_id +"-" + config.password,
                    '0',
                    //expiration: 1 day
                    { expires: 30 });
        }
        else {
            $.cookie('source_copied_to_target-' + config.job_id +"-" + config.password,
                    null,
                    //set expiration date before the current date to delete the cookie
                    {expires: new Date(1)});
        }
    },
    setComingFrom: function () {
        var page = (config.isReview)? 'revise' : 'translate';
        $.cookie('comingFrom' , page, { path: '/' });
    },

    clearMarks: function (str) {
        str = str.replace(/(<mark class="inGlossary">)/gi, '').replace(/<\/mark>/gi, '');
        str = str.replace(/<span data-id="[^"]+" class="unusedGlossaryTerm">(.*?)<\/span>/gi, "$1");
        return str;
    },
	highlightEditarea: function(seg) {
		segment = seg || this.currentSegment;
		segment.addClass('highlighted1');
		setTimeout(function() {
			$('.highlighted1').addClass('modified highlighted2');
			segment.trigger('modified');
		}, 300);
		setTimeout(function() {
			$('.highlighted1, .highlighted2').removeClass('highlighted1 highlighted2');
		}, 2000);
	},


	copyToNextIfSame: function(nextUntranslatedSegment) {
		if ($('.source', this.currentSegment).data('original') == $('.source', nextUntranslatedSegment).data('original')) {
			if ($('.editarea', nextUntranslatedSegment).hasClass('fromSuggestion')) {
				$('.editarea', nextUntranslatedSegment).text(this.editarea.text());
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
        var sameButton = (nextSegment.hasClass('status-new')) || (nextSegment.hasClass('status-draft'));
        if (this.currentSegmentTPEnabled) {
            nextUntranslated = "";
            currentButton = '<li><a id="segment-' + this.currentSegmentId +
                '-button-guesstags" data-segmentid="segment-' + this.currentSegmentId +
                '" href="#" class="guesstags"' + disabled + ' >' + 'GUESS TAGS' + '</a><p>' +
                ((UI.isMac) ? 'CMD' : 'CTRL') + '+ENTER</p></li>';
        } else {
            nextUntranslated = (sameButton)? '' : '<li><a id="segment-' + this.currentSegmentId +
                '-nextuntranslated" href="#" class="btn next-untranslated" data-segmentid="segment-' +
                this.currentSegmentId + '" title="Translate and go to next untranslated">' + label_first_letter + '+&gt;&gt;</a><p>' +
                ((UI.isMac) ? 'CMD' : 'CTRL') + '+SHIFT+ENTER</p></li>';
            currentButton = '<li><a id="segment-' + this.currentSegmentId +
                '-button-translated" data-segmentid="segment-' + this.currentSegmentId +
                '" href="#" class="translated"' + disabled + ' >' + button_label + '</a><p>' +
                ((UI.isMac) ? 'CMD' : 'CTRL') + '+ENTER</p></li>';
        }

        UI.segmentButtons = nextUntranslated + currentButton;

		var buttonsOb = $('#segment-' + this.currentSegmentId + '-buttons');

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

        // If the Messages Tab is present open it by default
        if ($('.footer', segment).find('.open.segment-notes').length) {
            this.forceShowMatchesTab();
        }

        UI.currentSegment.trigger('afterFooterCreation', segment);

        // FIXME: arcane. Whatever it does, it should go in the contribution module.
		if ($(segment).hasClass('loaded') && (segment === this.currentSegment) && ($(segment).find('.matches .overflow').text() === '')) {
            var d = JSON.parse( UI.getFromStorage('contribution-' + config.id_job + '-' + sid ) );
			UI.processContributions( d, segment );
		}
	},

    createHeader: function(segment, forceCreation) {
        var segment$ = $(segment);
        var segmentId = UI.getSegmentId(segment);
        forceCreation = forceCreation || false;

        if ( $('h2.percentuage', segment$).length && !forceCreation ) {
            return;
        }
		var header = '<h2 title="" class="percentuage"><span></span></h2><a href="/referenceFile/' + config.id_job + '/' + config.password + '/' + segmentId + '" id="segment-' + segmentId + '-context" class="context" title="Open context" target="_blank">Context</a>';
        $('#' + segment$.attr('id') + '-header').html(header);

        if ( segment$.data( 'autopropagated' ) && !$( '.header .repetition', segment$ ).length ) {
            $( '.header', segment$ ).prepend( '<span class="repetition">Autopropagated</span>' );
        }

    },
	createJobMenu: function() {
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
					if(this == config.id_job) {
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
        // node.setAttribute('contenteditable', 'false');
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
    showRevisionStatuses : function() {
        return true;
    },
	createStatusMenu: function(statusMenu, section) {
        $("ul.statusmenu").empty().hide();

        var segment = new UI.Segment( section );

        var data = {
            id_segment : segment.id,
            show_revision_statuses : UI.showRevisionStatuses(),
            show_post_revision_statuses : UI.showPostRevisionStatuses
        };

        var menu = MateCat.Templates['segment_status_menu'](data);

		statusMenu.html(menu).show();
	},
	deActivateSegment: function(byButton, segment) {
		UI.removeButtons(byButton, segment);
		UI.removeHeader(byButton, segment);
		UI.removeFooter(byButton, segment);

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
			this.startSegmentId = (hash) ? hash : config.last_opened_segment;
		}
	},
    fixHeaderHeightChange: function() {
        var headerHeight = $('header .wrapper').height() + ((this.body.hasClass('filterOpen'))? $('header .searchbox').height() : 0) + ((this.body.hasClass('incomingMsg'))? $('header #messageBar').height() : 0);
        $('#outer').css('margin-top', headerHeight + 'px');
    },

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

    /**
     * getNextSegment
     *
     * Returns the next segment.
     *
     */
	getNextSegment: function(segment, status) {
        UI.evalNextSegment( segment, status) ;
        return this.nextSegmentId ;
	},

    /**
     * selectorForNextUntranslatedSegment
     *
     * Defines the css selectors to be used to determine the next
     * segment to open.
     */
    selectorForNextUntranslatedSegment : function(status, section) {
        var selector = (status == 'untranslated') ? 'section.status-draft:not(.readonly), section.status-rejected:not(.readonly), section.status-new:not(.readonly)' : 'section.status-' + status + ':not(.readonly)';
        return selector ;
    },

    /**
     * selectorForNextSegment
     */
    selectorForNextSegment : function() {
        return 'section';
    },

    /**
     * evalNextSegment
     *
     * Evaluates the next segment and populates this.nextSegmentId ;
     *
     */
    evalNextSegment: function( section, status ) {
        var selector = UI.selectorForNextUntranslatedSegment( status, section );
		var n = $(section).nextAll(selector).first();

		if (!n.length) {
			n = $(section).parents('article').next().find(selector).first();
		}

		if (n.length) { // se ci sono sotto segmenti caricati con lo status indicato
			this.nextUntranslatedSegmentId = this.getSegmentId($(n));
		} else {
			this.nextUntranslatedSegmentId = UI.nextUntranslatedSegmentIdByServer;
		}
        var i = $(section).next();

        if (!i.length) {
			i = $(section).parents('article').next().find( UI.selectorForNextSegment() ).first();
		}
		if (i.length) {
			this.nextSegmentId = this.getSegmentId($(i));
		} else {
			this.nextSegmentId = 0;
		}
    },
	getPercentuageClass: function(match) {
		var percentageClass = "";
		var m_parse = parseInt(match);

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

                if ($.cookie('tmpanel-open') == '1') UI.openLanguageResourcesPanel();
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

        SegmentNotes.enabled() && SegmentNotes.registerSegments ( d.data );

		$.each(d.data.files, function() {
			startSegmentId = this.segments[0].sid;
            //Tag Projection: check if is enable the Tag Projection
            UI.setGlobalTagProjection(this);
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
				this.scrollSegment($('#segment-' + options.segmentToScroll), options.highlight );
			}
			if (options.segmentToOpen) {
				$('#segment-' + options.segmentToOpen + ' ' + UI.targetContainerSelector()).click();
			}

			if ( UI.editarea.length && ($('#segment-' + UI.currentSegmentId).length) && (!$('section.editor').length)) {
				UI.openSegment(UI.editarea);
			}
			if (options.caller == 'link2file') {
				if (UI.segmentIsLoaded(UI.currentSegmentId)) {
					UI.openSegment(UI.editarea);
				}
			}

			if ($('#segment-' + UI.startSegmentId).hasClass('readonly')) {
                this.scrollSegment($('#segment-' + UI.startSegmentId), options.highlight );
			}

			if (options.applySearch) {
				$('mark.currSearchItem').removeClass('currSearchItem');
				this.markSearchResults();
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
		this.markTags();
		this.checkPendingOperations();
        this.retrieveStatistics();
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
		var editarea = (typeof seg == 'undefined') ? this.editarea : $('.editarea', seg);
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
			$(UI.targetContainerSelector() + ', .area', seg).text(this.translation);
			status = (this.status == 'DRAFT') ? 'draft' : (this.status == 'TRANSLATED') ? 'translated' : (this.status == 'APPROVED') ? 'approved' : (this.status == 'REJECTED') ? 'rejected' : '';
			UI.setStatus(seg, status);
		});
	},
	test: function(params) {
        // TODO: remove thi function once we know who's calling it.
        console.warn('This function does nothing and should be removed.');
	},
	gotoNextSegment: function() {
        var selector = UI.selectorForNextSegment() ;
		var next = $('.editor').nextAll( selector  ).first();

		if (next.is('section')) {
			UI.scrollSegment(next);
			$(UI.targetContainerSelector(), next).trigger("click", "moving");
		} else {
			next = UI.currentFile.next().find( selector ).first();
			if (next.length) {
                $(UI.targetContainerSelector(), next).trigger("click", "moving");
                UI.scrollSegment(next);
			} else {
                UI.closeSegment(UI.currentSegment, 1, 'save');
            }
		}
	},
	gotoNextUntranslatedSegment: function() {
        console.log('gotoNextUntranslatedSegment');
		if (!UI.segmentIsLoaded(UI.nextUntranslatedSegmentId)) {
			if (!UI.nextUntranslatedSegmentId) {
				UI.closeSegment(UI.currentSegment);
			} else {
				UI.reloadWarning();
			}
		} else {
			$("#segment-" + UI.nextUntranslatedSegmentId +
                " " + UI.targetContainerSelector() ).trigger("click");
		}
	},

	gotoOpenSegment: function(quick) {
        quick = quick || false;

        if ($('#segment-' + this.currentSegmentId).length) {
			UI.scrollSegment(this.currentSegment, false, quick);
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
        var selector = UI.selectorForNextSegment() ;
		var prev = $('.editor').prevAll( selector ).first();
		if (prev.is('section')) {
			$(UI.targetContainerSelector(), prev).click();
		} else {
			prev = $('.editor').parents('article').prevAll( selector ).first();
			if (prev.length) {
				$(UI.targetContainerSelector() , prev).click();
			}
        }
		if (prev.length)
			UI.scrollSegment(prev);
	},
	gotoSegment: function(id) {

	    if ( !this.segmentIsLoaded(id) && UI.parsedHash.splittedSegmentId ) {
            id = UI.parsedHash.splittedSegmentId ;
        }

        if ( MBC.enabled() && MBC.wasAskedByCommentHash( id ) ) {
            MBC.openSegmentComment( UI.Segment.findEl( id ) ) ;
        } else if ( _.isUndefined(UI.currentSegment) || UI.getSegmentId(UI.currentSegment) !== id ) {
            SegmentActivator.activate(id)
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
		$('p.warnings', segment).empty();
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
        // If we are going to re-render the articles first we remove them
        if (where === "center" && !starting) {
            $('article').remove();
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
            if (LXQ.enabled())
            $.each(this.segments,function(i,seg) {
            if (!starting)
            if (LXQ.hasOwnProperty('lexiqaData') && LXQ.lexiqaData.hasOwnProperty('lexiqaWarnings') &&
                LXQ.lexiqaData.lexiqaWarnings.hasOwnProperty(seg.sid)) {
                    console.log('in loadmore segments, segment: '+seg.sid+' already has qa info...');
                    //FOTDDD
                    LXQ.redoHighlighting(seg.sid,true);
                    LXQ.redoHighlighting(seg.sid,false);
                }
            });
		});

        $(document).trigger('files:appended');

		if (starting) {
			this.init();
            LXQ.getLexiqaWarnings();
		}

	},
    stripSpans: function (str) {
        return str.replace(/<span(.*?)>/gi, '').replace(/<\/span>/gi, '');
    },
    normalizeSplittedSegments: function (segments) {
        newSegments = [];
        $.each(segments, function (index) {
            splittedSourceAr = this.segment.split(UI.splittedTranslationPlaceholder);
            if(splittedSourceAr.length > 1) {
                segment = this;
                splitGroup = [];
                $.each(splittedSourceAr, function (i) {
                    splitGroup.push(segment.sid + '-' + (i + 1));
                });

                $.each(splittedSourceAr, function (i) {
                    translation = segment.translation.split(UI.splittedTranslationPlaceholder)[i];
                    status = segment.target_chunk_lengths.statuses[i];
                    segData = {
                        autopropagated_from: "0",
                        has_reference: "false",
                        parsed_time_to_edit: ["00", "00", "00", "00"],
                        readonly: "false",
                        segment: splittedSourceAr[i],
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
                newSegments.push(this);
            }

        });
        return newSegments;
    },

    isReadonlySegment : function( segment ) {
       return ( (segment.readonly == 'true') ||(UI.body.hasClass('archived'))) ? true : false;
    },

    renderSegments: function (segments, justCreated, splitAr, splitGroup) {
        segments = this.normalizeSplittedSegments(segments);
        splitAr = splitAr || [];
        splitGroup = splitGroup || [];
        var t = config.time_to_edit_enabled;
        newSegments = '';
        $.each(segments, function(index) {

            var readonly = UI.isReadonlySegment( this );

            var autoPropagated = this.autopropagated_from != 0;
            var autoPropagable = (this.repetitions_in_chunk == "1")? false : true;

            try {
                if (!$.parseHTML(this.segment).length) {
                } else {
                    this.segment = UI.stripSpans(this.segment);
                }
            } catch ( e ){
                //if we split a segment in more than 3 parts and reload the cattool
                //this exception is raised:
                // Uncaught TypeError: Cannot read property 'length' of null
                //so SKIP in a catched exception
            }

            var escapedSegment = htmlEncode(this.segment.replace(/\"/g, "&quot;"));

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

    getStatusForAutoSave : function( segment ) {
        var status ;
        if (segment.hasClass('status-translated')) {
            status = 'translated';
        }
        else if (segment.hasClass('status-approved')) {
            status = 'approved' ;
        }
        else if (segment.hasClass('status-rejected')) {
            status = 'rejected';
        }
        else if (segment.hasClass('status-new')) {
            status = 'new';
        }
        else {
            status = 'draft';
        }

		if (status == 'new') {
			status = 'draft';
		}
        console.debug('status', status);
        return status;
    },

    saveSegment: function(segment) {
		this.setTranslation({
            id_segment: this.getSegmentId(segment),
            status: this.getStatusForAutoSave( segment ) ,
            caller: 'autosave'
        });
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
		var editarea = (typeof ed == 'undefined') ? UI.editarea : $(ed);
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
				window.location.hash = UI.currentSegmentId;
			}, 300);
		}

		if (this.readonly) return;

		APP.doRequest({
			data: {
				action: 'setCurrentSegment',
				password: config.password,
				id_segment: id_segment.toString(),
				id_job: config.id_job
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
        $('html').trigger('setCurrentSegment_success', [d, id_segment]);
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
            if( this.translation == htmlEncode(UI.postProcessEditarea( UI.currentSegment ).replace( /[ \xA0]+$/ , '' )) ) sameContentIndex1 = ind;
        });
        if(sameContentIndex1 != -1) d.data.not_editable.splice(sameContentIndex1, 1);

        var numAlt = d.data.editable.length + d.data.not_editable.length;
        var numSeg = 0;
        $.each(d.data.editable, function() {
            numSeg += this.involved_id.length;
        });
        if(numAlt) {
            tab = UI.currentSegment.find('.tab-switcher-al');
            tab.find('.number').text('(' + numAlt + ')');
            UI.renderAlternatives(d);
            tab.show();
            // tab.click();
            // this.currentSegment.find('.footer').removeClass('showMatches');
            $('.editor .submenu .active').removeClass('active');
            tab.addClass('active');
            $('.editor .sub-editor').removeClass('open');
            $('.editor .sub-editor.alternatives').addClass('open');
            this.body.removeClass('hideMatches');

        }
    },
    renderAlternatives: function(d) {
        var segment = UI.currentSegment;
        var segment_id = UI.currentSegmentId;
        var escapedSegment = UI.decodePlaceholdersToText(UI.currentSegment.find('.source').html(), false, segment_id, 'render alternatives');
        // Take the .editarea content with special characters (Ex: ##$_0A$##) and transform the placeholders
        var mainStr = UI.clenaupTextFromPleaceholders(UI.postProcessEditarea(UI.currentSegment));
        $.each(d.data.editable, function(index) {
            // Decode the string from the server
            var transDecoded = htmlDecode(this.translation);
            // Make the diff between the text with the same codification
            var diff_obj = UI.execDiff(mainStr, transDecoded);
            $('.sub-editor.alternatives .overflow', segment).append('<ul class="graysmall" data-item="' + (index + 1) + '">' +
                '<li class="sugg-source"><span id="' + segment_id + '-tm-' + this.id + '-source" class="suggestion_source">' +
                escapedSegment + '</span></li><li class="b sugg-target"><!-- span class="switch-editing">Edit</span -->' +
                '<span class="graysmall-message">CTRL+' + (index + 1) + '</span><span class="translation">' +
                UI.dmp.diff_prettyHtml(diff_obj) + '</span><span class="realData hide">' + this.translation +
                '</span></li><li class="goto"><a href="#" data-goto="' + this.involved_id[0]+ '">View</a></li></ul>');
        });

        $.each(d.data.not_editable, function(index1) {
            var diff_obj = UI.execDiff(mainStr, this.translation);
            $('.sub-editor.alternatives .overflow', segment).append('<ul class="graysmall notEditable" data-item="' + (index1 + d.data.editable.length + 1) + '"><li class="sugg-source"><span id="' + segment_id + '-tm-' + this.id + '-source" class="suggestion_source">' + escapedSegment + '</span></li><li class="b sugg-target"><!-- span class="switch-editing">Edit</span --><span class="graysmall-message">CTRL+' + (index1 + d.data.editable.length + 1) + '</span><span class="translation">' + UI.dmp.diff_prettyHtml(diff_obj) + '</span><span class="realData hide">' + this.translation + '</span></li><li class="goto"><a href="#" data-goto="' + this.involved_id[0]+ '">View</a></li></ul>');
        });
        // Transform the tags
        UI.markSuggestionTags(segment);


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
        this.lockTags(this.editarea);
        this.editarea.focus();
        this.highlightEditarea();
        this.disableTPOnSegment();
    },
	copyAlternativeInEditarea: function(translation) {
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
        UI.lockTags();
        this.saveInUndoStack('formatSelection');

        $('.editor .editarea .formatSelection-placeholder').after($('.editor .editarea .rangySelectionBoundary'));
        $('.editor .editarea .formatSelection-placeholder').remove();
        $('.editor .editarea').trigger('afterFormatSelection');
        setTimeout(function () {
            setCursorPosition(document.getElementsByClassName("undoCursorPlaceholder")[0]);
        }, 0);
    },

    /**
     * setStatus
     *
     * Set the status at UI level, with potential inconsistent state against what is saved server side.
     * This is necessary for CSS but also for changeStatus function, which relies on this class to
     * determine the status to assign to the setTranslation during the autosave.
     *
     * @param segment DOM element
     * @param status
     */
	setStatus: function(segment, status) {
		segment.removeClass(
            "status-draft status-translated status-approved " +
            "status-rejected status-new status-fixed status-rebutted")
        .addClass("status-" + status);

        segment
            .find( '.status-container a' )
            .attr( 'title', UI.statusHandleTitleAttr(status) );
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
        $("#point2seg").trigger('mousedown');
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
        if ( $('#downloadProject').hasClass('disabled') ) {
            return ;
        }

        if (typeof openOriginalFiles === 'undefined') {
            openOriginalFiles = 0;
        }

        // TODO: this should be relative to the current USER, find a
        // way to generate this at runtime.
        //
        /*if( !config.isGDriveProject || config.isGDriveProject == 'false' ) {
            UI.showDownloadCornerTip();
        }*/
        UI.disableDownloadButtonForDownloadStart( openOriginalFiles );

        if ( typeof window.googleDriveWindows == 'undefined' ) {
            window.googleDriveWindows = {};
        }

        var winName ;

        var driveUpdateDone = function(data) {
            if( !data.urls || data.urls.length === 0 ) {
                APP.alert({msg: "MateCat was not able to update project files on Google Drive. Maybe the project owner revoked privileges to access those files. Ask the project owner to login again and grant Google Drive privileges to MateCat."});

                return;
            }

            var winName ;

            $.each( data.urls, function(index, item) {
                winName = 'window' + item.localId ;
                console.log(winName);


                if ( typeof window.googleDriveWindows[ winName ] != 'undefined' && window.googleDriveWindows[ winName ].opener != null ) {
                    window.googleDriveWindows[ winName ].location.href = item.alternateLink ;
                    window.googleDriveWindows[ winName ].focus();
                } else {
                    window.googleDriveWindows[ winName ] = window.open( item.alternateLink );
                }
            });
        }

        $.ajax({
                cache: false,
                url: UI.downloadFileURL( openOriginalFiles ),
                dataType: 'json'
            })
            .done( driveUpdateDone )
            .always(function() {
                UI.reEnableDownloadButton() ;
            });
    },

    continueDownload: function() {
        if ( $('#downloadProject').hasClass('disabled') ) {
            return ;
        }

        //UI.showDownloadCornerTip();

        UI.disableDownloadButtonForDownloadStart();

        //create an iFrame element
        var iFrameDownload = $( document.createElement( 'iframe' ) ).hide().prop({
            id:'iframeDownload',
            src: ''
        });

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

                //if the cookie is found, download is completed
                //remove iframe an re-enable download button
                if ( typeof token != 'undefined' ) {
                    /*
                     * the token is a json and must be read with "parseJSON"
                     * in case of failure:
                     *      error_message = Object {code: -110, message: "Download failed.
                     *      Please contact the owner of this MateCat instance"}
                     *
                     * in case of success:
                     *      error_message = Object {code: 0, message: "Download Complete."}
                     *
                     */
                    tokenData = $.parseJSON(token);
                    if(parseInt(tokenData.code) < 0) {
                        var notification = {
                            title: 'Error',
                            text: 'Download failed. Please, fix any tag issues and try again in 5 minutes. If it still fails, please, contactsupport@matecat.com',
                            type: 'error'
                        };
                        APP.addNotification(notification);
                        // UI.showMessage({msg: tokenData.message})
                    }
                    UI.reEnableDownloadButton();

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
	fillCurrentSegmentWarnings: function(warningDetails, global) {
		if ( !global ) {
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
            id_job: config.id_job,
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

                UI.translationMismatches = data.translation_mismatches;
                UI.globalWarnings = data.details;
                //The tags with tag projection enabled doesn't show the tags in the source, so dont show the warning
                UI.globalWarnings.tag_issues = UI.filterTagsWithTagProjection(UI.globalWarnings.tag_issues);

                //check for errors
                if (UI.globalWarnings) {

                    UI.renderQAPanel();

                    if (openingSegment)
                        UI.fillCurrentSegmentWarnings(data.details, true);

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
    renderQAPanel: function () {
	    if ( !this.QAComponent ) {
            var mountPoint = $(".qa-wrapper")[0];
            this.QAComponent = ReactDOM.render(React.createElement(QAComponent, {
            }), mountPoint);
        }
        if ( UI.globalWarnings.tag_issues ) {
            this.QAComponent.setTagIssues(UI.globalWarnings.tag_issues);
        }
        if ( UI.globalWarnings.translation_mismatches ) {
            this.QAComponent.setTranslationConflitcts(UI.globalWarnings.translation_mismatches);
        }
    },
	displayMessage: function(messages) {
        var self = this;
		if($('body').hasClass('incomingMsg')) return false;
        $.each(messages, function() {
            var elem = this;
            if(typeof $.cookie('msg-' + this.token) == 'undefined' && ( new Date( this.expire ) > ( new Date() ) ) &&
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
                        $.cookie('msg-' + elem.token, '', {expires: expireDate});
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
    currentSegmentLexiQA: function() {
        var translation = $('.editarea', UI.currentSegment ).text().replace(/\uFEFF/g,'');
        var id_segment = UI.getSegmentId(UI.currentSegment);
        LXQ.doLexiQA(UI.currentSegment, translation, id_segment,false, function () {}) ;
    },
    segmentQA : function( segment ) {
        if ( ! ( segment instanceof UI.Segment) ) {
            segment = new UI.Segment( segment );
        }

		segment.el.addClass('waiting_for_check_result');

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
				if (segment.el.hasClass('waiting_for_check_result')) {

                    // TODO: define d.total more explicitly
					if ( !d.total ) {
						$('p.warnings', segment.el).empty();
						$('span.locked.mismatch', segment.el).removeClass('mismatch');
                        $('.editor .editarea .order-error').removeClass('order-error');

					}
                    else {
                        UI.fillCurrentSegmentWarnings(d.details, false); // update warnings
                        UI.markTagMismatch(d.details);
                        delete UI.checkSegmentsArray[d.token]; // delete the token from the tail
                        segment.el.removeClass('waiting_for_check_result');
                    }
				}

                $(document).trigger('getWarning:local:success', { resp : d, segment: segment }) ;
			}
		}, 'local');
        if (LXQ.enabled()) UI.currentSegmentLexiQA();
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

    changeStatusOffline: function (sid) {
        if($('#segment-' + sid + ' .editarea').text() != '') {
            $('#segment-' + sid).removeClass('status-draft status-approved ' +
                                             'status-new status-rejected ' +
                                             'status-fixed status-rebutted'
                                            ).addClass('status-translated');
        }
    },
    addToSetTranslationTail: function (item) {
        $('#segment-' + item.id_segment).addClass('setTranslationPending');
        this.setTranslationTail.push(item);
    },
    updateToSetTranslationTail: function (item) {
        $('#segment-' + item.id_segment).addClass('setTranslationPending');

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
		var errors = '';
		errors = this.collectSegmentErrors(segment);
		var chosen_suggestion = $('.editarea', segment).data('lastChosenSuggestion');
		autosave = (caller == 'autosave') ? true : false;
        isSplitted = (id_segment.split('-').length > 1) ? true : false;
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
            propagate: propagate
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

                $(document).trigger('translation:change', data.translation);

                var translation = $('.editarea', segment ).text().replace(/\uFEFF/g,'');
                LXQ.doLexiQA(segment,translation,id_segment,true,null);
                $(document).trigger('setTranslation:success', data);
			}
		});
	},
    /**
     * This function is an attempt to centralize all distributed logic used to mark
     * the segment as modified. When a segment is modified we set the class and we set
     * data. And we trigger an event.
     *
     * Preferred way would be to use MateCat.db.segments to save this data, and have the
     * UI redraw after this change. This would help transition to component based architecture.
     *
     * @param el
     */
    setSegmentModified : function( el, isModified ) {
        if ( typeof isModified == 'undefined' ) {
            throw new Exception('isModified parameter is missing.');
        }

        if ( isModified ) {
            el.addClass('modified');
            el.data('modified', true);
            el.trigger('modified');
        } else {
            el.removeClass('modified');
            el.data('modified', false);
            el.trigger('modified');
        }
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
        // TODO: evaluate the need for this given that class "targetarea"
        // seems to be possible to apply without any side effect.
        return '.editarea';
    },

    /**
     *
     * This function is used before the text is sent to the server.
     *
     *
     * @param context
     * @param selector
     * @returns {*|jQuery}
     */
    postProcessEditarea: function(context, selector) {
        selector = (typeof selector === "undefined") ? UI.targetContainerSelector() : selector;
        var area = $( selector, context ).clone();
        var divs = $( area ).find( 'div' );

        if( divs.length ){
            divs.each(function(){
                $(this).find( 'br:not([class])' ).remove();
                $(this).prepend( $('<span class="placeholder">' + config.crPlaceholder + '</span>' ) ).replaceWith( $(this).html() );
            });
        } else {
            $(area).find( 'br:not([class])' ).replaceWith( $('<span class="placeholder">' + config.crPlaceholder + '</span>') );
            $(area).find('br.' + config.crlfPlaceholderClass).replaceWith( '<span class="placeholder">' + config.crlfPlaceholder + '</span>' );
            $(area).find('span.' + config.lfPlaceholderClass).replaceWith( '<span class="placeholder">' + config.lfPlaceholder + '</span>' );
            $(area).find('span.' + config.crPlaceholderClass).replaceWith( '<span class="placeholder">' + config.crPlaceholder + '</span>' );
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

		_str = _str.replace( config.lfPlaceholderRegex, '<span class="monad marker softReturn ' + config.lfPlaceholderClass +'"><br /></span>' )
					.replace( config.crPlaceholderRegex, '<span class="monad marker ' + config.crPlaceholderClass +'"><br /></span>' )
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
	setContextMenu: function() {
		var alt = (this.isMac) ? '&#x2325;' : 'Alt ';
		var cmd = (this.isMac) ? '&#8984;' : 'Ctrl ';
		$('#contextMenu .shortcut .alt').html(alt);
		$('#contextMenu .shortcut .cmd').html(cmd);
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
        UI.propagateTranslation(segment, status, false);
    },

    propagateTranslation: function(segment, status, evenTranslated) {
        this.tempReqArguments = null;
        if( (status == 'translated') || (config.isReview && (status == 'approved'))){
            plusApproved = (config.isReview)? ', section[data-hash=' + $(segment).attr('data-hash') + '].status-approved' : '';

            //NOTE: i've added filter .not( segment ) to exclude current segment from list to be set as draft
            $.each($('section[data-hash=' + $(segment).attr('data-hash') + '].status-new, section[data-hash=' + $(segment).attr('data-hash') + '].status-draft, section[data-hash=' + $(segment).attr('data-hash') + '].status-rejected' + ', section[data-hash=' + $(segment).attr('data-hash') + '].status-translated' + plusApproved ).not( segment ), function() {
                $('.editarea', $(this)).html( $('.editarea', segment).html() );

                //Tag Projection: disable it if enable
                UI.disableTPOnSegment($(this));

                // if status is not set to draft, the segment content is not displayed
                UI.setStatus($(this), status); // now the status, too, is propagated
                $( this ).data( 'autopropagated', true );
                var trans = $('.editarea', this ).text().replace(/\uFEFF/g,'');
                LXQ.doLexiQA(this,trans,UI.getSegmentId(this),true,null);
            });

            //unset actual segment as autoPropagated because now it is translated
            $( segment ).data( 'autopropagated', false );

            //update current Header of Just Opened Segment
            //NOTE: because this method is called after OpenSegment
            // AS callback return for setTranslation ( whe are here now ),
            // currentSegment pointer was already advanced by openSegment and header already created
            //Needed because two consecutive segments can have the same hash
            this.createHeader(segment, true);

        }
    },
    switchFooter: function() {
        console.log('switchFooter');
        this.currentSegment.find('.footer').removeClass('showMatches');
        this.body.toggleClass('hideMatches');
        var cookieName = (config.isReview)? 'hideMatchesReview' : 'hideMatches';
        $.cookie(cookieName + '-' + config.id_job, this.body.hasClass('hideMatches'), { expires: 30 });
    },
    setHideMatches: function () {
        var cookieName = (config.isReview)? 'hideMatchesReview' : 'hideMatches';

        if(typeof $.cookie(cookieName + '-' + config.id_job) != 'undefined') {
            if($.cookie(cookieName + '-' + config.id_job) == 'true') {
                UI.body.addClass('hideMatches')
            } else {
                UI.body.removeClass('hideMatches')
            }
        } else {
            $.cookie(cookieName + '-' + config.id_job, this.body.hasClass('hideMatches'), { expires: 30 });
        }

    },
    setTagLockCustomizeCookie: function (first) {
        if(first && !config.tagLockCustomizable) return;
        var cookieName = 'tagLockDisabled';

        if(typeof $.cookie(cookieName + '-' + config.id_job) != 'undefined') {
            if(first) {
                if($.cookie(cookieName + '-' + config.id_job) == 'true') {
                    UI.body.addClass('tagmarkDisabled');
                    setTimeout(function() {
                        $('.editor .tagLockCustomize').addClass('unlock');
                    }, 100);
                } else {
                    UI.body.removeClass('tagmarkDisabled')
                }
            } else {
                cookieVal = (UI.body.hasClass('tagmarkDisabled'))? 'true' : 'false';
                $.cookie(cookieName + '-' + config.id_job, cookieVal,  { expires: 30 });
            }

        } else {
            $.cookie(cookieName + '-' + config.id_job, this.body.hasClass('tagmarkDisabled'), { expires: 30 });
        }

    },
    forceShowMatchesTab: function () {
        UI.body.removeClass('hideMatches');
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

    storeClientInfo: function () {
        clientInfo = {
            xRes: window.screen.availWidth,
            yRes: window.screen.availHeight
        };
        $.cookie('client_info', JSON.stringify(clientInfo), { expires: 3650 });
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
        // $('.undoCursorPlaceholder').remove();
		if (this.undoStackPosition > 0)
			this.undoStackPosition--;
		this.currentSegment.removeClass('waiting_for_check_result');
		this.registerQACheck();
	},
	saveInUndoStack: function() {
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

        saveSelection();
        // var cursorPos = APP.getCursorPosition(this.editarea.get(0));
        $('.undoCursorPlaceholder').remove();
        if ($('.rangySelectionBoundary').closest('.editarea').length) {
            $('.rangySelectionBoundary').after('<span class="undoCursorPlaceholder monad" contenteditable="false"></span>');
        }
        restoreSelection();
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

        this.commonPartInSegmentIds = a.substring(0,n);
    },
    shortenId: function(id) {
        return id.replace(UI.commonPartInSegmentIds, '<span class="implicit">' + UI.commonPartInSegmentIds + '</span>');
    },
    isCJK: function () {
        var l = config.target_rfc;
        return !!((l == 'zh-CN') || (l == 'zh-TW') || (l == 'ja-JP') || (l == 'ko-KR'));
    },
    isKorean: function () {
        var l = config.target_rfc;
        return l == 'ko-KR';
    },
    start: function () {

        // If some icon is added on the top header menu, the file name is resized
        APP.addDomObserver($('.header-menu')[0], function() {
            APP.fitText($('.breadcrumbs'), $('#pname'), 30);
        });
        setBrowserHistoryBehavior();
        $("article").each(function() {
            APP.fitText($('.filename h2', $(this)), $('.filename h2', $(this)), 30);
        });

        UI.render({
            firstLoad: true
        }).done( function() {
            // launch segments check on opening
            UI.checkWarnings(true);
        });

        $('html').trigger('start');
        if (LXQ.enabled()) {
            $('#lexiqabox').removeAttr("style");
            LXQ.initPopup();
        }
    },
    restart: function () {
        $('#outer').empty();
        this.start();
    },


    /**
     * Edit area click
     *
     * This function can be extended in order for other modules
     * to change the behaviour of segment activation.
     *
     * TODO: .editarea class is bound to presentation and logic
     * and should be decoupled in future refactorings.
     *
     */
    editAreaClick : function(e, operation, action) {
        if (typeof operation == 'undefined') {
            operation = 'clicking';
        }

        UI.saveInUndoStack('click');
        this.onclickEditarea = new Date();

        UI.notYetOpened = false;
        UI.closeTagAutocompletePanel();
        UI.removeHighlightCorrespondingTags();

        var segmentNotYetOpened = ($(this).is(UI.editarea) && !$(this).closest('section').hasClass("opened"));

        if ( !$(this).is(UI.editarea) || (UI.editarea === '') || (!UI.body.hasClass('editing')) || segmentNotYetOpened) {
            if (operation == 'moving') {
                if ((UI.lastOperation == 'moving') && (UI.recentMoving)) {
                    UI.segmentToOpen = segment;
                    UI.blockOpenSegment = true;
                } else {
                    UI.blockOpenSegment = false;
                }
                UI.recentMoving = true;
                clearTimeout(UI.recentMovingTimeout);
                UI.recentMovingTimeout = setTimeout(function() {
                    UI.recentMoving = false;
                }, 1000);
            } else {
                UI.blockOpenSegment = false;
            }

            UI.lastOperation = operation;

            UI.openSegment(this, operation);
            if (action == 'openConcordance')
                UI.openConcordance();

            if (operation != 'moving') {
                segment = $('#segment-' + $(this).data('sid'));
                if(!(config.isReview && (segment.hasClass('status-new') || segment.hasClass('status-draft')))) {
                    UI.scrollSegment($('#segment-' + $(this).data('sid')));
                }
            }
        }

        if (UI.editarea != '') {
            UI.lockTags(UI.editarea);
            UI.checkTagProximity();
        }

        // if (UI.debug) { console.log('Total onclick Editarea: ' + ((new Date()) - this.onclickEditarea)); }

    },
    /**
     * After User click on Translated or T+>> Button
     * @param e
     * @param button
     */
    clickOnTranslatedButton: function (e, button) {
        var buttonValue = ($(button).hasClass('translated')) ? 'translated' : 'next-untranslated';
        e.preventDefault();
        UI.hideEditToolbar();
        //??
        $('.test-invisible').remove();

        UI.setSegmentModified( UI.currentSegment, false ) ;

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
        UI.checkHeaviness();
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

        UI.unlockTags();
        UI.setStatusButtons(button);

        if (!skipChange) {
            UI.changeStatus(button, 'translated', 0);
        }

        if (buttonValue == 'translated') {
            UI.gotoNextSegment();
        } else {
            // TODO: investigate why this trigger click is necessary.
            $(".editarea", UI.nextUntranslatedSegment).trigger("click", "translated")
        }

        UI.lockTags($('#' + $(button).attr('data-segmentid') + ' .editarea'));
        UI.lockTags(UI.editarea);
        UI.changeStatusStop = new Date();
        UI.changeStatusOperations = UI.changeStatusStop - UI.buttonClickStop;
    },

    handleClickOnReadOnly : function(section) {
        if ( UI.justSelecting('readonly') )   return;
        if ( UI.someUserSelection )           return;

        UI.selectingReadonly = setTimeout(function() {
            APP.alert({msg: UI.messageForClickOnReadonly() });
        }, 200);
    },

    messageForClickOnReadonly : function() {
        var msgArchived = 'Job has been archived and cannot be edited.' ;
        var msgOther = 'This part has not been assigned to you.' ;
        return (UI.body.hasClass('archived'))? msgArchived : msgOther ;
    },

    openOptionsPanel: function() {
        if ($(".popup-tm").hasClass('open') ) {
            return false;
        }
        var tab = 'opt';
        $('body').addClass('side-popup');
        $(".popup-tm").addClass('open').show("slide", { direction: "right" }, 400);
        $(".outer-tm").show();
        $('.mgmt-panel-tm .nav-tabs .mgmt-' + tab).click();
        $.cookie('tmpanel-open', 1, { path: '/' });
    },
    closeAllMenus: function (e, fromQA) {
        if ($('.searchbox').is(':visible')) {
            UI.toggleSearch(e);
        }
        $('.mbc-history-balloon-outer').removeClass('mbc-visible');

        var qa_cont = $('.qa-container');
        if ( qa_cont.hasClass('qa-open') && !fromQA) {
            QAComponent.togglePanel();
        }
    }


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
                UI.openSegment( segment );
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
