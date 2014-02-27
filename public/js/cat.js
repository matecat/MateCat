/*
	Component: ui.core
 */
UI = null;

UI = {
	toggleFileMenu: function() {

		if ($('#jobMenu').is(':animated')) {
			return false;
		}
		if (this.body.hasClass('editing')) {
			$('#jobMenu .currSegment').show();
		} else {
			$('#jobMenu .currSegment').hide();
		}
		var menuHeight = $('#jobMenu').height();
		var startTop = 47 - menuHeight;
		$('#jobMenu').css('top', (47 - menuHeight) + "px");

		if ($('#jobMenu').hasClass('open')) {
			$('#jobMenu').animate({top: "-=" + menuHeight + "px"}, 500).removeClass('open');
		} else {
			$('#jobMenu').animate({top: "+=" + menuHeight + "px"}, 300, function() {
				$('body').on('click', function(e) {
					if ($('#jobMenu').hasClass('open')) {
						UI.toggleFileMenu();
					}
				});
			}).addClass('open');
		}
	},
	activateSegment: function() {
		this.createFooter(this.currentSegment);
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
	},
	changeStatus: function(ob, status, byStatus) {
		var segment = (byStatus) ? $(ob).parents("section") : $('#' + $(ob).data('segmentid'));
		$('.percentuage', segment).removeClass('visible');
		if (!segment.hasClass('saved'))
			this.setTranslation(segment, status);
		segment.removeClass('saved');
		this.setContribution(segment, status, byStatus);
		this.setContributionMT(segment, status, byStatus);
		this.getNextSegment(this.currentSegment, 'untranslated');
		if(!this.nextUntranslatedSegmentId) {
			$(window).trigger({
				type: "allTranslated"
			});
		};
		$(window).trigger({
			type: "statusChanged",
			segment: segment,
			status: status
		});
	},
	checkHeaviness: function() {
//		console.log('UI.hasToBeRerendered: ', this.hasToBeRerendered);
//		console.log(this.initSegNum + ' - ' + this.numOpenedSegments + ' - ' + (this.initSegNum/this.numOpenedSegments));
//		if (($('section').length > 500)||(this.numOpenedSegments > 2)) {
		if (($('section').length > 500)||((this.initSegNum/this.numOpenedSegments) < 2)||(this.hasToBeRerendered)) {
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
		if ((typeof segment == 'undefined') || (typeof UI.toSegment != 'undefined')) {
			this.toSegment = undefined;
			return true;
		}

		var closeStart = new Date();
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
		if ((segment.hasClass('modified')) && (saveBrevior)) {
			this.saveSegment(segment);
		}
		this.deActivateSegment(byButton);

		this.lastOpenedEditarea.attr('contenteditable', 'false');
		this.body.removeClass('editing');
		$(segment).removeClass("editor");
		$('span.locked.mismatch', segment).removeClass('mismatch');
		if (!this.opening) {
			this.checkIfFinished(1);
		}
	},
	copySource: function() {

		var source_val = $.trim($(".source", this.currentSegment).text());
		// Test
		//source_val = source_val.replace(/&quot;/g,'"');

		// Attention I use .text to obtain a entity conversion, by I ignore the quote conversion done before adding to the data-original
		// I hope it still works.

		this.saveInUndoStack('copysource');
		$(".editarea", this.currentSegment).text(source_val).keyup().focus();
		this.saveInUndoStack('copysource');
//		$(".editarea", this.currentSegment).effect("highlight", {}, 1000);
		$(window).trigger({
			type: "sourceCopied",
			segment: segment
		});
		this.currentSegment.addClass('highlighted1');
		setTimeout(function() {
			UI.currentSegment.addClass('modified highlighted2');
		}, 100);
		setTimeout(function() {
			UI.currentSegment.removeClass('highlighted1 highlighted2');
		}, 2000);
		this.currentSegmentQA();
		this.setChosenSuggestion(0);
		this.lockTags(this.editarea);
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
		var buttons = '<li><a id="segment-' + this.currentSegmentId + '-nextuntranslated" href="#" class="btn next-untranslated" data-segmentid="segment-' + this.currentSegmentId + '" title="Translate and go to next untranslated">T+&gt;&gt;</a><p>' + ((UI.isMac) ? 'CMD' : 'CTRL') + '+SHIFT+ENTER</p></li><li><a id="segment-' + this.currentSegmentId + '-button-translated" data-segmentid="segment-' + this.currentSegmentId + '" href="#" class="translated"' + disabled + ' >TRANSLATED</a><p>' + ((UI.isMac) ? 'CMD' : 'CTRL') + '+ENTER</p></li>';
		$('#segment-' + this.currentSegmentId + '-buttons').empty().append(buttons);
		$('#segment-' + this.currentSegmentId + '-buttons').before('<p class="warnings"></p>');
	},
	createFooter: function(segment) {
		if ($('.matches .overflow', segment).text() !== '')
			return false;
		if ($('.footer', segment).text() !== '')
			return false; 

		var footer = '<ul class="submenu"><li class="active tab-switcher-tm" id="segment-' + this.currentSegmentId + '-tm"><a tabindex="-1" href="#">Translation matches</a></li><li class="tab-switcher-cc" id="segment-' + this.currentSegmentId + '-cc"><a tabindex="-1" href="#">Concordance</a></li><li class="tab-switcher-gl" id="segment-' + this.currentSegmentId + '-gl"><a tabindex="-1" href="#">Glossary&nbsp;<span class="number"></span></a></li></ul><div class="tab sub-editor matches" id="segment-' + this.currentSegmentId + '-matches"><div class="overflow"></div></div><div class="tab sub-editor concordances" id="segment-' + this.currentSegmentId + '-concordances"><div class="overflow"><div class="cc-search"><div class="input search-source" contenteditable="true" /><div class="input search-target" contenteditable="true" /></div><div class="results"></div></div></div><div class="tab sub-editor glossary" id="segment-' + this.currentSegmentId + '-glossary"><div class="overflow"><div class="gl-search"><div class="input search-source" contenteditable="true" /><div class="input search-target" contenteditable="true" /><!-- a class="search-glossary" href="#"></a --><a class="set-glossary disabled" href="#"></a><div class="comment"><a href="#">(+) Comment</a><div class="input gl-comment" contenteditable="true" /></div></div><div class="results"></div></div></div>';
		$('.footer', segment).html(footer);

		if (($(segment).hasClass('loaded')) && (segment === this.currentSegment) && ($(segment).find('.matches .overflow').text() === '')) {
			
			$('.sub-editor.matches .overflow .graysmall.message', segment).remove();
			$('.sub-editor.matches .overflow', segment).append('<ul class="graysmall message"><li>Sorry, we can\'t help you this time. Check if the language pair is correct. If not, create the project again.</li></ul>');
		}
	},
	createHeader: function() {
		if ($('h2.percentuage', this.currentSegment).length) {
			return;
		}
		var header = '<h2 title="" class="percentuage"><span></span></h2><a href="#" id="segment-' + this.currentSegmentId + '-close" class="close" title="Close this segment"></a><a href="/referenceFile/' + config.job_id + '/' + config.password + '/' + this.currentSegmentId + '" id="segment-' + this.currentSegmentId + '-context" class="context" title="Open context" target="_blank">Context</a>';
		$('#' + this.currentSegment.attr('id') + '-header').html(header);
	},
	createJobMenu: function() {
		var menu = '<nav id="jobMenu" class="topMenu">' +
				'    <ul>';
		$.each(config.firstSegmentOfFiles, function(index) {
			menu += '<li data-file="' + this.id_file + '" data-segment="' + this.first_segment + '"><span class="' + UI.getIconClass(this.file_name.split('.')[this.file_name.split('.').length -1]) + '"></span><a href="#" title="' + this.file_name + '" >' + this.file_name + '</a></li>';
		});

		menu += '    </ul>' +
				'	<ul>' +
				'		<li class="currSegment" data-segment="' + UI.currentSegmentId + '"><a href="#">Go to current segment</a></li>' +
				'    </ul>' +
				'</nav>';
		this.body.append(menu); 
/*
		$('#jobMenu li').each(function() {
			APP.fitText($(this), $('a', $(this)), 20);
		});
*/
	},
	displaySurvey: function(s) {
		if(this.surveyDisplayed) return;
		survey = '<div class="modal survey" data-type="view">' +
				'	<div class="popup-outer"></div>' +
				'	<div class="popup">' +
				'		<a href="#" class="x-popup"></a>' +
				'		<h1>Translation Completed - Take a Survey</h1>' +
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
				$.each(jobs, function(index) {
					if(this == config.job_id) {
						found = true;
					}
				});
				return found;
			}
		} else {
			return false;
		}
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
			}
		} else {
		}
	},
	detectFirstLast: function() {
		var s = $('section');
		this.firstSegment = s.first();
		this.lastSegment = s.last();
	},
	setSegmentPointer: function() {
		if ($('.editor').length) {
			if ($('.editor').isOnScreen()) {
				$('#segmentPointer').hide();
			} else {
				if ($(window).scrollTop() > $('.editor').offset().top) {
					$('#segmentPointer').removeClass('down').css('margin-top', '-10px').addClass('up').show();
				} else {
					$('#segmentPointer').removeClass('up').addClass('down').css('margin-top', ($(window).height() - 140) + 'px').show();
				}
			}
		}
	},
	detectRefSegId: function(where) {
		var step = this.moreSegNum;
		var seg = (where == 'after') ? $('section').last() : (where == 'before') ? $('section').first() : '';
		var segId = (seg.length) ? seg.attr('id').split('-')[1] : 0;
		return segId;
	},
	detectStartSegment: function() {
		if (this.segmentToScrollAtRender) {
			this.startSegmentId = this.segmentToScrollAtRender;
		} else {
			var hash = window.location.hash.substr(1);
			this.startSegmentId = (hash) ? hash : config.last_opened_segment;
		}
	},
// temp
//	enableSearch: function() {
//		$('#filterSwitch').show();
//		this.searchEnabled = true;
//	},

	nextUnloadedResultSegment: function() {
		var found = '';
		var last = $('section').last().attr('id').split('-')[1];
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
					UI.segMoving = $(this).attr('id').split('-')[1];
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
				step: 50,
				segment: segId,
				where: where
			},
			success: function(d) {
				UI.getMoreSegments_success(d);
			}
		});
	},
	getMoreSegments_success: function(d) {
		if (d.error.length)
			this.processErrors(d.error, 'getMoreSegments');
		where = d.data.where;
		if (typeof d.data.files != 'undefined') {
			firstSeg = $('section').first();
			lastSeg = $('section').last();
			var numsegToAdd = 0;
			$.each(d.data.files, function() {
				numsegToAdd = numsegToAdd + this.segments.length;
			});
			this.renderSegments(d.data.files, where, false);

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
		if (where == 'after') {
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
	},
	getNextSegment: function(segment, status) {
		var seg = this.currentSegment;

		var rules = (status == 'untranslated') ? 'section.status-draft:not(.readonly), section.status-rejected:not(.readonly), section.status-new:not(.readonly)' : 'section.status-' + status + ':not(.readonly)';
		var n = $(seg).nextAll(rules).first();
		if (!n.length) {
			n = $(seg).parents('article').next().find(rules).first();
		}
		if (n.length) {
			this.nextUntranslatedSegmentId = $(n).attr('id').split('-')[1];
		} else if ((UI.nextUntranslatedSegmentIdByServer) && (!UI.noMoreSegmentsAfter)) {
			this.nextUntranslatedSegmentId = UI.nextUntranslatedSegmentIdByServer;
		} else {
			this.nextUntranslatedSegmentId = 0;
		}

		var i = $(seg).next();
		if (!i.length) {
			i = $(seg).parents('article').next().find('section').first();
		}
		if (i.length) {
			this.nextSegmentId = $(i).attr('id').split('-')[1];
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
			success: function(d) {
				UI.getSegments_success(d, options);
			}
		});
	},
	getSegments_success: function(d, options) {
		if (d.error.length)
			this.processErrors(d.error, 'getSegments');
		where = d.data.where;
		$.each(d.data.files, function() {
			startSegmentId = this.segments[0].sid;
		});
		if (typeof this.startSegmentId == 'undefined')
			this.startSegmentId = startSegmentId;
		this.body.addClass('loaded');
		if (typeof d.data.files != 'undefined') {
			this.renderSegments(d.data.files, where, this.firstLoad);
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
//				console.log('a');
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
		this.loadingMore = false;
		this.setWaypoints();
		this.markTags();
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
					first_segment: $('section').first().attr('id').split('-')[1],
					last_segment: $('section').last().attr('id').split('-')[1]
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
			}
		}
	},
	gotoOpenSegment: function() {
		if ($('#segment-' + this.currentSegmentId).length) {
			this.scrollSegment(this.currentSegment);
		} else {
			$('#outer').empty();
			this.render({
				firstLoad: false,
				segmentToOpen: this.currentSegmentId
			});
		}
		$(window).trigger({
			type: "scrolledToOpenSegment",
			segment: segment
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
		var el = $("#segment-" + id + "-target").find(".editarea");
		$(el).click();
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
	millisecondsToTime: function(milli) {
		var milliseconds = milli % 1000;
		var seconds = Math.round((milli / 1000) % 60);
		var minutes = Math.floor((milli / (60 * 1000)) % 60);
		return [minutes, seconds];
	},
	closeContextMenu: function() {
		$('#contextMenu').hide();
		$('#spellCheck .words').remove();
	},
	openSegment: function(editarea, operation) {
		var segment = $('#segment-' + $(editarea).attr('data-sid'));
		this.openSegmentStart = new Date();
		if (!this.byButton) {
			if (this.justSelecting('editarea'))
				return;
		}
		this.numOpenedSegments++;
		this.firstOpenedSegment = (this.firstOpenedSegment === 0) ? 1 : 2;
		this.byButton = false;
		this.cacheObjects(editarea);
		this.updateJobMenu();
		$(window).trigger({
			type: "segmentOpened",
			segment: segment
		});

		this.clearUndoStack();
		this.saveInUndoStack('open');
		this.autoSave = true;
		this.activateSegment();
		this.getNextSegment(this.currentSegment, 'untranslated');
		this.setCurrentSegment(segment);
		this.currentSegment.addClass('opened');

		this.currentSegment.attr('data-searchItems', ($('mark.searchMarker', this.editarea).length));

		this.fillCurrentSegmentWarnings(this.globalWarnings, true);
		this.setNextWarnedSegment();

		this.focusEditarea = setTimeout(function() {
			UI.editarea.focus();
			clearTimeout(UI.focusEditarea);
		}, 100);
		this.currentIsLoaded = false;
		this.nextIsLoaded = false;
		if (!this.readonly)
			this.getContribution(segment, 0);
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
	pointToOpenSegment: function() {
		this.gotoOpenSegment();
	},
	removeButtons: function(byButton) {
		var segment = (byButton) ? this.currentSegment : this.lastOpenedSegment;
		$('#' + segment.attr('id') + '-buttons').empty();
		$('p.warnings', segment).remove();
	},
	removeFooter: function(byButton) {
	},
	removeHeader: function(byButton) {
		var segment = (byButton) ? this.currentSegment : this.lastOpenedSegment;
		$('#' + segment.attr('id') + '-header').empty();
	},
	removeStatusMenu: function(statusMenu) {
		statusMenu.empty().hide();
	},
	renderSegments: function(files, where, starting) {
		$.each(files, function(k, v) {
			var newFile = '';
			var fs = this.file_stats;
//            var fid = fs['ID_FILE'];
			var fid = k;
			var articleToAdd = ((where == 'center') || (!$('#file-' + fid).length)) ? true : false;

			if (articleToAdd) {
				filenametoshow = truncate_filename(this.filename, 40);
				newFile += '<article id="file-' + fid + '" class="loading">' +
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
						'			Payable Words: <strong>' + fs.TOTAL_FORMATTED + '</strong>' +
//                '			To-do: <strong>' + fs['DRAFT_FORMATTED'] + '</strong>'+
						'			<span id="rejected" class="hidden">Rejected: <strong>' + fs.REJECTED_FORMATTED + '</strong></span>' +
						'		</li>' +
						'	</ul>';
			}

			var t = config.time_to_edit_enabled;
			$.each(this.segments, function(index) {
//                this.readonly = true;
				var readonly = (this.readonly == 'true') ? true : false;
				var escapedSegment = htmlEncode(this.segment.replace(/\"/g, "&quot;"));

                /* this is to show line feed in source too, because server side we replace \n with placeholders */
                escapedSegment = escapedSegment.replace( config.lfPlaceholderRegex, "\n" );
                escapedSegment = escapedSegment.replace( config.crPlaceholderRegex, "\r" );
                escapedSegment = escapedSegment.replace( config.crlfPlaceholderRegex, "\r\n" );
                /* see also replacement made in source content below */
                /* this is to show line feed in source too, because server side we replace \n with placeholders */

				newFile += '<section id="segment-' + this.sid + '" class="' + ((readonly) ? 'readonly ' : '') + 'status-' + ((!this.status) ? 'new' : this.status.toLowerCase()) + ((this.has_reference == 'true')? ' has-reference' : '') + '">' +
						'	<a tabindex="-1" href="#' + this.sid + '"></a>' +
						'	<span class="sid">' + this.sid + '</span>' +
						'	<div class="body">' +
						'		<div class="header toggle" id="segment-' + this.sid + '-header">' +
//						'			<h2 title="" class="percentuage"><span></span></h2>' + 
//						'			<a href="#" id="segment-' + this.sid + '-close" class="close" title="Close this segment"></a>' +
//						'			<a href="#" id="segment-' + this.sid + '-context" class="context" title="Open context" target="_blank">Context</a>' +
						'		</div>' +
						'		<div class="text">' +
						'			<div class="wrap">' +               /* this is to show line feed in source too, because server side we replace \n with placeholders */
						'				<div class="outersource"><div class="source item" tabindex="0" id="segment-' + this.sid + '-source" data-original="' + escapedSegment + '">' + UI.decodePlaceholdersToText(this.segment) + '</div>' +
						'				<div class="copy" title="Copy source to target">' +
						'                   <a href="#"></a>' +
						'                   <p>' + ((UI.isMac) ? 'CMD' : 'CTRL') + '+RIGHT</p>' +
						'				</div>' +
						'				<div class="target item" id="segment-' + this.sid + '-target">' +
						'					<span class="hide toggle"> ' +
						'						<a href="#" class="warning normalTip exampleTip" title="Warning: as">!</a>' +
						'					</span>' +
						'					<div class="textarea-container">' +
						'						<span class="loader"></span>' +
						'						<div class="' + ((readonly) ? 'area' : 'editarea') + ' invisible" ' + ((readonly) ? '' : 'contenteditable="false" ') + 'spellcheck="true" lang="' + config.target_lang.toLowerCase() + '" id="segment-' + this.sid + '-editarea" data-sid="' + this.sid + '">' + ((!this.translation) ? '' : UI.decodePlaceholdersToText(this.translation)) + '</div>' +
						'						<p class="save-warning" title="Segment modified but not saved"></p>' +
						'					</div> <!-- .textarea-container -->' +
						'				</div> <!-- .target -->' +
						'			</div></div> <!-- .wrap -->' +
						'						<ul class="buttons toggle" id="segment-' + this.sid + '-buttons"></ul>' +
						'			<div class="status-container">' +
						'				<a href=# title="' + ((!this.status) ? 'Change segment status' : this.status.toLowerCase() + ', click to change it') + '" class="status" id="segment-' + this.sid + '-changestatus"></a>' +
						'			</div> <!-- .status-container -->' +
						'		</div> <!-- .text -->' +
						'		<div class="timetoedit" data-raw_time_to_edit="' + this.time_to_edit + '">' +
						((t) ? '			<span class=edit-min>' + this.parsed_time_to_edit[1] + '</span>m:' : '') +
						((t) ? '			<span class=edit-sec>' + this.parsed_time_to_edit[2] + '</span>s' : '') +
						'		</div>' +
						'		<div class="footer toggle"></div> <!-- .footer -->     ' +
						'	</div> <!-- .body -->' +
						'	<ul class="statusmenu"></ul>' +
						'</section> ';
			});

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
	saveSegment: function(segment) {
		var status = (segment.hasClass('status-translated')) ? 'translated' : (segment.hasClass('status-approved')) ? 'approved' : (segment.hasClass('status-rejected')) ? 'rejected' : (segment.hasClass('status-new')) ? 'new' : 'draft';
		if (status == 'new') {
			status = 'draft';
		}
		console.log('SAVE SEGMENT');
		this.setTranslation(segment, status, 'autosave');
		segment.addClass('saved');
	},
	renderAndScrollToSegment: function(sid, file) {
		$('#outer').empty();
		this.render({
			firstLoad: false,
			caller: 'link2file',
			segmentToScroll: sid,
			scrollToFile: true
		});
//        this.render(false, segment.selector.split('-')[1]);
	},
	scrollSegment: function(segment) {
//		console.log(segment);
//        segment = (noOpen)? $('#segment-'+segment) : segment;
//        noOpen = (typeof noOpen == 'undefined')? false : noOpen;
		if (!segment.length) {
			$('#outer').empty();
			this.render({
				firstLoad: false,
				segmentToOpen: segment.selector.split('-')[1]
			});
		}
		var spread = 23;
		var current = this.currentSegment;
		var previousSegment = $(segment).prev('section');
//		console.log(previousSegment);

		if (!previousSegment.length) {
			previousSegment = $(segment);
			spread = 103;
		}
		var destination = "#" + previousSegment.attr('id');
		var destinationTop = $(destination).offset().top;
		if (this.firstScroll) {
			destinationTop = destinationTop + 100;
			this.firstScroll = false;
		}

		if ($(current).length) { // if there is an open segment
			if ($(segment).offset().top > $(current).offset().top) { // if segment to open is below the current segment
				if (!current.is($(segment).prev())) { // if segment to open is not the immediate follower of the current segment
					var diff = (this.firstLoad) ? ($(current).height() - 200 + 120) : 20;
					destinationTop = destinationTop - diff;
				} else { // if segment to open is the immediate follower of the current segment
					destinationTop = destinationTop - spread;
				}
			} else { // if segment to open is above the current segment
				destinationTop = destinationTop - spread;
			}
		} else { // if no segment is opened
			destinationTop = destinationTop - spread;
		}

		$("html,body").stop();
		$("html,body").animate({
			scrollTop: destinationTop - 20
		}, 500);
		setTimeout(function() {
			UI.goingToNext = false;
		}, 500);
	},
	segmentIsLoaded: function(segmentId) {
		if ($('#segment-' + segmentId).length) {
			return true;
		} else {
			return false;
		}
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
			success: function(data) {
				ed = this;
				$.each(data.result, function(key, value) { //key --> 0: { 'word': { 'offset':20, 'misses':['word1','word2'] } }

					var word = Object.keys(value)[0];
					replacements = value[word].misses.join(",");

					var Position = [
						parseInt(value[word].offset),
						parseInt(value[word].offset) + parseInt(word.length)
					];

					var sentTextInPosition = ed.text().substring(Position[0], Position[1]);
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
			},
			success: function(data) {

			}
		});
	},
	setCurrentSegment: function(segment, closed) {
		var id_segment = this.currentSegmentId;
		if (closed) {
			id_segment = 0;
			UI.currentSegment = undefined;
		} else {
			setTimeout(function() {
				var hash_value = window.location.hash;
				window.location.hash = UI.currentSegmentId;
			}, 300);
		}
		var file = this.currentFile;
		if (this.readonly)
			return;
		APP.doRequest({
			data: {
				action: 'setCurrentSegment',
				password: config.password,
				id_segment: id_segment,
				id_job: config.job_id
			},
			success: function(d) {
				UI.setCurrentSegment_success(d);
			}
		});
	},
	setCurrentSegment_success: function(d) {
		if (d.error.length)
			this.processErrors(d.error, 'setCurrentSegment');
		this.nextUntranslatedSegmentIdByServer = d.nextSegmentId;
//		this.nextUntranslatedSegmentIdByServer = d.nextUntranslatedSegmentId;
		this.getNextSegment(this.currentSegment, 'untranslated');
	},
	setDownloadStatus: function(stats) {
		var t = 'approved';
        var app = parseFloat(stats.APPROVED);
        var tra = parseFloat(stats.TRANSLATED);
        var dra = parseFloat(stats.DRAFT);
        var rej = parseFloat(stats.REJECTED);
		if (tra)
			t = 'translated';
		if (dra)
			t = 'draft';
		if (rej)
			t = 'draft';
        if( !tra && !dra && !rej && !app ){
            t = 'draft';
        }
		$('.downloadtr-button').removeClass("draft translated approved").addClass(t);
		var label = (t == 'translated') ? 'DOWNLOAD TRANSLATION' : 'PREVIEW';
		$('#downloadProject').attr('value', label);
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
		var status = 'approved';
		var total = s.TOTAL;
		var t_perc = s.TRANSLATED_PERC;
		var a_perc = s.APPROVED_PERC;
		var d_perc = s.DRAFT_PERC;
		var r_perc = s.REJECTED_PERC;

		var t_perc_formatted = s.TRANSLATED_PERC_FORMATTED;
		var a_perc_formatted = s.APPROVED_PERC_FORMATTED;
		var d_perc_formatted = s.DRAFT_PERC_FORMATTED;
		var r_perc_formatted = s.REJECTED_PERC_FORMATTED;

		var d_formatted = s.DRAFT_FORMATTED;
		var r_formatted = s.REJECTED_FORMATTED;
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
	setStatus: function(segment, status) {
		segment.removeClass("status-draft status-translated status-approved status-rejected status-new").addClass("status-" + status);
	},
	setStatusButtons: function(button) {
		isTranslatedButton = ($(button).hasClass('translated')) ? true : false;
		this.editStop = new Date();
		var segment = this.currentSegment;
		tte = $('.timetoedit', segment);
		this.editTime = this.editStop - this.editStart;
		this.totalTime = this.editTime + tte.data('raw_time_to_edit');
		var editedTime = this.millisecondsToTime(this.totalTime);
		if (config.time_to_edit_enabled) {
			var editSec = $('.timetoedit .edit-sec', segment);
			var editMin = $('.timetoedit .edit-min', segment);
			editMin.text(this.zerofill(editedTime[0], 2));
			editSec.text(this.zerofill(editedTime[1], 2));
		}
		tte.data('raw_time_to_edit', this.totalTime);
		var statusSwitcher = $(".status", segment);
		statusSwitcher.removeClass("col-approved col-rejected col-done col-draft");

		var statusToGo = (isTranslatedButton) ? 'untranslated' : '';
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
            $('#downloadProject').addClass('disabled').val('DOWNLOADING...');

            //create an iFrame element
            var iFrameDownload = $( document.createElement( 'iframe' ) ).hide().prop({
                id:'iframeDownload',
                src: ''
            });

            //append iFrame to the DOM
            $("body").append( iFrameDownload );

            //generate a token download
            var downloadToken = new Date().getTime();

            //set event listner, on ready, attach an interval that check for finished download
            iFrameDownload.ready(function () {

                //create a GLOBAL setInterval so in anonymous function it can be disabled
                downloadTimer = window.setInterval(function () {

                    //check for cookie
                    var token = $.cookie('downloadToken');

                    //if the cookie is found, download is completed
                    //remove iframe an re-enable download button
                    if ( token == downloadToken ) {
                        $('#downloadProject').removeClass('disabled').val('PREVIEW');
                        window.clearInterval( downloadTimer );
                        $.cookie('downloadToken', null, { path: '/', expires: -1 });
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
		$.each(idList, function(index) {
			if (this > sid) {
				$('#point2seg').attr('href', '#' + this);
				return false;
			}
			if (this == idList[idList.length - 1]) {
				$('#point2seg').attr('href', '#' + idList[0]);
			}
		});
	},
	fillWarnings: function(segment, warnings) {
		//console.log( 'fillWarnings' );
		//console.log( warnings);

		//add Warnings to current Segment
		var parentTag = segment.find('p.warnings').parent();
		var actualWarnings = segment.find('p.warnings');

		$.each(warnings, function(key, value) {
			//console.log(warnings[key]);
			parentTag.before(actualWarnings).append('<p class="warnings">' + value.debug + '</p>');
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

	checkWarnings: function(openingSegment) {
		var dd = new Date();
		ts = dd.getTime();
		var seg = (typeof this.currentSegmentId == 'undefined') ? this.startSegmentId : this.currentSegmentId;
		var token = seg + '-' + ts.toString();

		APP.doRequest({
			data: {
				action: 'getWarning',
				id_job: config.job_id,
				password: config.password,
				token: token
			},
			success: function(data) {
				var warningPosition = '';
//                console.log('data.total: '+data.total);
				UI.globalWarnings = data.details;

				//check for errors
				if (UI.globalWarnings.length > 0) {
					//for now, put only last in the pointer to segment id
					warningPosition = '#' + data.details[ Object.keys(data.details).sort().shift() ].id_segment;
//                    console.log('warningPosition: ' + warningPosition);

					if (openingSegment)
						UI.fillCurrentSegmentWarnings(data.details, true);
			
					//switch to css for warning
					$('#notifbox').attr('class', 'warningbox').attr("title", "Some translations seems to have TAGS and/or other untraslatables that do not match the source").find('.numbererror').text(UI.globalWarnings.length);

				} else {
					//if everything is ok, switch css to ok
					$('#notifbox').attr('class', 'notific').attr("title", "Well done, no errors found!").find('.numbererror').text('');
					//reset the pointer to offending segment
					$('#point2seg').attr('href', '#');
				}

				UI.setNextWarnedSegment();
//                $('#point2seg').attr('href', warningPosition);
			}
		});
	},
	currentSegmentQA: function() {
		this.currentSegment.addClass('waiting_for_check_result');
		var dd = new Date();
		ts = dd.getTime();
		var token = this.currentSegmentId + '-' + ts.toString();

		//var src_content = $('.source', this.currentSegment).attr('data-original');

        if( config.brPlaceholdEnabled ){
            src_content = this.postProcessEditarea(this.currentSegment, '.source');
            trg_content = this.postProcessEditarea(this.currentSegment);
        } else {
            src_content = this.getSegmentSource();
            trg_content = this.getSegmentTarget();
        }

		this.checkSegmentsArray[token] = trg_content;
		APP.doRequest({
			data: {
				action: 'getWarning',
				id: this.currentSegmentId,
				token: token,
				password: config.password,
				src_content: src_content,
				trg_content: trg_content
			},
			success: function(d) {
				if (UI.currentSegment.hasClass('waiting_for_check_result')) {

					// check conditions for results discard
					if (!d.total) {
						$('p.warnings', UI.currentSegment).empty();
						$('span.locked.mismatch', UI.currentSegment).removeClass('mismatch');
						return;
					}
					if (UI.editarea.text().trim() != UI.checkSegmentsArray[d.token].trim().replace(config.crlfPlaceholderRegex, ''))
						return;

					UI.fillCurrentSegmentWarnings(d.details, false); // update warnings
					UI.markTagMismatch(d.details);
					delete UI.checkSegmentsArray[d.token]; // delete the token from the tail
					UI.currentSegment.removeClass('waiting_for_check_result');
				}
			}
		}, 'local');
	},
	setTranslation: function(segment, status, caller) {
		caller = (typeof caller == 'undefined') ? false : caller;
//		console.log('SET TRANSLATION');
		var info = $(segment).attr('id').split('-');
		var id_segment = info[1];
		var file = $(segment).parents('article');
//		var status = status;

		// Attention, to be modified when we will lock tags
		if( config.brPlaceholdEnabled ) {
            translation = this.postProcessEditarea(segment);
        } else {
            translation = $('.editarea', segment ).text();
        }

		if (translation === '')
			return false;
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


		APP.doRequest({
			data: {
				action: 'setTranslation',
				id_segment: id_segment,
				id_job: config.job_id,
				id_first_file: file.attr('id').split('-')[1],
				password: config.password,
				status: status,
				translation: translation,
				time_to_edit: time_to_edit,
				id_translator: id_translator,
				errors: errors,
				chosen_suggestion_index: chosen_suggestion,
				autosave: autosave
			},
			success: function(d) {
				UI.setTranslation_success(d, segment, status);
			}
		});
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

	postProcessEditarea: function(context, selector){
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
				$(this).prepend( $('<span class="placeholder">' + config.crlfPlaceholder + '</span>' ) ).replaceWith( $(this).html() );
			});
		} else {
//			console.log('post process 1: ', $(area).html());
//			console.log($(area).find( 'br:not([class])' ).length);
//			$(area).find( 'br:not([class])' ).replaceWith( $('<span class="placeholder">' + config.crlfPlaceholder + '</span>') );
			$(area).find('br:not([class]), br.' + config.crlfPlaceholderClass).replaceWith( '<span class="placeholder">' + config.crlfPlaceholder + '</span>' );
//			$(area).find( 'br:not([class])' ).replaceWith( $('[BR]') );
//			console.log('post process 2: ', $(area).html());
		}

//        Now commented, but valid for future purposes when the user will choose what type of carriage return
//        $('br', area).each(function() {
//
//            try{
//                var br = this;
//                //split ensure array with at least 1 item or throws exception
//                var classes = $(br).attr('class').split(' ');
//                $(classes).each( function( index, value ){
//                    switch( value ){
//                        case config.lfPlaceholderClass:
//                            $(br).after('<span class="placeholder">' + config.lfPlaceholder + '</span>');
//                            break;
//                        case config.crPlaceholderClass:
//                            $(br).after('<span class="placeholder">' + config.crPlaceholder + '</span>');
//                            break;
//                        case config.crlfPlaceholderClass:
//                            $(br).after('<span class="placeholder">' + config.crlfPlaceholder + '</span>');
//                            break;
//                    }
//                });
//            } catch ( e ){
//                console.log( "Exception on placeholder replacement.\nAdded a default placeholder " + e.message );
//                //add a default placeholder, when a return is pressed by the user chrome add a simple <br>
//                //so
//                $(this).after('<span class="placeholder">' + config.crPlaceholder + '</span>');
//            }
//
//        });

		return area.text();



    },

    /**
     * Called when a Segment string returned by server has to be visualized, it replace placeholders with br tags
     * @param str
     * @returns {XML|string}
     */
    decodePlaceholdersToText: function ( str ) {
        var _str = str.replace( config.lfPlaceholderRegex, '<br class="' + config.lfPlaceholderClass +'" />' )
                      .replace( config.crPlaceholderRegex, '<br class="' + config.crPlaceholderClass +'" />' )
                      .replace( config.crlfPlaceholderRegex, '<br class="' + config.crlfPlaceholderClass +'" />' );
        return _str;
    },

	processErrors: function(err, operation) {
		$.each(err, function() {
			if (operation == 'setTranslation') {
				if (this.code != '-10') { // is not a password error
					APP.alert({msg: "Error in saving the translation. Try the following: <br />1) Refresh the page (Ctrl+F5 twice) <br />2) Clear the cache in the browser <br />If the solutions above does not resolve the issue, please stop the translation and report the problem to <b>support@matecat.com</b>"});
				}
			}

			if (operation == 'setContribution' && this.code != '-10') { // is not a password error
				APP.alert({msg: "Error in saving the translation memory.<br />Try the to save again the segment.<br />If the solutions above does not resolve the issue, please stop the translation and report the problem to <b>support@matecat.com</b>"});
			}

			if (this.code == '-10') {
//				APP.alert("Job canceled or assigned to another translator");
				APP.alert({
					msg: 'Job canceled or assigned to another translator', 
					callback: 'reloadPage' 
				});		
				//FIXME
				// This Alert, will be NEVER displayed because are no-blocking
				// Transform location.reload(); to a callable function passed as callback to alert
			}

		});
	},
	reloadPage: function() {
		console.log('reloadPage');
		location.reload();
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
	setTranslation_success: function(d, segment, status) {
		if (d.error.length)
			this.processErrors(d.error, 'setTranslation');
		if (d.data == 'OK') {
			this.setStatus(segment, status);
			this.setDownloadStatus(d.stats);
			this.setProgress(d.stats);
			//check status of global warnings
			this.checkWarnings(false);
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
		if (this.undoStackPosition === 0)
			this.saveInUndoStack('undo');
		var ind = 0;
		if (this.undoStack[this.undoStack.length - 1 - this.undoStackPosition - 1])
			ind = this.undoStack.length - 1 - this.undoStackPosition - 1;

		this.editarea.html(this.undoStack[ind]);
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
	saveInUndoStack: function(action) {
		currentItem = this.undoStack[this.undoStack.length - 1 - this.undoStackPosition];

		if (typeof currentItem != 'undefined') {
			if (currentItem.trim() == this.editarea.html().trim())
				return;
		}

		if (this.editarea.html() === '')
			return;

		var ss = this.editarea.html().match(/<span.*?contenteditable\="false".*?\>/gi);
		var tt = this.editarea.html().match(/&lt;/gi);
		if (tt) {
			if ((tt.length) && (!ss))
				return;
		}

		var diff = (typeof currentItem != 'undefined') ? this.dmp.diff_main(currentItem, this.editarea.html())[1][1] : 'null';
		if (diff == ' selected')
			return;

		var pos = this.undoStackPosition;
		if (pos > 0) {
			this.undoStack.splice(this.undoStack.length - pos, pos);
			this.undoStackPosition = 0;
		}
		this.undoStack.push(this.editarea.html().replace(/(<.*?)\s?selected\s?(.*?\>)/gi, '$1$2'));
	},
	clearUndoStack: function() {
		this.undoStack = [];
	},
	updateJobMenu: function() {
		$('#jobMenu li.current').removeClass('current');
		$('#jobMenu li:not(.currSegment)').each(function(index) {
			if ($(this).attr('data-file') == UI.currentFileId)
				$(this).addClass('current');
		});
		$('#jobMenu li.currSegment').attr('data-segment', UI.currentSegmentId);
	},
//	beforeExit: function() {
//		var dont_confirm_leave = 0; //set dont_confirm_leave to 1 when you want the user to be able to leave withou confirmation
//		var leave_message = 'You are sure that you want to leave?'
//		if(dont_confirm_leave!==1) {
//			if(!e) e = window.event;
//			//e.cancelBubble is supported by IE - this will kill the bubbling process.
//			e.cancelBubble = true;
//			e.returnValue = leave_message;
//			//e.stopPropagation works in Firefox.
//			if (e.stopPropagation) 
//			{
//				e.stopPropagation();
//				e.preventDefault();
//			}
//
//			//return works for Chrome and Safari
//			return leave_message;
//		}		
//	},

	zerofill: function(i, l, s) {
		var o = i.toString();
		if (!s) {
			s = '0';
		}
		while (o.length < l) {
			o = s + o;
		}
		return o;
	}
};

$(document).ready(function() {

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
	//and on every polling interval
	setInterval(function() {
		UI.checkWarnings(false);
	}, config.warningPollingInterval);
});

$.extend($.expr[":"], {
	"containsNC": function(elem, i, match, array) {
		return (elem.textContent || elem.innerText || "").toLowerCase().indexOf((match[3] || "").toLowerCase()) >= 0;
	}
});

$(window).resize(function() {
});


/*
	Component: ui.init
 */
$.extend(UI, {
	init: function(options) {
		this.initStart = new Date();
		if (this.debug)
			console.log('Render time: ' + (this.initStart - renderStart));
		this.numContributionMatchesResults = 3;
		this.numMatchesResults = 10;
		this.numSegments = $('section').length;
		this.editarea = '';
		this.byButton = false;
		this.notYetOpened = true;
		this.pendingScroll = 0;
		this.firstScroll = true;
		this.blockGetMoreSegments = true;
		this.searchParams = {};
		this.searchParams.search = 0;
//		var bb = $.cookie('noAlertConfirmTranslation');
//		this.alertConfirmTranslationEnabled = (typeof bb == 'undefined') ? true : false;
		this.customSpellcheck = false;
		this.noGlossary = false;
		setTimeout(function() {
			UI.blockGetMoreSegments = false;
		}, 200);
		this.detectFirstLast();
		this.reinitMMShortcuts();
		this.initSegmentNavBar();
		rangy.init();
		this.savedSel = null;
		this.savedSelActiveElement = null;
		this.firstOpenedSegment = false;
		this.autoscrollCorrectionEnabled = true;
		this.searchEnabled = true;
		if (this.searchEnabled)
			$('#filterSwitch').show();
		this.viewConcordanceInContextMenu = true;
		if (!this.viewConcordanceInContextMenu)
			$('#searchConcordance').hide();
		this.viewSpellCheckInContextMenu = true;
		if (!this.viewSpellCheckInContextMenu)
			$('#spellCheck').hide();
		setTimeout(function() {
			UI.autoscrollCorrectionEnabled = false;
		}, 2000);
		this.checkSegmentsArray = {};
		this.firstMarking = true;
//		this.markTags(true);
		this.firstMarking = false;
		this.surveyDisplayed = false;
		this.setContextMenu();
		this.createJobMenu();
		$('#alertConfirmTranslation p').text('To confirm your translation, please press on Translated or use the shortcut ' + ((UI.isMac) ? 'CMD' : 'CTRL') + '+Enter.');

		// SET EVENTS
		this.setEvents();
		if(this.surveyAlreadyDisplayed()) {
			this.surveyDisplayed = true;
		}
	},
}); 



/*
	Component: ui.render 
 */
$.extend(UI, {
	render: function(options) {
		firstLoad = (options.firstLoad || false);
		segmentToOpen = (options.segmentToOpen || false);
		segmentToScroll = (options.segmentToScroll || false);
		scrollToFile = (options.scrollToFile || false);
		seg = (segmentToOpen || false);
		this.segmentToScrollAtRender = (seg) ? seg : false;
		this.isWebkit = $.browser.webkit;
		this.isChrome = $.browser.webkit && !!window.chrome;
		this.isFirefox = $.browser.mozilla;
		this.isSafari = $.browser.webkit && !window.chrome;
		this.isMac = (navigator.platform == 'MacIntel') ? true : false;
		this.body = $('body');
		this.firstLoad = firstLoad;

//        if (firstLoad)
//            this.startRender = true;
		this.initSegNum = 100; // number of segments initially loaded
		this.moreSegNum = 25;
		this.numOpenedSegments = 0;
		this.hasToBeRerendered = false;
		this.maxMinutesBeforeRerendering = 30;
		setTimeout(function() {
			UI.hasToBeRerendered = true;
		}, this.maxMinutesBeforeRerendering*60000);	
		this.loadingMore = false;
		this.infiniteScroll = true;
		this.noMoreSegmentsAfter = false;
		this.noMoreSegmentsBefore = false;
		this.blockButtons = false;
		this.blockOpenSegment = false;
		this.dmp = new diff_match_patch();
		this.beforeDropEditareaHTML = '';
		this.beforeDropSearchSourceHTML = '';
		this.currentConcordanceField = null;
		this.droppingInEditarea = false;
		this.draggingInsideEditarea = false;
		this.undoStack = [];
		this.undoStackPosition = 0;
		this.ccSourceUndoStack = [];
		this.ccSourceUndoStackPosition = 0;
		this.ccTargetUndoStack = [];
		this.ccTargetUndoStackPosition = 0;
		this.tagSelection = false;
		this.nextUntranslatedSegmentIdByServer = 0;
		this.cursorPlaceholder = '[[placeholder]]';
		this.openTagPlaceholder = 'åå';
		this.closeTagPlaceholder = 'ΩΩ';
		this.tempViewPoint = '';
		this.checkUpdatesEvery = 180000;
		this.autoUpdateEnabled = true;
		this.goingToNext = false;
		this.preCloseTagAutocomplete = false;

		/**
		 * Global Warnings array definition.
		 */
		this.globalWarnings = [];

		this.downOpts = {offset: '130%'};
		this.upOpts = {offset: '-40%'};
		this.readonly = (this.body.hasClass('archived')) ? true : false;
		this.suggestionShortcutLabel = 'ALT+' + ((UI.isMac) ? "CMD" : "CTRL") + '+';

		this.taglockEnabled = config.taglockEnabled;
		this.debug = false;
//		this.debug = Loader.detect('debug');
//		this.checkTutorialNeed();

		UI.detectStartSegment(); 
		options.openCurrentSegmentAfter = ((!seg) && (!this.firstLoad)) ? true : false;
		UI.getSegments(options);
		if (this.firstLoad && this.autoUpdateEnabled) {
			this.lastUpdateRequested = new Date();
			setTimeout(function() {
				UI.getUpdates();
			}, UI.checkUpdatesEvery);
		}
	},
});


/*
	Component: ui.events 
 */
$.extend(UI, {
	setEvents: function() {
		$("body").bind('keydown', 'Ctrl+return', function(e) {
			e.preventDefault();
			$('.editor .translated').click();
		}).bind('keydown', 'Meta+return', function(e) {
			e.preventDefault();
			$('.editor .translated').click();
		}).bind('keydown', 'Ctrl+shift+return', function(e) {
			e.preventDefault();
			$('.editor .next-untranslated').click();
		}).bind('keydown', 'Meta+shift+return', function(e) {
			e.preventDefault();
			$('.editor .next-untranslated').click();
		}).bind('keydown', 'Ctrl+pageup', function(e) {
			e.preventDefault();
		}).bind('keydown', 'Ctrl+down', function(e) {
			e.preventDefault();
			e.stopPropagation();
			UI.gotoNextSegment();
		}).bind('keydown', 'Meta+down', function(e) {
			e.preventDefault();
			e.stopPropagation();
			UI.gotoNextSegment();
		}).bind('keydown', 'Ctrl+up', function(e) {
			e.preventDefault();
			e.stopPropagation();
			UI.gotoPreviousSegment();
		}).bind('keydown', 'Meta+up', function(e) {
			e.preventDefault();
			e.stopPropagation();
			UI.gotoPreviousSegment();
		}).bind('keydown', 'Ctrl+left', function(e) {
			e.preventDefault();
			UI.pointToOpenSegment();
		}).bind('keydown', 'Meta+left', function(e) {
			e.preventDefault();
			UI.pointToOpenSegment();
		}).bind('keydown', 'Alt+ctrl+i', function(e) {
			e.preventDefault();
			UI.copySource();
		}).bind('keydown', 'Ctrl+z', function(e) {
			e.preventDefault();
			UI.undoInSegment(segment);
			UI.closeTagAutocompletePanel();
		}).bind('keydown', 'Meta+z', function(e) {
			e.preventDefault();
			UI.undoInSegment(segment);
			UI.closeTagAutocompletePanel();
		}).bind('keydown', 'Ctrl+y', function(e) {
			e.preventDefault();
			UI.redoInSegment(segment);
		}).bind('keydown', 'Meta+Shift+z', function(e) {
			e.preventDefault();
			UI.redoInSegment(segment);
		}).bind('keydown', 'Ctrl+c', function(e) {
			UI.tagSelection = false;
		}).bind('keydown', 'Meta+c', function(e) {
			UI.tagSelection = false;
//		}).bind('keydown', 'Backspace', function(e) {
		}).bind('keydown', 'Meta+f', function(e) {
			UI.toggleSearch(e);
		}).bind('keydown', 'Ctrl+f', function(e) {
			UI.toggleSearch(e);
//		}).on('change', '#hideAlertConfirmTranslation', function(e) {
//			console.log($(this).prop('checked'));
//			if ($(this).prop('checked')) {
//				console.log('checked');
//				UI.alertConfirmTranslationEnabled = false;
//				$.cookie('noAlertConfirmTranslation', true, {expires: 1000});
//			} else {
//				console.log('unchecked');
//				UI.alertConfirmTranslationEnabled = true;
//				$.removeCookie('noAlertConfirmTranslation');
//			}
		}).on('click', '#spellCheck .words', function(e) {
			e.preventDefault();
			UI.selectedMisspelledElement.replaceWith($(this).text());
			UI.closeContextMenu();
		}).on('click', '#spellCheck .add', function(e) {
			e.preventDefault();
			UI.closeContextMenu();
			UI.addWord(UI.selectedMisspelledElement.text());
		}).on('click', '.tag-autocomplete li', function(e) {
			e.preventDefault();
//			UI.editarea.html(UI.editarea.html().replace(/&lt;[&;"\w\s\/=]*?(\<span class="tag-autocomplete-endcursor"\>)/gi, '$1'));
//			UI.editarea.html(UI.editarea.html().replace(/&lt;(?:[a-z]*&nbsp;*(["\w\s\/=]*?))?(\<span class="tag-autocomplete-endcursor"\>)/gi, '$2'));
			UI.editarea.html(UI.editarea.html().replace(/&lt;(?:[a-z]*(?:&nbsp;)*["\w\s\/=]*)?(<span class="tag-autocomplete-endcursor"\>)/gi, '$1'));
			saveSelection();
			if(!$('.rangySelectionBoundary', UI.editarea).length) { // click, not keypress
				setCursorPosition(document.getElementsByClassName("tag-autocomplete-endcursor")[0]);
				saveSelection();
			}
//			console.log($('.rangySelectionBoundary', UI.editarea)[0]);
			var ph = $('.rangySelectionBoundary', UI.editarea)[0].outerHTML;
			$('.rangySelectionBoundary', UI.editarea).remove();
			$('.tag-autocomplete-endcursor', UI.editarea).after(ph);
//			setCursorPosition(document.getElementsByClassName("tag-autocomplete-endcursor")[0]);
			$('.tag-autocomplete-endcursor').before(htmlEncode($(this).text()));
			restoreSelection();
			UI.closeTagAutocompletePanel();
			UI.lockTags(UI.editarea);
			UI.currentSegmentQA();
		}).on('click', '.modal.survey .x-popup', function(e) {
			UI.surveyDisplayed = true;
			if(typeof $.cookie('surveyedJobs') != 'undefined') {
				var c = $.cookie('surveyedJobs');
				surv = c.split('||')[0];
				if(config.survey === surv) {
					$.cookie('surveyedJobs', c + config.job_id + ',');
				}
			} else {
				$.cookie('surveyedJobs', config.survey + '||' + config.job_id + ',', { expires: 20, path: '/' });
			}
			$('.modal.survey').remove();
		}).on('click', '.modal.survey .popup-outer', function(e) {
			$('.modal.survey').hide().remove();
		});
		
		$(window).on('scroll', function(e) {
			UI.browserScrollPositionRestoreCorrection();
		}).on('allTranslated', function(e) {
			if(config.survey) UI.displaySurvey(config.survey);
		})
//window.onbeforeunload = UI.beforeExit();
window.onbeforeunload = goodbye;


function goodbye(e) {
	if ($('#downloadProject').hasClass('disabled')) {
		var dont_confirm_leave = 0; //set dont_confirm_leave to 1 when you want the user to be able to leave withou confirmation
		var leave_message = 'You have a pending download. Are you sure you want to quit?'
		if(dont_confirm_leave!==1) {
			if(!e) e = window.event;
			//e.cancelBubble is supported by IE - this will kill the bubbling process.
			e.cancelBubble = true;
			e.returnValue = leave_message;
			//e.stopPropagation works in Firefox.
			if (e.stopPropagation) 
			{
				e.stopPropagation();
				e.preventDefault();
			}

			//return works for Chrome and Safari
			return leave_message;
		}
	}
}   
	
// no more used:
		$("header .filter").click(function(e) {
			e.preventDefault();
			UI.body.toggleClass('filtering');
		});
		$("#filterSwitch").bind('click', function(e) {
			UI.toggleSearch(e);
		});
		$("#segmentPointer").click(function(e) {
			e.preventDefault();
			UI.pointToOpenSegment();
		});

		$(".replace").click(function(e) {
			e.preventDefault();
			UI.body.toggleClass('replace-box');
		});

		jQuery('.editarea').trigger('update');

		$("div.notification-box").mouseup(function() {
			return false;
		});

		$(".search-icon, .search-on").click(function(e) {
			e.preventDefault();
			$("#search").toggle();
		});
		$('.download-chrome a.close').bind('click', function(e) {
			e.preventDefault();
			$('.download-chrome').removeClass('d-open');
		});

		//overlay

		$(".x-stats").click(function(e) {
			$(".stats").toggle();
		});

		$(window).on('sourceCopied', function(event) {
		});

		$("#outer").on('click', 'a.sid', function(e) {
			e.preventDefault();
			e.stopPropagation();
			return false;
		}).on('click', 'a.status', function(e) {
			e.preventDefault();
			e.stopPropagation();
		}).on('click', 'section:not(.readonly) a.status', function(e) {
			console.log('status');
			var segment = $(this).parents("section");
			var statusMenu = $("ul.statusmenu", segment);

			UI.createStatusMenu(statusMenu);
			statusMenu.show();
			var autoCloseStatusMenu = $('html').bind("click.vediamo", function(event) {
				$("ul.statusmenu").hide();
				$('html').unbind('click.vediamo');
				UI.removeStatusMenu(statusMenu);
			});
		}).on('click', 'section.readonly, section.readonly a.status', function(e) {
			e.preventDefault();
			if (UI.justSelecting('readonly'))
				return;
			if (UI.someUserSelection)
				return;

			UI.selectingReadonly = setTimeout(function() {
				APP.alert({msg: 'This part has not been assigned to you.'});
			}, 200);
		}).on('mousedown', 'section.readonly, section.readonly a.status', function(e) {
			sel = window.getSelection();
			UI.someUserSelection = (sel.type == 'Range') ? true : false;
		}).on('dblclick', 'section.readonly', function(e) {
			clearTimeout(UI.selectingReadonly);
		}).on('blur', '.graysmall .translation', function(e) {
			e.preventDefault();
			UI.closeInplaceEditor($(this));
		}).on('click', '.graysmall .edit-buttons .cancel', function(e) {
			e.preventDefault();
			UI.closeInplaceEditor($(this).parents('.graysmall').find('.translation'));
		}).on('click', '.graysmall .edit-buttons .save', function(e) {
			e.preventDefault();
			console.log('save');
			ed = $(this).parents('.graysmall').find('.translation');
			UI.editContribution(UI.currentSegment, $(this).parents('.graysmall'));
			UI.closeInplaceEditor(ed);
		});

		$(".joblink").click(function(e) {
			e.preventDefault();
			$(".joblist").toggle();
			return false;
		});

		$(".statslink").click(function(e) {
			e.preventDefault();
			e.stopPropagation();
			$(".stats").toggle();
		});

		$(".getoriginal").click(function(e) {
			e.preventDefault();
			$('#originalDownload').submit();
		});
		$("form#fileDownload").bind('submit', function(e) {
			e.preventDefault();
		});

		$('html').click(function() {
			$(".menucolor").hide();
		}).on('click', '#downloadProject', function(e) {
			e.preventDefault();
			if($('#downloadProject').hasClass('disabled')) return false;
			if ($("#notifbox").hasClass("warningbox")) {
				APP.confirm({
					name: 'confirmDownload',
					cancelTxt: 'Fix errors',
					onCancel: 'goToFirstError',
					callback: 'continueDownload',
					okTxt: 'Continue',
					msg: "Potential errors (missing tags, numbers etc.) found in the text. <br>If you continue, part of the content could be untranslated - look for the string \"UNTRANSLATED_CONTENT\" in the downloaded file(s).<br><br>Continue downloading or fix the error in MateCat:"
				});
			} else {
				UI.continueDownload();
			}
		}).on('click', '.alert .close', function(e) {
			e.preventDefault();
			$('.alert').remove();
		}).on('click', '.downloadtr-button .draft', function(e) {
			if (UI.isChrome) {
				$('.download-chrome').addClass('d-open');
				setTimeout(function() {
					$('.download-chrome').removeClass('d-open');
				}, 7000);
			}
		}).on('click', '#contextMenu #searchConcordance', function(e) {
			if ($('#contextMenu').attr('data-sid') == UI.currentSegmentId) {
				UI.openConcordance();
			} else {
				$('#segment-' + $('#contextMenu').attr('data-sid') + ' .editarea').trigger('click', ['clicking', 'openConcordance']);
			}
		});

		$("#outer").on('click', 'a.percentuage', function(e) {
			e.preventDefault();
			e.stopPropagation();			
		}).on('click', '.editarea', function(e, operation, action) {
			if (typeof operation == 'undefined')
				operation = 'clicking';
			this.onclickEditarea = new Date();
			UI.notYetOpened = false;
			UI.closeTagAutocompletePanel();
			if ((!$(this).is(UI.editarea)) || (UI.editarea === '') || (!UI.body.hasClass('editing'))) {
				if (operation == 'moving') {
					if ((UI.lastOperation == 'moving') && (UI.recentMoving)) {
						UI.segmentToOpen = segment;
						UI.blockOpenSegment = true;

						console.log('ctrl+down troppo vicini');
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

				if (operation != 'moving')
					UI.scrollSegment($('#segment-' + $(this).data('sid')));
			}
			if (UI.debug)
				console.log('Total onclick Editarea: ' + ((new Date()) - this.onclickEditarea));
		}).on('keydown', '.editor .source, .editor .editarea', 'alt+meta+c', function(e) {
			e.preventDefault();
			UI.preOpenConcordance();
		}).on('keydown', '.editor .source, .editor .editarea', 'alt+ctrl+c', function(e) {
			e.preventDefault();
			UI.preOpenConcordance();
		}).on('keypress', '.editor .editarea', function(e) {
//			console.log('keypress: ', UI.editarea.html());

			if((e.which == 60)&&(UI.taglockEnabled)) { // opening tag sign
//				console.log('KEYPRESS SU EDITAREA: ', UI.editarea.html());
				if($('.tag-autocomplete').length) {
					e.preventDefault();
					return false;
				}
				UI.openTagAutocompletePanel();
//				console.log('Q: ', UI.editarea.html());
			}
			if((e.which == 62)&&(UI.taglockEnabled)) { // closing tag sign
				if($('.tag-autocomplete').length) {
					e.preventDefault();
					return false;
				}
			}
			setTimeout(function() {
				if($('.tag-autocomplete').length) {
//					console.log(UI.editarea.html().match(/^(<span class="tag-autocomplete-endcursor"\><\/span>&lt;)/gi) != null);
					if(UI.editarea.html().match(/^(<span class="tag-autocomplete-endcursor"\><\/span>&lt;)/gi) !== null) {
						UI.editarea.html(UI.editarea.html().replace(/^(<span class="tag-autocomplete-endcursor"\><\/span>&lt;)/gi, '&lt;<span class="tag-autocomplete-endcursor"><\/span>'));
					}
					UI.checkAutocompleteTags();
				}
			}, 50);			
		}).on('keydown', '.editor .editarea', function(e) {
//			console.log('keydown: ', UI.editarea.html());
/*
			var special = event.type !== "keypress" && jQuery.hotkeys.specialKeys[ event.which ];
			if ((event.metaKey && !event.ctrlKey && special !== "meta") || (event.ctrlKey)) {
				if (event.which == 88) { // ctrl+x
					if ($('.selected', $(this)).length) {console.log('VEDIAMO');
						event.preventDefault();
						UI.tagSelection = getSelectionHtml();
						$('.selected', $(this)).remove();
					}
				}
			}
*/
			if ((e.which == 8) || (e.which == 46)) { // backspace e canc(mac)
				if ($('.selected', $(this)).length) {
					e.preventDefault();
					$('.selected', $(this)).remove();
					UI.saveInUndoStack('cancel');
					UI.currentSegmentQA();
				} else {
//					try {
//						console.log(UI.editarea.text().match(/<.*?\>/gi) == null);
						var numTagsBefore = (UI.editarea.text().match(/<.*?\>/gi) != null)? UI.editarea.text().match(/<.*?\>/gi).length : 0;
						var numSpacesBefore = UI.editarea.text().match(/\s/gi).length;

						saveSelection('noMove');
						parentTag = $('span.locked', UI.editarea).has('.rangySelectionBoundary');
						isInsideTag = $('span.locked .rangySelectionBoundary', UI.editarea).length;
						parentMark = $('.searchMarker', UI.editarea).has('.rangySelectionBoundary');
						isInsideMark = $('.searchMarker .rangySelectionBoundary', UI.editarea).length;
						restoreSelection();
						
						// insideTag management
						if ((e.which == 8)&&(isInsideTag)) {
//							console.log('AA: ', UI.editarea.html()); 
							parentTag.remove();
							e.preventDefault();
//							console.log('BB: ', UI.editarea.html());
						}
//						console.log(e.which + ' - ' + isInsideTag);
						setTimeout(function() {
							if ((e.which == 46)&&(isInsideTag)) {
								console.log('inside tag');
							}
//							console.log(e.which + ' - ' + isInsideTag);
//							console.log('CC: ', UI.editarea.html());
							var numTagsAfter = (UI.editarea.text().match(/<.*?\>/gi) != null)? UI.editarea.text().match(/<.*?\>/gi).length : 0;
							var numSpacesAfter = UI.editarea.text().match(/\s/gi).length;
							if (numTagsAfter < numTagsBefore)
								UI.saveInUndoStack('cancel');
							if (numSpacesAfter < numSpacesBefore)
								UI.saveInUndoStack('cancel');
//							console.log('DD: ', UI.editarea.html());

						}, 50);
						
						// insideMark management
						if ((e.which == 8)&&(isInsideMark)) {
							console.log('inside mark'); 
						}
				
//						selectText(this);


//					} catch (e) {
						//Error: Cannot read property 'length' of null 
						//when we are on first character position in edit area and try to BACKSPACE
						//console.log(e.message); 
//					}
				}
			}
			
			if (e.which == 8) { // backspace
				if($('.tag-autocomplete').length) {
					UI.closeTagAutocompletePanel();
					setTimeout(function() {
						UI.openTagAutocompletePanel();
						added = UI.getPartialTagAutocomplete();
						if(added === '') UI.closeTagAutocompletePanel();
					}, 10);		
				}
			}
			if (e.which == 37) { // left arrow
				selection = window.getSelection();
				range = selection.getRangeAt(0);
				if (range.startOffset != range.endOffset) { // if something is selected when the left button is pressed...
					r = range.startContainer.data;
					if ((r[0] == '<') && (r[r.length - 1] == '>')) { // if a tag is selected
						saveSelection();
						rr = document.createRange();
						referenceNode = $('.rangySelectionBoundary', UI.editarea).first().get(0);
						rr.setStartBefore(referenceNode);
						rr.setEndBefore(referenceNode);
						$('.rangySelectionBoundary', UI.editarea).remove();
					}
				}
				UI.closeTagAutocompletePanel();
//				UI.jumpTag('start');
			}

			if (e.which == 38) { // top arrow
				if($('.tag-autocomplete').length) {
					if(!$('.tag-autocomplete li.current').is($('.tag-autocomplete li:first'))) {
						$('.tag-autocomplete li.current:not(:first-child)').removeClass('current').prevAll(':not(.hidden)').first().addClass('current');
						return false;
					}	
				}
				selection = window.getSelection();
				range = selection.getRangeAt(0);
				if (range.startOffset != range.endOffset) {
					r = range.startContainer.data;
					if ((r[0] == '<') && (r[r.length - 1] == '>')) {
						saveSelection();
						rr = document.createRange();
						referenceNode = $('.rangySelectionBoundary', UI.editarea).last().get(0);
						rr.setStartAfter(referenceNode);
						rr.setEndAfter(referenceNode);
						$('.rangySelectionBoundary', UI.editarea).remove();
					}
				}
			}
			if (e.which == 39) { // right arrow
				selection = window.getSelection();
				range = selection.getRangeAt(0);
				if (range.startOffset != range.endOffset) {
					r = range.startContainer.data;
					if ((r[0] == '<') && (r[r.length - 1] == '>')) {
						saveSelection();
						rr = document.createRange();
						referenceNode = $('.rangySelectionBoundary', UI.editarea).last().get(0);
						rr.setStartAfter(referenceNode);
						rr.setEndAfter(referenceNode);
						$('.rangySelectionBoundary', UI.editarea).remove();
					}
				}
				UI.closeTagAutocompletePanel();
//				UI.jumpTag('end');
			}

			if (e.which == 40) { // down arrow
				if($('.tag-autocomplete').length) {
					$('.tag-autocomplete li.current:not(:last-child)').removeClass('current').nextAll(':not(.hidden)').first().addClass('current');	
					return false;
				}
				selection = window.getSelection();
				range = selection.getRangeAt(0);
				if (range.startOffset != range.endOffset) {
					r = range.startContainer.data;
					if ((r[0] == '<') && (r[r.length - 1] == '>')) {
						saveSelection();
						rr = document.createRange();
						referenceNode = $('.rangySelectionBoundary', UI.editarea).last().get(0);
						rr.setStartAfter(referenceNode);
						rr.setEndAfter(referenceNode);
						$('.rangySelectionBoundary', UI.editarea).remove();
					}
				}
			}

			if (!((e.which == 37) || (e.which == 38) || (e.which == 39) || (e.which == 40))) { // not arrows
				if (UI.body.hasClass('searchActive')) {
					UI.resetSearch();
				}
			}
			if (e.which == 32) { // space
				setTimeout(function() {
					UI.saveInUndoStack('space');
				}, 100);
			}

			if (e.which == 13) { // return
				if($('.tag-autocomplete').length) {
					$('.tag-autocomplete li.current').click();	
					return false;
				}
			}

			if (
					(e.which == 13) || // return
					(e.which == 32) || // space
					(e.which == 49) || // semicomma
					(e.which == 188) || // comma
					(e.which == 186) || // semicomma
					(e.which == 190) || // mark
					(e.which == 191) || // question mark
					(e.which == 222)) { // apostrophe
				UI.spellCheck();
			}

		}).on('input', '.editarea', function(e) {
//			console.log('input in editarea');
			if (UI.body.hasClass('searchActive')) {
				console.log('on input');
				UI.resetSearch();
			}
			UI.currentSegment.addClass('modified').removeClass('waiting_for_check_result');
			if (UI.draggingInsideEditarea) {
				$(UI.tagToDelete).remove();
				UI.draggingInsideEditarea = false;
				UI.tagToDelete = null;
			}
			if (UI.droppingInEditarea) {
				console.log('prima del clean 1: ', UI.editarea.html());
//				$('.editor .editarea span[style*="color"]+br').remove();
//				console.log('prima del clean 2: ', UI.editarea.html());
				UI.cleanDroppedTag(UI.editarea, UI.beforeDropEditareaHTML);
			}
			if (!UI.body.hasClass('searchActive'))
				setTimeout(function() {
//					console.log('before tag lock: ', UI.editarea.html());
					UI.lockTags(UI.editarea);
//					console.log('after tag lock: ', UI.editarea.html());
				}, 10);
			UI.registerQACheck();
		}).on('input', '.editor .cc-search .input', function(e) {
			UI.markTagsInSearch($(this));
		}).on('click', '.editor .source .locked,.editor .editarea .locked', function(e) {
			e.preventDefault();
			e.stopPropagation();
			setCursorPosition(this);
			selectText(this);
			$(this).toggleClass('selected');
//		}).on('contextmenu', '.source', function(e) {
			// temporarily disabled
//            if(UI.viewConcordanceInContextMenu||UI.viewSpellCheckInContextMenu) e.preventDefault();
		}).on('mousedown', '.source', function(e) {
			if (e.button == 2) { // right click
				// temporarily disabled
				return true;
/*
				if ($('#contextMenu').css('display') == 'block')
					return true;

				var selection = window.getSelection();
				if (selection.type == 'Range') { // something is selected
					var str = selection.toString().trim();
					if (str.length) { // the trimmed string is not empty
						UI.currentSelectedText = str;

						UI.currentSearchInTarget = ($(this).hasClass('source')) ? 0 : 1;
						$('#contextMenu').attr('data-sid', $(this).parents('section').attr('id').split('-')[1]);

						if (UI.customSpellcheck) {
							var range = selection.getRangeAt(0);
							var tag = range.startContainer.parentElement;
							if (($(tag).hasClass('misspelled')) && (tag === range.endContainer.parentElement)) { // the selected element is in a misspelled element
								UI.selectedMisspelledElement = $(tag);
								var replacements = '';
								var words = $(tag).attr('data-replacements').split(',');
								$.each(words, function(item) {
									replacements += '<a class="words" href="#">' + this + '</a>';
								});
								if ((words.length == 1) && (words[0] == '')) {
									$('#spellCheck .label').hide();
								} else {
									$('#spellCheck .label').show();
								}
								$('#spellCheck .words').remove();
								$('#spellCheck').show().find('.label').after(replacements);
							} else {
								$('#spellCheck').hide();
							}
						}

						UI.showContextMenu(str, e.pageY, e.pageX);
					}
				}
				return false;
				*/
			}
			return true;
		}).on('dragstart', '.editor .editarea .locked', function(e) {
			var selection = window.getSelection();
			var range = selection.getRangeAt(0);
			if (range.startContainer.data != range.endContainer.data)
				return false;

			UI.draggingInsideEditarea = true;
			UI.tagToDelete = $(this);
		}).on('drop', '.editor .editarea', function(e) {
			if (e.stopPropagation) {
				e.stopPropagation(); // stops the browser from redirecting.
			}
			UI.beforeDropEditareaHTML = UI.editarea.html();
			UI.droppingInEditarea = true;

			$(window).trigger({
				type: "droppedInEditarea",
				segment: UI.currentSegment
			});
			UI.saveInUndoStack('drop');
			console.log('bbb: ', UI.editarea.html());
			$(this).css('float', 'left');
			setTimeout(function() {
				UI.editarea.removeAttr('style');
				console.log('ccc: ', UI.editarea.html());
				UI.saveInUndoStack('drop');
			}, 100);
		}).on('drop paste', '.editor .cc-search .input, .editor .gl-search .input', function(e) {
			UI.beforeDropSearchSourceHTML = UI.editarea.html();
			UI.currentConcordanceField = $(this);
			setTimeout(function() {
				UI.cleanDroppedTag(UI.currentConcordanceField, UI.beforeDropSearchSourceHTML);
			}, 100);
		}).on('click', '.editor .editarea .locked.selected', function(e) {
		}).on('click', '.editor .editarea, .editor .source', function(e) {
			$('.selected', $(this)).removeClass('selected');
			UI.currentSelectedText = false;
			UI.currentSearchInTarget = false;
			$('#contextMenu').hide();
		}).on('click', 'a.translated, a.next-untranslated', function(e) {
			var w = ($(this).hasClass('translated')) ? 'translated' : 'next-untranslated';
			e.preventDefault();
			UI.currentSegment.removeClass('modified');

			var skipChange = false;
			if (w == 'next-untranslated') {
				console.log('entra');
				if (!UI.segmentIsLoaded(UI.nextUntranslatedSegmentId)) {
					UI.changeStatus(this, 'translated', 0);
					skipChange = true;
					if (!UI.nextUntranslatedSegmentId) {
						$('#' + $(this).attr('data-segmentid') + '-close').click();
					} else {
						UI.reloadWarning();
					}

				}
			} else {
				if (!$(UI.currentSegment).nextAll('section').length) {
					UI.changeStatus(this, 'translated', 0);
					skipChange = true;
					$('#' + $(this).attr('data-segmentid') + '-close').click();
				}
			}

			UI.checkHeaviness();
			if (UI.blockButtons) {
				if (UI.segmentIsLoaded(UI.nextUntranslatedSegmentId) || UI.nextUntranslatedSegmentId === '') {
					console.log('segment is already loaded');
				} else {
					console.log('segment is not loaded');

					if (!UI.noMoreSegmentsAfter) {
						UI.reloadWarning();
					}
				}
				return;
			}
			UI.blockButtons = true;

			UI.unlockTags();
			UI.setStatusButtons(this);

            if (!skipChange)
                UI.changeStatus(this, 'translated', 0);

			if (w == 'translated') {
				UI.gotoNextSegment();
			} else {
				$(".editarea", UI.nextUntranslatedSegment).trigger("click", "translated");
			}

			UI.markTags();
			UI.lockTags(UI.editarea);
			UI.changeStatusStop = new Date();
			UI.changeStatusOperations = UI.changeStatusStop - UI.buttonClickStop;
		}).on('click', 'a.approved', function(e) {
			UI.setStatusButtons(this);
			$(".editarea", UI.nextUntranslatedSegment).click();

			UI.changeStatus(this, 'approved', 0);
			UI.changeStatusStop = new Date();
			UI.changeStatusOperations = UI.changeStatusStop - UI.buttonClickStop;

		}).on('click', 'a.d, a.a, a.r, a.f', function(e) {
			var segment = $(this).parents("section");
			$("a.status", segment).removeClass("col-approved col-rejected col-done col-draft");
			$("ul.statusmenu", segment).toggle();
			return false;
		}).on('click', 'a.d', function(e) {
			UI.changeStatus(this, 'translated', 1);
		}).on('click', 'a.a', function(e) {
			UI.changeStatus(this, 'approved', 1);
		}).on('click', 'a.r', function(e) {
			UI.changeStatus(this, 'rejected', 1);
		}).on('click', 'a.f', function(e) {
			UI.changeStatus(this, 'draft', 1);
		}).on('click', '.editor .outersource .copy', function(e) {
//        }).on('click', 'a.copysource', function(e) {
			e.preventDefault();
			UI.copySource();
		}).on('click', '.tagmenu, .warning, .viewer, .notification-box li a', function(e) {
			return false;
		}).on('click', '.tab-switcher-tm', function(e) {
			e.preventDefault();
			$('.editor .submenu .active').removeClass('active');
			$(this).addClass('active');
			$('.editor .sub-editor').hide();
			$('.editor .sub-editor.matches').show();
		}).on('click', '.tab-switcher-cc', function(e) {
			e.preventDefault();
			$('.editor .submenu .active').removeClass('active');
			$(this).addClass('active');
			$('.editor .sub-editor').hide();
			$('.editor .sub-editor.concordances').show();
			$('.cc-search .search-source').focus();
//        }).on('keydown', '.sub-editor .cc-search .search-source', 'return', function(e) {
			//if($(this).text().length > 2) UI.getConcordance($(this).text(), 0);
		}).on('click', '.tab-switcher-gl', function(e) {
			e.preventDefault();
			$('.editor .submenu .active').removeClass('active');
			$(this).addClass('active');
			$('.editor .sub-editor').hide();
			$('.editor .sub-editor.glossary').show();
			$('.gl-search .search-source').focus();
		}).on('click', '.sub-editor.glossary .overflow a.trash', function(e) {
			e.preventDefault();
			ul = $(this).parents('ul.graysmall').first();
			UI.deleteGlossaryItem($(this).parents('ul.graysmall').first());
		}).on('click', '.sub-editor.glossary .details .comment', function(e) {
			e.preventDefault();
			$(this).attr('contenteditable', true).focus();
		}).on('blur', '.sub-editor.glossary .details .comment', function(e) {
			e.preventDefault();
			$(this).attr('contenteditable', false);
			item = $(this).parents('.graysmall');
			APP.doRequest({
				data: {
					action: 'glossary',
					exec: 'update',
					segment: item.find('.suggestion_source').text(),
					translation: item.find('.translation').text(),
					comment: $(this).text(),
					id_item: item.attr('data-id'),
					id_job: config.job_id,
					password: config.password
				},
				context: [UI.currentSegment, next],
				success: function(d) {
				},
				complete: function() {
				}
			});
		}).on('keydown', '.sub-editor .cc-search .search-source', function(e) {
			if (e.which == 13) { // enter
				e.preventDefault();
				var txt = $(this).text();
				if (txt.length > 1)
					UI.getConcordance(txt, 0);
			} else {
				if ($('.editor .sub-editor .cc-search .search-target').text().length > 0) {
					$('.editor .sub-editor .cc-search .search-target').text('');
					$('.editor .sub-editor.concordances .results').empty();
				}
			}
		}).on('keydown', '.sub-editor .cc-search .search-target', function(e) {
			if (e.which == 13) {
				e.preventDefault();
				var txt = $(this).text();
				if (txt.length > 2)
					UI.getConcordance(txt, 1);
			} else {
				if ($('.editor .sub-editor .cc-search .search-source').text().length > 0) {
					$('.editor .sub-editor .cc-search .search-source').text('');
					$('.editor .sub-editor.concordances .results').empty();
				}
			}
		}).on('click', '.sub-editor .gl-search .search-glossary', function(e) {
			e.preventDefault();
			var txt = $(this).parents('.gl-search').find('.search-source').text();
			segment = $(this).parents('section').first();
			if (txt.length > 1) {
				UI.getGlossary(segment, false);
			} else {
				APP.alert({msg: 'Please insert a string of two letters at least!'});
			}

		}).on('keydown', '.sub-editor .gl-search .search-source', function(e) {
			if (e.which == 13) {
				e.preventDefault();
				var txt = $(this).text();
				if (txt.length > 2) {
					segment = $(this).parents('section').first();
					UI.getGlossary(segment, false);
				}
			}
		}).on('input', '.sub-editor .gl-search .search-target', function(e) {
			gl = $(this).parents('.gl-search').find('.set-glossary');	
			if($(this).text() === '') {
				gl.addClass('disabled');
			} else {
				gl.removeClass('disabled');
			}
		}).on('click', '.sub-editor .gl-search .set-glossary', function(e) {
			e.preventDefault();
		}).on('click', '.sub-editor .gl-search .set-glossary:not(.disabled)', function(e) {
			e.preventDefault();
			if($(this).parents('.gl-search').find('.search-source').text() === '') {
				APP.alert({msg: 'Please insert a glossary term.'});
				return false;
			} else {
				UI.setGlossaryItem();
			}
		}).on('click', '.sub-editor .gl-search .comment a', function(e) {
			e.preventDefault();
			$(this).parents('.comment').find('.gl-comment').toggle();
		}).on('paste', '.editarea', function(e) {
			UI.saveInUndoStack('paste');
			$('#placeHolder').remove();
			var node = document.createElement("div");
			node.setAttribute('id', 'placeHolder');
			removeSelectedText($(this));
			insertNodeAtCursor(node);
			if(UI.isFirefox) pasteHtmlAtCaret('<div id="placeHolder"></div>');
			var ev = (UI.isFirefox) ? e : event;
			handlepaste(this, ev);

			$(window).trigger({
				type: "pastedInEditarea",
				segment: segment
			});

			setTimeout(function() {
				UI.saveInUndoStack('paste');
			}, 100);
			UI.lockTags(UI.editarea);
			UI.currentSegmentQA();
		}).on('click', 'a.close', function(e, param) {
			e.preventDefault();
			var save = (typeof param == 'undefined') ? 'noSave' : param;
			UI.closeSegment(UI.currentSegment, 1, save);
		}).on('keyup', '.editor .editarea', function(e) {
			if ( e.which == 13 ){
//				$(this).find( 'br:not([class])' ).replaceWith( $('<br class="' + config.crPlaceholderClass + '" />') );

                //replace all divs with a br and remove all br without a class
//                var divs = $( this ).find( 'div' );
//                if( divs.length ){
//					divs.each(function(){
//						$(this).find( 'br:not([class])' ).remove();
//						$(this).prepend( $('<br class="' + config.crPlaceholderClass + '" />' ) ).replaceWith( $(this).html() );
//					});
//                } else {
//                    $(this).find( 'br:not([class])' ).replaceWith( $('<br class="' + config.crPlaceholderClass + '" />') );
//                }
			}
		});
		UI.toSegment = true;
		if (!this.segmentToScrollAtRender)
			UI.gotoSegment(this.startSegmentId);

		$(".end-message-box a.close").on('click', function(e) {
			e.preventDefault();
			UI.body.removeClass('justdone');
		});

		this.checkIfFinishedFirst();

		$("section .close").bind('keydown', 'Shift+tab', function(e) {
			e.preventDefault();
			$(this).parents('section').find('a.translated').focus();
		});

		$("a.translated").bind('keydown', 'tab', function(e) {
			e.preventDefault();
			$(this).parents('section').find('.close').focus();
		});

		$("#navSwitcher").on('click', function(e) {
			e.preventDefault();
		});
		$("#pname").on('click', function(e) {
			e.preventDefault();
			UI.toggleFileMenu();
		});
		$("#jobNav .jobstart").on('click', function(e) {
			e.preventDefault();
			UI.scrollSegment($('#segment-' + config.firstSegmentOfFiles[0].first_segment));
		});
		$("#jobMenu").on('click', 'li:not(.currSegment)', function(e) {
			e.preventDefault();
			UI.renderAndScrollToSegment($(this).attr('data-segment'), true);
		});
		$("#jobMenu").on('click', 'li.currSegment', function(e) {
			e.preventDefault();
			UI.pointToOpenSegment();
		});
		$("#jobNav .prevfile").on('click', function(e) {
			e.preventDefault();
			currArtId = $(UI.currentFile).attr('id').split('-')[1];
			$.each(config.firstSegmentOfFiles, function() {
				if (currArtId == this.id_file)
					firstSegmentOfCurrentFile = this.first_segment;
			});
			UI.scrollSegment($('#segment-' + firstSegmentOfCurrentFile));
		});
		$("#jobNav .currseg").on('click', function(e) {
			e.preventDefault();

			if (!($('#segment-' + UI.currentSegmentId).length)) {
				$('#outer').empty();
				UI.render({
					firstLoad: false
				});
			} else {
				UI.scrollSegment(UI.currentSegment);
			}
		});
		$("#jobNav .nextfile").on('click', function(e) {
			e.preventDefault();
			if (UI.tempViewPoint === '') { // the user have not used yet the Job Nav
				// go to current file first segment
				currFileFirstSegmentId = $(UI.currentFile).attr('id').split('-')[1];
				$.each(config.firstSegmentOfFiles, function() {
					if (this.id_file == currFileFirstSegmentId)
						firstSegId = this.first_segment;
				});
				UI.scrollSegment($('#segment-' + firstSegId));
				UI.tempViewPoint = $(UI.currentFile).attr('id').split('-')[1];
			}
			$.each(config.firstSegmentOfFiles, function() {
				console.log(this.id_file);
			});
		});

// Search and replace

		$(".searchbox input, .searchbox select").bind('keydown', 'return', function(e) {
			e.preventDefault();
			if ($("#exec-find").attr('disabled') != 'disabled')
				$("#exec-find").click();
		});

		$("#exec-find").click(function(e) {
			e.preventDefault();
			if ($(this).attr('data-func') == 'find') {
				UI.execFind();
			} else {
				if (!UI.goingToNext) {
					UI.goingToNext = true;
					UI.execNext();
				}

			}
		});
		$("#exec-cancel").click(function(e) {
			e.preventDefault();
			$("#filterSwitch").click();
			UI.body.removeClass('searchActive');
			UI.clearSearchMarkers();
			UI.clearSearchFields();
			UI.setFindFunction('find');
			$('#exec-find').removeAttr('disabled');
			$('#exec-replace, #exec-replaceall').attr('disabled', 'disabled');
			UI.enableTagMark();
			if (UI.segmentIsLoaded(UI.currentSegmentId)) {
				UI.gotoOpenSegment();
			} else {
				UI.render({
					firstLoad: false,
					segmentToOpen: UI.currentSegmentId
				});
			}

		});
		$("#exec-replaceall").click(function(e) {
			e.preventDefault();
			APP.confirm({
				name: 'confirmReplaceAll',
				cancelTxt: 'Cancel',
				callback: 'execReplaceAll',
				okTxt: 'Continue',
				msg: "Do you really want to replace this text in all search results? <br>(The page will be refreshed after confirm)"
			});
		});
		$("#exec-replace").click(function(e) {
			e.preventDefault();
			if ($('#search-target').val() == $('#replace-target').val()) {
				APP.alert({msg: 'Attention: you are replacing the same text!'});
				return false;
			}

			if (UI.searchMode == 'onlyStatus') {
				
			} else if (UI.searchMode == 'source&target') {

			} else {
				txt = $('#replace-target').val();
				// todo: rifai il marksearchresults sul target

				$("mark.currSearchItem").text(txt);
				segment = $("mark.currSearchItem").parents('section');
				UI.setTranslation(segment, UI.getStatus(segment), 'replace');
				UI.updateSearchDisplayCount(segment);
				$(segment).attr('data-searchItems', $('mark.searchMarker', segment).length);

				UI.gotoNextResultItem(true);
			}
		});
		$("#enable-replace").on('change', function(e) {
			if (($('#enable-replace').is(':checked')) && ($('#search-target').val() !== '')) {
				$('#replace-target, #exec-replace, #exec-replaceall').removeAttr('disabled');
			} else {
				$('#replace-target, #exec-replace, #exec-replaceall').attr('disabled', 'disabled');
			}
		});
		$("#search-source, #search-target").on('input', function(e) {
			if (UI.checkSearchChanges()) {
				UI.setFindFunction('find');
			}
		});
		$("#search-target").on('input', function(e) {
			if ($(this).val() === '') {
				$('#replace-target, #exec-replace, #exec-replaceall').attr('disabled', 'disabled');
			} else {
				if ($('#enable-replace').is(':checked'))
					$('#replace-target, #exec-replace, #exec-replaceall').removeAttr('disabled');
			}
		});
		$("#select-status").on('change', function(e) {
			if (UI.checkSearchChanges()) {
				UI.setFindFunction('find');
			}
		});
		$("#match-case, #exact-match").on('change', function(e) {
			UI.setFindFunction('find');
		});
		this.initEnd = new Date();
		this.initTime = this.initEnd - this.initStart;
		if (this.debug)
			console.log('Init time: ' + this.initTime);
		
	}
});



/*
	Component: ui.contribution
 */
$.extend(UI, {
	chooseSuggestion: function(w) {
		this.copySuggestionInEditarea(this.currentSegment, $('.editor ul[data-item=' + w + '] li.b .translation').text(), $('.editor .editarea'), $('.editor ul[data-item=' + w + '] ul.graysmall-details .percent').text(), false, false, w);
		this.lockTags(this.editarea);
		this.setChosenSuggestion(w);

		this.editarea.focus().effect("highlight", {}, 1000);
	},
	copySuggestionInEditarea: function(segment, translation, editarea, match, decode, auto, which) {

		if (typeof (decode) == "undefined") {
			decode = false;
		}
		percentageClass = this.getPercentuageClass(match);

		if ($.trim(translation) !== '') {

			//ANTONIO 20121205 editarea.text(translation).addClass('fromSuggestion');

			if (decode) {
				translation = htmlDecode(translation);
			}
			if (this.body.hasClass('searchActive'))
				this.addWarningToSearchDisplay();

			this.saveInUndoStack('copysuggestion');
			$(editarea).text(translation).addClass('fromSuggestion');
			this.saveInUndoStack('copysuggestion');
			$('.percentuage', segment).text(match).removeClass('per-orange per-green per-blue per-yellow').addClass(percentageClass).addClass('visible');
			if (which)
				this.currentSegment.addClass('modified');
		}

		// a value of 0 for 'which' means the choice has been made by the
		// program and not by the user

		$(window).trigger({
			type: "suggestionChosen",
			segment: UI.currentSegment,
			element: UI.editarea,
			which: which,
			translation: translation
		});
	},
	getContribution: function(segment, next) {
		var n = (next === 0) ? $(segment) : (next == 1) ? $('#segment-' + this.nextSegmentId) : $('#segment-' + this.nextUntranslatedSegmentId);
		if ($(n).hasClass('loaded')) {
			this.spellCheck();
			if (next) {
				this.nextIsLoaded = true;
			} else {
				this.currentIsLoaded = true;
			}
			if (this.currentIsLoaded)
				this.blockButtons = false;
			if (this.currentSegmentId == this.nextUntranslatedSegmentId)
				this.blockButtons = false;
			if (!next)
				this.currentSegmentQA();
			return false;
		}

		if ((!n.length) && (next)) {
			return false;
		}
		var id = n.attr('id'); 
		var id_segment = id.split('-')[1];
/*
        if( config.brPlaceholdEnabled ) {
            var txt = this.postProcessEditarea(n, '.source');
        } else {
            var txt = $('.source', n).text();
        }
*/
		var txt = $('.source', n).text();
		txt = view2rawxliff(txt);
		// Attention: As for copysource, what is the correct file format in attributes? I am assuming html encoded and "=>&quot;
		//txt = txt.replace(/&quot;/g,'"');

		if (!next) {
			$(".loader", n).addClass('loader_on');
		}

		APP.doRequest({
			data: {
				action: 'getContribution',
				password: config.password,
				is_concordance: 0,
				id_segment: id_segment,
				text: txt,
				id_job: config.job_id,
				num_results: this.numContributionMatchesResults,
				id_translator: config.id_translator
			},
			context: $('#' + id),
			success: function(d) {
				UI.getContribution_success(d, this);
			},
			complete: function(d) {
				UI.getContribution_complete(n);
			}
		});
	},
	getContribution_complete: function(n) {
		$(".loader", n).removeClass('loader_on');
	},
	getContribution_success: function(d, segment) {
//		console.log(d.data.matches);
//		localStorage.setItem($(segment).attr('id').split('-')[1], JSON.stringify(d.data.matches));
//		console.log(localStorage.getItem($(segment).attr('id').split('-')[1]));
//		console.log(localStorage.getItem('4679214'));
//		console.log(!localStorage.getItem('4679214'));
//		console.log(localStorage.getItem('4679215'));
		this.renderContributions(d, segment);
		if ($(segment).attr('id').split('-')[1] == UI.currentSegmentId)
			this.currentSegmentQA();
		this.lockTags(this.editarea);
		this.spellCheck();

		this.saveInUndoStack();

		this.blockButtons = false;
		if (d.data.matches.length > 0) {
			$('.submenu li.matches a span', segment).text('(' + d.data.matches.length + ')');
		} else {
			$(".sbm > .matches", segment).hide();
		}
	},
	renderContributions: function(d, segment) {
		var isActiveSegment = $(segment).hasClass('editor');
		var editarea = $('.editarea', segment);



//        console.log(d.data.matches.length);


		if (d.data.matches.length) {
			var editareaLength = editarea.text().trim().length;
			if (isActiveSegment) {
				editarea.removeClass("indent");
			} else {
				if (editareaLength === 0)
					editarea.addClass("indent");
			}
			var translation = d.data.matches[0].translation;
			var perc_t = $(".percentuage", segment).attr("title");

			$(".percentuage", segment).attr("title", '' + perc_t + "Created by " + d.data.matches[0].created_by);
			var match = d.data.matches[0].match;

			var copySuggestionDone = false;
			if (editareaLength === 0) {
				UI.copySuggestionInEditarea(segment, translation, editarea, match, true, true, 0);
				if (UI.body.hasClass('searchActive'))
					UI.addWarningToSearchDisplay();
				UI.setChosenSuggestion(1);
				copySuggestionDone = true;
			} else {
			}
			var segment_id = segment.attr('id');
			$(segment).addClass('loaded');
			$('.sub-editor.matches .overflow', segment).empty();

			$.each(d.data.matches, function(index) {
				if ((this.segment === '') || (this.translation === ''))
					return;
				var disabled = (this.id == '0') ? true : false;
				cb = this.created_by;

				if ("sentence_confidence" in this &&
						(
								this.sentence_confidence !== "" &&
								this.sentence_confidence !== 0 &&
								this.sentence_confidence != "0" &&
								this.sentence_confidence !== null &&
								this.sentence_confidence !== false &&
								typeof this.sentence_confidence != 'undefined'
								)
						) {
					suggestion_info = "Quality: <b>" + this.sentence_confidence + "</b>";
				} else if (this.match != 'MT') {
					suggestion_info = this.last_update_date;
				} else {
					suggestion_info = '';
				}

				cl_suggestion = UI.getPercentuageClass(this.match);

				if (!$('.sub-editor.matches', segment).length) {
					UI.createFooter(segment);
				}
				// Attention Bug: We are mixing the view mode and the raw data mode.
				// before doing a enanched view you will need to add a data-original tag
                escapedSegment = UI.decodePlaceholdersToText(this.segment);
				$('.sub-editor.matches .overflow', segment).append('<ul class="graysmall" data-item="' + (index + 1) + '" data-id="' + this.id + '"><li class="sugg-source">' + ((disabled) ? '' : ' <a id="' + segment_id + '-tm-' + this.id + '-delete" href="#" class="trash" title="delete this row"></a>') + '<span id="' + segment_id + '-tm-' + this.id + '-source" class="suggestion_source">' + escapedSegment + '</span></li><li class="b sugg-target"><!-- span class="switch-editing">Edit</span --><span class="graysmall-message">' + UI.suggestionShortcutLabel + (index + 1) + '</span><span id="' + segment_id + '-tm-' + this.id + '-translation" class="translation">' + UI.decodePlaceholdersToText( this.translation ) + '</span></li><ul class="graysmall-details"><li class="percent ' + cl_suggestion + '">' + (this.match) + '</li><li>' + suggestion_info + '</li><li class="graydesc">Source: <span class="bold">' + cb + '</span></li></ul></ul>');
			});
			UI.markSuggestionTags(segment);
			UI.setDeleteSuggestion(segment);
			UI.lockTags();
			if (copySuggestionDone) {
				if (isActiveSegment) {
				}
			}

			$('.translated', segment).removeAttr('disabled');
			$('.draft', segment).removeAttr('disabled');
		} else {
			if (UI.debug)
				console.log('no matches');
			$(segment).addClass('loaded');
			$('.sub-editor.matches .overflow', segment).append('<ul class="graysmall message"><li>Sorry. Can\'t help you this time. Check the language pair if you feel this is weird.</li></ul>');
		}
	},
	setContribution: function(segment, status, byStatus) {
		if ((status == 'draft') || (status == 'rejected'))
			return false;

        if( config.brPlaceholdEnabled ) {
            source = this.postProcessEditarea(segment, '.source');
            target = this.postProcessEditarea(segment);
        } else {
            source = $('.source', segment).text();
            // Attention: to be modified when we will be able to lock tags.
            target = $('.editarea', segment).text();
        }

		if ((target === '') && (byStatus)) {
			APP.alert({msg: 'Cannot change status on an empty segment. Add a translation first!'});
		}
		if (target === '') {
			return false;
		}
		this.updateContribution(source, target);
	},
	updateContribution: function(source, target) {
		source = view2rawxliff(source);
		target = view2rawxliff(target);
		APP.doRequest({
			data: {
				action: 'setContribution',
				id_job: config.job_id,
				source: source,
				target: target,
				source_lang: config.source_lang,
				target_lang: config.target_lang,
				password: config.password,
				id_translator: config.id_translator,
				private_translator: config.private_translator,
				id_customer: config.id_customer,
				private_customer: config.private_customer
			},
			success: function(d) {
				if (d.error.length)
					UI.processErrors(d.error, 'setContribution');
			}
		});
	},
	setContributionMT: function(segment, status, byStatus) {
		if ((status == 'draft') || (status == 'rejected'))
			return false;
		var source = $('.source', segment).text();
		source = view2rawxliff(source);
		// Attention: to be modified when we will be able to lock tags.
		var target = $('.editarea', segment).text();
		if ((target === '') && (byStatus)) {
			APP.alert({msg: 'Cannot change status on an empty segment. Add a translation first!'});
		}
		if (target === '') {
			return false;
		}
		target = view2rawxliff(target);
		var languages = $(segment).parents('article').find('.languages');
		var source_lang = $('.source-lang', languages).text();
		var target_lang = $('.target-lang', languages).text();
		var id_translator = config.id_translator;
		var private_translator = config.private_translator;
		var id_customer = config.id_customer;
		var private_customer = config.private_customer;

		var info = $(segment).attr('id').split('-');
		var id_segment = info[1];
		var time_to_edit = UI.editTime;
		var chosen_suggestion = $('.editarea', segment).data('lastChosenSuggestion');

		APP.doRequest({
			data: {
				action: 'setContributionMT',
				id_segment: id_segment,
				source: source,
				target: target,
				source_lang: config.source_lang,
				target_lang: config.target_lang,
				password: config.password,
				time_to_edit: time_to_edit,
				id_job: config.job_id,
				chosen_suggestion_index: chosen_suggestion
			},
			success: function(d) {
				if (d.error.length)
					UI.processErrors(d.error, 'setContributionMT');
			}
		});
	},
	setDeleteSuggestion: function(segment) {
		$('.sub-editor .overflow a.trash', segment).click(function(e) {
			e.preventDefault();
			var ul = $(this).parents('.graysmall');

            if( config.brPlaceholdEnabled ){
                source = UI.postProcessEditarea( ul, '.suggestion_source' );
                target = UI.postProcessEditarea( ul, '.translation' );
            } else {
                source = $('.suggestion_source', ul).text();
                target = $('.translation', ul).text();
            }

            target = view2rawxliff(target);
            source = view2rawxliff(source);
			ul.remove();

			APP.doRequest({
				data: {
					action: 'deleteContribution',
					source_lang: config.source_lang,
					target_lang: config.target_lang,
					id_job: config.job_id,
					password: config.password,
					seg: source,
					tra: target,
					id_translator: config.id_translator
				},
				success: function(d) {
					UI.setDeleteSuggestion_success(d);
				}
			});
		});
	},
	setDeleteSuggestion_success: function(d) {
		if (d.error.length)
			this.processErrors(d.error, 'setDeleteSuggestion');
		if (this.debug)
			console.log('match deleted');

		$(".editor .matches .graysmall").each(function(index) {
			$(this).find('.graysmall-message').text(UI.suggestionShortcutLabel + (index + 1));
			$(this).attr('data-item', index + 1);
			UI.reinitMMShortcuts();
		});
	},
	reinitMMShortcuts: function(a) {
		var keys = (this.isMac) ? 'alt+meta' : 'alt+ctrl';
		$('body').unbind('keydown.alt1').unbind('keydown.alt2').unbind('keydown.alt3').unbind('keydown.alt4').unbind('keydown.alt5');
		$("body, .editarea").bind('keydown.alt1', keys + '+1', function(e) {
			e.preventDefault();
			e.stopPropagation();
//            if (e.which != 97) {
			UI.chooseSuggestion('1');
//            }
		}).bind('keydown.alt2', keys + '+2', function(e) {
			e.preventDefault();
			e.stopPropagation();
//            if (e.which != 98) {
			UI.chooseSuggestion('2');
//            }
		}).bind('keydown.alt3', keys + '+3', function(e) {
			e.preventDefault();
			e.stopPropagation();
//            if (e.which != 99) {
			UI.chooseSuggestion('3');
//            }
		}).bind('keydown.alt4', keys + '+4', function(e) {
			e.preventDefault();
			e.stopPropagation();
//            if (e.which != 100) {
			UI.chooseSuggestion('4');
//            }
		}).bind('keydown.alt5', keys + '+5', function(e) {
			e.preventDefault();
			e.stopPropagation();
//            if (e.which != 101) {
			UI.chooseSuggestion('5');
//            }
		}); 
	},
	setChosenSuggestion: function(w) {
		this.editarea.data('lastChosenSuggestion', w);
	},
});


/*
	Component: ui.tags
 */
$.extend(UI, {
	noTagsInSegment: function(starting) {
		if ((!this.editarea) && (typeof starting == 'undefined'))
			return true;
		if (typeof starting != 'undefined')
			return false;

		var a = $('.source', this.currentSegment).html().match(/\&lt;.*?\&gt;/gi);
		var b = this.editarea.html().match(/\&lt;.*?\&gt;/gi);
		if (a || b) {
			return false;
		} else {
			return true;
		}
	},
	tagCompare: function(sourceTags, targetTags, prova) {

// removed, to be verified
//		if(!UI.currentSegment.hasClass('loaded')) return false;

		var mismatch = false;
		for (var i = 0; i < sourceTags.length; i++) {
			for (var index = 0; index < targetTags.length; index++) {
				if (sourceTags[i] == targetTags[index]) {
					sourceTags.splice(i, 1);
					targetTags.splice(index, 1);
					UI.tagCompare(sourceTags, targetTags, prova++);
				}
			}
		}
		if ((!sourceTags.length) && (!targetTags.length)) {
			mismatch = false;
		} else {
			mismatch = true;
		}
		return(mismatch);
	},
	
	// TAG MARK
	detectTags: function(area) {
		
        //ALL in one
        $(area).html($(area).html().replace(/(:?<span.*?>)?(&lt;\s*\/*\s*(g|x|bx|ex|bpt|ept|ph[^a-z]|it|mrk)\s*.*?&gt;)(:?<\/span>)?/gi, "<span contenteditable=\"false\" class=\"locked\">$2</span>"));
//        $(area).html($(area).html().replace(/(:?<span.*?>)?(&lt;\s*\/*\s*(g|x|bx|ex|bpt|ept|ph[^a-z]|it|mrk)\s*.*?&gt;)(:?<\/span>)?/gi, "<span contenteditable=\"true\" class=\"locked\">$2</span>"));

//		$(area).html($(area).html().replace(/(&lt;\s*\/*\s*(g|x|bx|ex|bpt|ept|ph|it|mrk)\s*.*?&gt;)/gi, "<span contenteditable=\"false\" class=\"locked\">$1</span>"));
//      if (!this.firstMarking) {
//			$(area).html($(area).html().replace(/(<span contenteditable=\"false\" class=\".*?locked.*?\"\>){2,}(.*?)(<\/span\>){2,}/gi, "<span contenteditable=\"false\" class=\"locked\">$2</span>"));
//          console.log( $(area).html() );
//		}
	},
	disableTagMark: function() {
		this.taglockEnabled = false;
		this.body.addClass('tagmarkDisabled');
		$('.source span.locked').each(function(index) {
			$(this).replaceWith($(this).html());
		});
		$('.editarea span.locked').each(function(index) {
			$(this).replaceWith($(this).html());
		});
	},
	enableTagMark: function() {//console.log('enable tag mark');
		this.taglockEnabled = true;
		this.body.removeClass('tagmarkDisabled');
		saveSelection();
		this.markTags();
		restoreSelection();
	},
	markSuggestionTags: function(segment) {
		if (!this.taglockEnabled)
			return false;
		$('.footer .suggestion_source', segment).each(function() {
			$(this).html($(this).html().replace(/(&lt;(g|x|bx|ex|bpt|ept|ph|it|mrk)\sid.*?&gt;)/gi, "<span contenteditable=\"false\" class=\"locked\">$1</span>"));
			if (UI.isFirefox) {
				$(this).html($(this).html().replace(/(<span class=\"(.*?locked.*?)\" contenteditable=\"false\"\>)(<span class=\"(.*?locked.*?)\" contenteditable=\"false\"\>)(.*?)(<\/span\>){2,}/gi, "$1$5</span>"));
			} else {
				$(this).html($(this).html().replace(/(<span contenteditable=\"false\" class=\"(.*?locked.*?)\"\>)(<span contenteditable=\"false\" class=\"(.*?locked.*?)\"\>)(.*?)(<\/span\>){2,}/gi, "$1$5</span>"));
			}
		});
		$('.footer .translation').each(function() {
			$(this).html($(this).html().replace(/(&lt;(g|x|bx|ex|bpt|ept|ph|it|mrk)\sid.*?&gt;)/gi, "<span contenteditable=\"false\" class=\"locked\">$1</span>"));
			if (UI.isFirefox) {
				$(this).html($(this).html().replace(/(<span class=\"(.*?locked.*?)\" contenteditable=\"false\"\>)(<span class=\"(.*?locked.*?)\" contenteditable=\"false\"\>)(.*?)(<\/span\>){2,}/gi, "$1$5</span>"));
			} else {
				$(this).html($(this).html().replace(/(<span contenteditable=\"false\" class=\"(.*?locked.*?)\"\>)(<span contenteditable=\"false\" class=\"(.*?locked.*?)\"\>)(.*?)(<\/span\>){2,}/gi, "$1$5</span>"));
			}
		});
	},
	markTags: function() {
		if (!this.taglockEnabled) return false;
//		UI.checkHeaviness(); 
		
		if (this.noTagsInSegment(1))
			return false;
		$('.source').each(function() {
			UI.detectTags(this);
		});

		$('.editarea').each(function() {
			if ($('#segment-' + $(this).data('sid')).hasClass('mismatch'))
				return false;
			UI.detectTags(this);
		});
	},
	markTagsInSearch: function(el) {
		if (!this.taglockEnabled)
			return false;
		var elements = (typeof el == 'undefined') ? $('.editor .cc-search .input') : el;
		elements.each(function() {
//			UI.detectTags(this);
		});
	},

	// TAG LOCK
	lockTags: function(el) {
//		console.log('lock tags');
		if (this.body.hasClass('tagmarkDisabled'))
			return false;
		editarea = (typeof el == 'undefined') ? UI.editarea : el;
		if (!this.taglockEnabled)
			return false;
		if (this.noTagsInSegment())
			return false;
		$(editarea).first().each(function(index) {
			saveSelection();
			var tx = $(this).html();
			brTx1 = (UI.isFirefox)? "<pl class=\"locked\" contenteditable=\"false\">$1</pl>" : "<pl contenteditable=\"false\" class=\"locked\">$1</pl>";
			brTx2 = (UI.isFirefox)? "<span class=\"locked\" contenteditable=\"false\">$1</span>" : "<span contenteditable=\"false\" class=\"locked\">$1</span>";			
//			brTx1 = (UI.isFirefox)? "<pl class=\"locked\" contenteditable=\"true\">$1</pl>" : "<pl contenteditable=\"true\" class=\"locked\">$1</pl>";
//			brTx2 = (UI.isFirefox)? "<span class=\"locked\" contenteditable=\"true\">$1</span>" : "<span contenteditable=\"true\" class=\"locked\">$1</span>";

            tx = tx.replace(/<span/gi, "<pl")
                    .replace(/<\/span/gi, "</pl")
                    .replace(/&lt;/gi, "<")
                    .replace(/(<(g|x|bx|ex|bpt|ept|ph[^a-z]*|it|mrk)\sid[^<]*?&gt;)/gi, brTx1)
                    .replace(/</gi, "&lt;")
                    .replace(/\&lt;pl/gi, "<span")
                    .replace(/\&lt;\/pl/gi, "</span")
                    .replace(/\&lt;div\>/gi, "<div>")
                    .replace(/\&lt;\/div\>/gi, "</div>")
                    .replace(/\&lt;br\>/gi, "<br>")
                    .replace(/\&lt;br class=["\'](.*?)["\'][\s]*[\/]*(\&gt;|\>)/gi, '<br class="$1" />')
                    .replace(/(&lt;\s*\/\s*(g|x|bx|ex|bpt|ept|ph|it|mrk)\s*&gt;)/gi, brTx2);

            if (UI.isFirefox) {
                tx = tx.replace(/(<span class="[^"]*" contenteditable="false"\>)(:?<span class="[^"]*" contenteditable="false"\>)(.*?)(<\/span\>){2}/gi, "$1$3</span>");
//                tx = tx.replace(/(<span class="[^"]*" contenteditable="true"\>)(:?<span class="[^"]*" contenteditable="true"\>)(.*?)(<\/span\>){2}/gi, "$1$3</span>");
            } else {
                tx = tx.replace(/(<span contenteditable="false" class="[^"]*"\>)(:?<span contenteditable="false" class="[^"]*"\>)(.*?)(<\/span\>){2}/gi, "$1$3</span>");
//                tx = tx.replace(/(<span contenteditable="true" class="[^"]*"\>)(:?<span contenteditable="true" class="[^"]*"\>)(.*?)(<\/span\>){2}/gi, "$1$3</span>");
            }

//			if (UI.isFirefox) {
//				tx = tx.replace(/(<span class=\"(.*?locked.*?)\" contenteditable=\"false\"\>)(<span class=\"(.*?locked.*?)\" contenteditable=\"false\"\>)(.*?)(<\/span\>){2,}/gi, "$1$5</span>");
//				tx = tx.replace(/(<span class=\"(.*?locked.*?)\" contenteditable=\"false\"\>){2,}(.*?)(<\/span\>){2,}/gi, "<span class=\"$2\" contenteditable=\"false\">$3</span>");
//			} else {
//				// fix nested encapsulation
//				tx = tx.replace(/(<span contenteditable=\"true\" class=\"(.*?locked.*?)\"\>)(<span contenteditable=\"true\" class=\"(.*?locked.*?)\"\>)(.*?)(<\/span\>){2,}/gi, "$1$5</span>");
//				tx = tx.replace(/(<span contenteditable=\"true\" class=\"(.*?locked.*?)\"\>){2,}(.*?)(<\/span\>){2,}/gi, "<span contenteditable=\"true\" class=\"$2\">$3</span>");
//			}

			tx = tx.replace(/(<\/span\>)$(\s){0,}/gi, "</span> ");
			tx = tx.replace(/(<\/span\>\s)$/gi, "</span><br class=\"end\">");
			var prevNumTags = $('span.locked', this).length;
			$(this).html(tx);
			restoreSelection();

			if($('span.locked', this).length != prevNumTags) UI.closeTagAutocompletePanel();
		});

	},
	unlockTags: function() {
		if (!this.taglockEnabled)
			return false;
		this.editarea.html(this.editarea.html().replace(/<span contenteditable=\"false\" class=\"locked\"\>(.*?)<\/span\>/gi, "$1"));
//		this.editarea.html(this.editarea.html().replace(/<span contenteditable=\"true\" class=\"locked\"\>(.*?)<\/span\>/gi, "$1"));
	},
	
	// TAG CLEANING
	cleanDroppedTag: function(area, beforeDropHTML) {console.log('clean');

		if (area == this.editarea) {
			this.droppingInEditarea = false;

			var diff = this.dmp.diff_main(beforeDropHTML, $(area).html());
			var draggedText = '';
			$(diff).each(function() {
				if (this[0] == 1) {
					draggedText += this[1];
				}
			});
			draggedText = draggedText.replace(/^(\&nbsp;)(.*?)(\&nbsp;)$/gi, "$2");
			dr2 = draggedText.replace(/(<br>)$/, '').replace(/(<span.*?>)\&nbsp;/,'$1');
//			dr2 = draggedText.replace(/(<span.*?>)\&nbsp;/,'$1');
			area.html(area.html().replace(draggedText, dr2));
			console.log('111: ', UI.editarea.html());

			var div = document.createElement("div");
			div.innerHTML = draggedText;
			console.log('div html: ', $(div).html());
			console.log('dragged text: ', draggedText);
			var isMarkup = draggedText.match(/^<span style=\"font\-size\: 13px/gi);
			saveSelection();

			$('.rangySelectionBoundary', area).last().remove();
			if($('span .rangySelectionBoundary', area).length) {
				var spel = $('span', area).has('.rangySelectionBoundary');
				var rsb = $('span .rangySelectionBoundary', area).detach();
				spel.after(rsb);
			}
			var phcode = $('.rangySelectionBoundary').last().outerHTML;
			console.log('phcode: ', phcode);
			$('.rangySelectionBoundary').text(this.cursorPlaceholder);

	//		closeTag = '</' + $(div).text().trim().replace(/<(.*?)\s.*?\>/gi, "$1") + '>';
			newTag = $(div).text();

			var newText = area.text().replace(draggedText, newTag);
			console.log('222: ', UI.editarea.html());
			console.log(newText);
			if(isMarkup) {
				console.log('IS MARKUP!!!');
				area.text(newText);
			}
			console.log('333: ', UI.editarea.html());
			if(typeof phcode == 'undefined') phcode = '';
			console.log('phcode 1: ', phcode);
			if(isMarkup) area.html(area.html().replace(this.cursorPlaceholder, phcode));
			console.log('444: ', UI.editarea.html());
			restoreSelection();
			if(isMarkup) area.html(area.html().replace(this.cursorPlaceholder, ''));			

			
		} else {
	// old cleaning code to be evaluated
			var diff = this.dmp.diff_main(beforeDropHTML, $(area).html());
			var draggedText = '';
			$(diff).each(function() {
				if (this[0] == 1) {
					draggedText += this[1];
				}
			});
			draggedText = draggedText.replace(/^(\&nbsp;)(.*?)(\&nbsp;)$/gi, "$2").replace(/(<br>)$/gi, '');
			var div = document.createElement("div");
			div.innerHTML = draggedText;
			saveSelection();
			$('.rangySelectionBoundary', area).last().remove();
			if($('span .rangySelectionBoundary', area).length) {
				var spel = $('span', area).has('.rangySelectionBoundary');
				var rsb = $('span .rangySelectionBoundary', area).detach();
				spel.after(rsb);
			}
			var phcode = $('.rangySelectionBoundary')[0].outerHTML;
			$('.rangySelectionBoundary').text(this.cursorPlaceholder);

	//		closeTag = '</' + $(div).text().trim().replace(/<(.*?)\s.*?\>/gi, "$1") + '>';
			newTag = $(div).text();

			var newText = area.text().replace(draggedText, newTag);
			area.text(newText);
			area.html(area.html().replace(this.cursorPlaceholder, phcode));
			restoreSelection();
			area.html(area.html().replace(this.cursorPlaceholder, ''));			
		}
	},
	
	// TAG MISMATCH
	markTagMismatch: function(d) {
		if(typeof d.tag_mismatch.source != 'undefined') {
			$.each(d.tag_mismatch.source, function(index) {
				$('#segment-' + d.id_segment + ' .source span.locked:not(.temp)').filter(function() {
					return $(this).text() === d.tag_mismatch.source[index];
				}).last().addClass('temp');							
			});			
		}
		if(typeof d.tag_mismatch.target != 'undefined') {
			$.each(d.tag_mismatch.target, function(index) {
				$('#segment-' + d.id_segment + ' .editarea span.locked:not(.temp)').filter(function() {
					return $(this).text() === d.tag_mismatch.target[index];
				}).last().addClass('temp');							
			});			
		}

		$('#segment-' + d.id_segment + ' span.locked.mismatch').addClass('mismatch-old').removeClass('mismatch');
		$('#segment-' + d.id_segment + ' span.locked.temp').addClass('mismatch').removeClass('temp');
		$('#segment-' + d.id_segment + ' span.locked.mismatch-old').removeClass('mismatch-old');
	},	

	// TAG AUTOCOMPLETE
	checkAutocompleteTags: function() {
		added = this.getPartialTagAutocomplete();
//		console.log('added: "', added + '"');
		$('.tag-autocomplete li.hidden').removeClass('hidden');
		$('.tag-autocomplete li').each(function() {
			var str = $(this).text();
			if( str.substring(0, added.length) === added ) {
				$(this).removeClass('hidden');
			} else {
				$(this).addClass('hidden');	
			}
		});
		if(!$('.tag-autocomplete li:not(.hidden)').length) {
			$('.tag-autocomplete').addClass('empty');
			if(UI.preCloseTagAutocomplete) {
				UI.closeTagAutocompletePanel();
				return false;				
			}
			UI.preCloseTagAutocomplete = true;
		} else {
			$('.tag-autocomplete li.current').removeClass('current');
			$('.tag-autocomplete li:not(.hidden)').first().addClass('current');
			$('.tag-autocomplete').removeClass('empty');		
			UI.preCloseTagAutocomplete = false;
		}
	},
	closeTagAutocompletePanel: function() {
		$('.tag-autocomplete, .tag-autocomplete-endcursor').remove();
		UI.preCloseTagAutocomplete = false;
	},
	getPartialTagAutocomplete: function() {
//		var added = UI.editarea.html().match(/&lt;([&;"\w\s\/=]*?)<span class="tag-autocomplete-endcursor">/gi);
		var added = UI.editarea.html().match(/&lt;(?:[a-z]*(?:&nbsp;)*["\w\s\/=]*)?<span class="tag-autocomplete-endcursor">/gi);
//		console.log(added);
		added = (added === null)? '' : htmlDecode(added[0].replace(/<span class="tag-autocomplete-endcursor"\>/gi, '')).replace(/\xA0/gi," ");
		return added;
	},
	openTagAutocompletePanel: function() {
		if(!UI.sourceTags.length) return false;
		$('.tag-autocomplete-marker').remove();

		var node = document.createElement("span");
		node.setAttribute('class', 'tag-autocomplete-marker');
		insertNodeAtCursor(node);
		var endCursor = document.createElement("span");
		endCursor.setAttribute('class', 'tag-autocomplete-endcursor');
		insertNodeAtCursor(endCursor);
		var offset = $('.tag-autocomplete-marker').offset();
		var addition = ($(':first-child', UI.editarea).hasClass('tag-autocomplete-endcursor'))? 30 : 20;
		$('.tag-autocomplete-marker').remove();
		UI.body.append('<div class="tag-autocomplete"><ul></ul></div>');
		var arrayUnique = function(a) {
			return a.reduce(function(p, c) {
				if (p.indexOf(c) < 0) p.push(c);
				return p;
			}, []);
		};
		UI.sourceTags = arrayUnique(UI.sourceTags);
		$.each(UI.sourceTags, function(index) {
			$('.tag-autocomplete ul').append('<li' + ((index === 0)? ' class="current"' : '') + '>' + this + '</li>');
		});

		$('.tag-autocomplete').css('top', offset.top + addition);
		$('.tag-autocomplete').css('left', offset.left);
		this.checkAutocompleteTags();	
	},
/*
// functions to handle the chrome bug on contenteditable false elements
	jumpTag: function(pos) {
		pos = pos || 'start';
		setTimeout(function() {
			saveSelection(pos);
			parentTag = $('span.locked', UI.editarea).has(' .rangySelectionBoundary');
			isInsideTag = $('span.locked .rangySelectionBoundary', UI.editarea).length;
			restoreSelection();
			if(isInsideTag) {
				setCursorPosition(parentTag[0], pos);
			}
		}, 50);		
	},

	movePHOutOfTags: function(where) {
		if($('span.locked .rangySelectionBoundary', this.editarea).length) {
			ph = $('span.locked .rangySelectionBoundary', this.editarea);
			if(where == 'start') {
				$('span.locked', this.editarea).has('.rangySelectionBoundary').before(ph[0].outerHTML);
			} else {
				$('span.locked', this.editarea).has('.rangySelectionBoundary').after(ph[0].outerHTML);
			}
			ph.remove();
		}
	},
*/
});



/*
	Component: ui.concordance
 */
$.extend(UI, {
	getConcordance: function(txt, in_target) {
		$('.cc-search', UI.currentSegment).addClass('loading');
		$('.sub-editor.concordances .overflow .results', this.currentSegment).empty();
		txt = view2rawxliff(txt);
		APP.doRequest({
			data: {
				action: 'getContribution',
				is_concordance: 1,
				from_target: in_target,
				id_segment: UI.currentSegmentId,
				text: txt,
				id_job: config.job_id,
				num_results: UI.numMatchesResults,
				id_translator: config.id_translator,
				password: config.password
			},
			success: function(d) {
				UI.renderConcordances(d, in_target);
			}
		});
	},
	openConcordance: function() {
		this.closeContextMenu();
		$('.editor .submenu .tab-switcher-cc a').click();
		$('.editor .cc-search .input').text('');
		$('.editor .concordances .results').empty();
		var searchField = (this.currentSearchInTarget) ? $('.editor .cc-search .search-target') : $('.editor .cc-search .search-source');
		$(searchField).text(this.currentSelectedText);
//		this.markTagsInSearch();

		this.getConcordance(this.currentSelectedText, this.currentSearchInTarget);
	},
	preOpenConcordance: function() {
		var selection = window.getSelection();
		if (selection.type == 'Range') { // something is selected
			var isSource = $(selection.baseNode.parentElement).hasClass('source');
			var str = selection.toString().trim();
			if (str.length) { // the trimmed string is not empty
				this.currentSelectedText = str;
				this.currentSearchInTarget = (isSource) ? 0 : 1;
//                this.currentSearchInTarget = ($(this).hasClass('source'))? 0 : 1;
				this.openConcordance();
			}
		}
	},	
	renderConcordances: function(d, in_target) {
		segment = this.currentSegment;
		segment_id = this.currentSegmentId;
		$('.sub-editor.concordances .overflow .results', segment).empty();
		$('.sub-editor.concordances .overflow .message', segment).remove();
		if (d.data.matches.length) {
			$.each(d.data.matches, function(index) {
				if ((this.segment === '') || (this.translation === ''))
					return;
				var disabled = (this.id == '0') ? true : false;
				cb = this.created_by;
				cl_suggestion = UI.getPercentuageClass(this.match);
				var leftTxt = (in_target) ? this.translation : this.segment;
				leftTxt = leftTxt.replace(/\#\{/gi, "<mark>");
				leftTxt = leftTxt.replace(/\}\#/gi, "</mark>");
				var rightTxt = (in_target) ? this.segment : this.translation;
				rightTxt = rightTxt.replace(/\#\{/gi, "<mark>");
				rightTxt = rightTxt.replace(/\}\#/gi, "</mark>");
				$('.sub-editor.concordances .overflow .results', segment).append('<ul class="graysmall" data-item="' + (index + 1) + '" data-id="' + this.id + '"><li class="sugg-source">' + ((disabled) ? '' : ' <a id="' + segment_id + '-tm-' + this.id + '-delete" href="#" class="trash" title="delete this row"></a>') + '<span id="' + segment_id + '-tm-' + this.id + '-source" class="suggestion_source">' + leftTxt + '</span></li><li class="b sugg-target"><!-- span class="switch-editing">Edit</span --><span id="' + segment_id + '-tm-' + this.id + '-translation" class="translation">' + rightTxt + '</span></li><ul class="graysmall-details"><!-- li class="percent ' + cl_suggestion + '">' + (this.match) + '</li --><li>' + this.last_update_date + '</li><li class="graydesc">Source: <span class="bold">' + cb + '</span></li></ul></ul>');
			});
		} else {
			console.log('no matches');
			$('.sub-editor.concordances .overflow', segment).append('<ul class="graysmall message"><li>Sorry. Can\'t help you this time. Check the language pair if you feel this is weird.</li></ul>');
		}

		$('.cc-search', this.currentSegment).removeClass('loading');
		this.setDeleteSuggestion(segment);
	},
	markTagsInSearch: function(el) {
		if (!this.taglockEnabled)
			return false;
		var elements = (typeof el == 'undefined') ? $('.editor .cc-search .input') : el;
		elements.each(function() {
//			UI.detectTags(this);
		});
	},
});



/*
	Component: ui.glossary
 */
$.extend(UI, {
	deleteGlossaryItem: function(item) {
		APP.doRequest({
			data: {
				action: 'glossary',
				exec: 'delete',
				segment: item.find('.suggestion_source').text(),
				translation: item.find('.translation').text(),
				id_job: config.job_id,
				password: config.password
			},
			success: function(d) {
			}
		});
		dad = $(item).prevAll('.glossary-item').first();
		$(item).remove();
//		console.log($(dad).next().length);
		if(($(dad).next().hasClass('glossary-item'))||(!$(dad).next().length)) {
			$(dad).remove();
			numLabel = $('.tab-switcher-gl a .number', UI.currentSegment);
			num = parseInt(numLabel.attr('data-num')) - 1;
//			console.log(num);
			if(num) {
//				console.log('ne rimangono');
				$(numLabel).text('(' + num + ')').attr('data-num', num);
			} else {
//				console.log('finiti');
				$(numLabel).text('').attr('data-num', 0);	
			}					
		}
	},
	getGlossary: function(segment, entireSegment, next) {
		if (typeof next != 'undefined') {
			if(entireSegment) {
				n = (next === 0) ? $(segment) : (next == 1) ? $('#segment-' + this.nextSegmentId) : $('#segment-' + this.nextUntranslatedSegmentId);
			}
		} else {
			n = segment;
		}
		if(($(n).hasClass('glossary-loaded'))&&(entireSegment)) return false;
		$(n).addClass('glossary-loaded');
		$('.gl-search', n).addClass('loading');
		$('.sub-editor.glossary .overflow .results', n).empty();
		$('.sub-editor.glossary .overflow .graysmall.message', n).empty();
		txt = (entireSegment)? $('.text .source', n).attr('data-original') : view2rawxliff($('.gl-search .search-source', n).text());

		APP.doRequest({
			data: {
				action: 'glossary',
				exec: 'get',
				segment: txt,
				automatic: entireSegment,
				translation: null,
				id_job: config.job_id,
				password: config.password
			},
			context: [n, next],
			success: function(d) {
				if(typeof d.errors != 'undefined') {
					if(d.errors[0].code == -1) {
						UI.noGlossary = true;
//						UI.body.addClass('noGlossary');
					}
				}
				UI.processLoadedGlossary(d, this);
			},
			complete: function() {
				$('.gl-search', UI.currentSegment).removeClass('loading');
			}
		});
	},
	processLoadedGlossary: function(d, context) {
		segment = context[0];
		next = context[1];
		if((next == 1)||(next == 2)) { // is a prefetching
			if(!$('.footer .submenu', segment).length) { // footer has not yet been created
				setTimeout(function() { // wait for creation
					UI.processLoadedGlossary(d, context);
				}, 200);	
			}
		}
		numMatches = Object.size(d.data.matches);
		if(numMatches) {
			UI.renderGlossary(d, segment);
			$('.tab-switcher-gl a .number', segment).text('(' + numMatches + ')').attr('data-num', numMatches);
		} else {
			$('.tab-switcher-gl a .number', segment).text('').attr('data-num', 0);	
		}		
	},
	renderGlossary: function(d, seg) {
		segment = seg;
		segment_id = segment.attr('id');
		$('.sub-editor.glossary .overflow .results', segment).empty();
		$('.sub-editor.glossary .overflow .message', segment).remove();
		numRes = 0;

		if (Object.size(d.data.matches)) {console.log('ci sono match');
			$.each(d.data.matches, function(k, v) {
				numRes++;
				$('.sub-editor.glossary .overflow .results', segment).append('<div class="glossary-item"><span>' + k + '</span></div>');
				$.each(this, function(index) {
					if ((this.segment === '') || (this.translation === ''))
						return;
					var disabled = (this.id == '0') ? true : false;
					cb = this.created_by;
					if(typeof this.target_note == 'undefined'){ this.comment = ''; }
					else { this.comment = this.target_note; }
					cl_suggestion = UI.getPercentuageClass(this.match);
					var leftTxt = this.segment;
					leftTxt = leftTxt.replace(/\#\{/gi, "<mark>");
					leftTxt = leftTxt.replace(/\}\#/gi, "</mark>");
					var rightTxt = this.translation;
					rightTxt = rightTxt.replace(/\#\{/gi, "<mark>");
					rightTxt = rightTxt.replace(/\}\#/gi, "</mark>");
					$('.sub-editor.glossary .overflow .results', segment).append('<ul class="graysmall" data-item="' + (index + 1) + '" data-id="' + this.id + '"><li class="sugg-source">' + ((disabled) ? '' : ' <a id="' + segment_id + '-tm-' + this.id + '-delete" href="#" class="trash" title="delete this row"></a>') + '<span id="' + segment_id + '-tm-' + this.id + '-source" class="suggestion_source">' + leftTxt + '</span></li><li class="b sugg-target"><!-- span class="switch-editing">Edit</span --><span id="' + segment_id + '-tm-' + this.id + '-translation" class="translation">' + rightTxt + '</span></li><li class="details">' + ((this.comment === '')? '' : '<div class="comment">' + this.comment + '</div>') + '<ul class="graysmall-details"><li>' + this.last_update_date + '</li><li class="graydesc">Source: <span class="bold">' + cb + '</span></li></ul></li></ul>');
				});
			});
		} else {
			console.log('no matches');
			$('.sub-editor.glossary .overflow', segment).append('<ul class="graysmall message"><li>Sorry. Can\'t help you this time.</li></ul>');
		}
	},
	setGlossaryItem: function() {
		$('.gl-search', UI.currentSegment).addClass('setting');
		APP.doRequest({
			data: {
				action: 'glossary',
				exec: 'set',
				segment: UI.currentSegment.find('.gl-search .search-source').text(),
				translation: UI.currentSegment.find('.gl-search .search-target').text(),
				comment: UI.currentSegment.find('.gl-search .gl-comment').text(),
				id_job: config.job_id,
				password: config.password
			},
			context: [UI.currentSegment, next],
			success: function(d) {
//				d.data.created_tm_key = '76786732';
				if(d.data.created_tm_key) {
					UI.footerMessage('A Private TM Key has been created for this job', this[0]);
					UI.noGlossary = false;
				} else {
					UI.footerMessage('A glossary item has been added', this[0]);					
				}
				UI.processLoadedGlossary(d, this);
			},
			complete: function() {
				$('.gl-search', UI.currentSegment).removeClass('setting');
			}
		});
	},
});



/*
	Component: ui.search
 */
$.extend(UI, {
	applySearch: function(segment) {
		if (this.body.hasClass('searchActive'))
			this.markSearchResults({
				singleSegment: segment,
				where: 'no'
			});
	},
	resetSearch: function() {console.log('reset search');
		this.body.removeClass('searchActive');
		this.clearSearchMarkers();
		this.setFindFunction('find');
		$('#exec-find').removeAttr('disabled');
		this.enableTagMark();
	},
	execFind: function() {
		this.searchResultsSegments = false;
		$('.search-display').removeClass('displaying');
		$('section.currSearchSegment').removeClass('currSearchSegment');

		if ($('#search-source').val() !== '') {
			this.searchParams.source = $('#search-source').val();
		} else {
			delete this.searchParams.source;
		}

		if ($('#search-target').val() !== '') {
			this.searchParams.target = $('#search-target').val();
			if ($('#enable-replace').is(':checked'))
				$('#replace-target, #exec-replace, #exec-replaceall').removeAttr('disabled');
		} else {
			delete this.searchParams.target;
			$('#replace-target, #exec-replace, #exec-replaceall').attr('disabled', 'disabled');
		}

		if ($('#select-status').val() !== '') {
			this.searchParams.status = $('#select-status').val();
			this.body.attr('data-filter-status', $('#select-status').val());
		} else {
			delete this.searchParams.status;
		}

		if ($('#replace-target').val() !== '') {
			this.searchParams.replace = $('#replace-target').val();
		} else {
			delete this.searchParams.replace;
		}
		this.searchParams['match-case'] = $('#match-case').is(':checked');
		this.searchParams['exact-match'] = $('#exact-match').is(':checked');
		this.searchParams.search = 1;
		if ((typeof this.searchParams.source == 'undefined') && (typeof this.searchParams.target == 'undefined') && (this.searchParams.status == 'all')) {
			APP.alert({msg: 'You must specify at least one between source and target<br>or choose a status'});
			return false;
		}
		this.disableTagMark();

		var p = this.searchParams;

		this.searchMode = ((typeof p.source == 'undefined') && (typeof p.target == 'undefined')) ? 'onlyStatus' :
				((typeof p.source != 'undefined') && (typeof p.target != 'undefined')) ? 'source&target' : 'normal';
		if (this.searchMode == 'onlyStatus') {

//			APP.alert('Status only search is temporarily disabled');
//			return false;
		}
		else if (this.searchMode == 'source&target') {
//			APP.alert('Combined search is temporarily disabled');
//			return false;
		}

		var source = (p.source) ? p.source : '';
		var target = (p.target) ? p.target : '';
		var replace = (p.replace) ? p.replace : '';
		this.markSearchResults();
		this.gotoSearchResultAfter({
			el: 'segment-' + this.currentSegmentId
		});
		this.setFindFunction('next');
		this.body.addClass('searchActive');

		var dd = new Date();
		APP.doRequest({
			data: {
				action: 'getSearch',
				function: 'find',
				job: config.job_id,
				token: dd.getTime(),
				password: config.password,
				source: source,
				target: target,
				status: this.searchParams.status,
				matchcase: this.searchParams['match-case'],
				exactmatch: this.searchParams['exact-match'],
				replace: replace
			},
			success: function(d) {
				UI.execFind_success(d);
			}
		});

	},
	execFind_success: function(d) {
		this.numSearchResultsItem = d.total;
		this.searchResultsSegments = d.segments;
		this.numSearchResultsSegments = (d.segments) ? d.segments.length : 0;
		this.updateSearchDisplay();
		if (this.pendingRender) {
			if (this.pendingRender.detectSegmentToScroll)
				this.pendingRender.segmentToScroll = this.nextUnloadedResultSegment();
			$('#outer').empty();

			this.render(this.pendingRender);
			this.pendingRender = false;
		}
//		console.log(this.editarea.html());
	},
	execReplaceAll: function() {
//		console.log('replace all');
		$('.search-display .numbers').text('No segments found');
		$('.editarea mark.searchMarker').remove();
		this.applySearch();
//		$('.modal[data-name=confirmReplaceAll] .btn-ok').addClass('disabled').text('Replacing...').attr('disabled', 'disabled');

        if ( $('#search-target').val() !== '' ) {
            this.searchParams.target = $('#search-target').val();
        } else {
            APP.alert({msg: 'You must specify the Target value to replace.'});
            delete this.searchParams.target;
            return false;
        }

        if ($('#replace-target').val() !== '') {
            this.searchParams.replace = $('#replace-target').val();
        } else {
            APP.alert({msg: 'You must specify the replacement value.'});
            delete this.searchParams.replace;
            return false;
        }

        if ($('#select-status').val() !== '') {
            this.searchParams.status = $('#select-status').val();
            this.body.attr('data-filter-status', $('#select-status').val());
        } else {
            delete this.searchParams.status;
        }

        this.searchParams['match-case'] = $('#match-case').is(':checked');
        this.searchParams['exact-match'] = $('#exact-match').is(':checked');

        var p = this.searchParams;
		var source = (p.source) ? p.source : '';
		var target = (p.target) ? p.target : '';
		var replace = (p.replace) ? p.replace : '';
		var dd = new Date();

		APP.doRequest({
			data: {
				action: 'getSearch',
				function: 'replaceAll',
				job: config.job_id,
				token: dd.getTime(),
				password: config.password,
				source: source,
				target: target,
				status: p.status,
				matchcase: p['match-case'],
				exactmatch: p['exact-match'],
				replace: replace
			},
			success: function(d) {				
				if(d.error.length) {
					APP.alert({msg: d.error[0].message});
					return false;
				}
				$('#outer').empty();
				UI.render({
					firstLoad: false
				});
			}
		});
	},
	checkSearchStrings: function() {
		s = this.searchParams.source;
		if (s.match(/[<\>]/gi)) { // there is a tag in source
			this.disableTagMark();
		} else {
			this.enableTagMark();
		}
	},
	updateSearchDisplay: function() {
		if ((this.searchMode == 'onlyStatus')) {
			res = (this.numSearchResultsSegments) ? this.numSearchResultsSegments : 0;
			resNumString = (res == 1) ? '' : 's';
			numbers = (res) ? 'Found <span class="segments">...</span> segment' + resNumString : 'No segments found';
			$('.search-display .numbers').html(numbers);
		} else if ((this.searchMode == 'source&target')) {
			res = (this.numSearchResultsSegments) ? this.numSearchResultsSegments : 0;
			resNumString = (res == 1) ? '' : 's';
			numbers = (res) ? 'Found <span class="segments">...</span> segment' + resNumString : 'No segments found';
			$('.search-display .numbers').html(numbers);
		} else {
			res = (this.numSearchResultsItem) ? this.numSearchResultsItem : 0;
			resNumString = (res == 1) ? '' : 's';
			numbers = (res) ? 'Found <span class="results">...</span> result' + resNumString + ' in <span class="segments">...</span> segment' + resNumString : 'No segments found';
			$('.search-display .numbers').html(numbers);
			$('.search-display .results').text(res);
		}
		$('.search-display .segments').text(this.numSearchResultsSegments);

		query = '';
		if (this.searchParams['exact-match'])
			query += ' exactly';
		if (this.searchParams.source)
			query += ' <span class="param">' + this.searchParams.source + '</span> in source';
		if (this.searchParams.target)
			query += ' <span class="param">' + this.searchParams.target + '</span> in target';

		if (this.searchParams.status)
			query += (((this.searchParams.source) || (this.searchParams.target)) ? ' and' : '') + ' status <span class="param">' + this.searchParams.status + '</span>';
		query += ' (' + ((this.searchParams['match-case']) ? 'case sensitive' : 'case insensitive') + ')';
		$('.search-display .query').html(query);
		$('.search-display').addClass('displaying');
		if ((this.searchMode == 'normal') && (this.numSearchResultsItem < 2)) {
			$('#exec-find[data-func=next]').attr('disabled', 'disabled');
		}
		if ((this.searchMode == 'source&target') && (this.numSearchResultsSegments < 2)) {
			$('#exec-find[data-func=next]').attr('disabled', 'disabled');
		}
		this.updateSearchItemsCount();
		if (this.someSegmentToSave()) {
			this.addWarningToSearchDisplay();
		} else {
			this.removeWarningFromSearchDisplay();
		}
	},
	addWarningToSearchDisplay: function() {
		if (!$('.search-display .found .warning').length)
			$('.search-display .found').append('<span class="warning"></span>');
		$('.search-display .found .warning').text(' (maybe some results in segments modified but not saved)');
	},
	removeWarningFromSearchDisplay: function() {
		$('.search-display .found .warning').remove();
	},
	updateSearchDisplayCount: function(segment) {
		numRes = $('.search-display .numbers .results');
		numRes.text(parseInt(numRes.text()) - 1);
		if (($('.editarea mark.searchMarker', segment).length - 1) === 0) {
			numSeg = $('.search-display .numbers .segments');
			numSeg.text(parseInt(numSeg.text()) - 1);
		}
		this.updateSearchItemsCount();
	},
	updateSearchItemsCount: function() {
		c = parseInt($('.search-display .numbers .results').text());
		if (c > 0) {
			$('#filterSwitch .numbererror').text(c).attr('title', $('.search-display .found').text());
		} else {
		}
	},
	execNext: function() {
		this.gotoNextResultItem(false);
	},
	markSearchResults: function(options) { // if where is specified mark only the range of segment before or after seg (no previous clear)		
		options = options || {};
		where = options.where;
		seg = options.seg;
		singleSegment = options.singleSegment || false;
//		console.log('singleSegment: ', singleSegment);
		if (typeof where == 'undefined') {
			this.clearSearchMarkers();
		}
		var p = this.searchParams;
//        console.log('mode: ' + mode + ' - coso: ' + coso);
		var targetToo = typeof p.target != 'undefined';
		var containsFunc = (p['match-case']) ? 'contains' : 'containsNC';
		var ignoreCase = (p['match-case']) ? '' : 'i';

		openTagReg = new RegExp(UI.openTagPlaceholder, "g");
		closeTagReg = new RegExp(UI.closeTagPlaceholder, "g");

		if (this.searchMode == 'onlyStatus') { // search mode: onlyStatus

		} else if (this.searchMode == 'source&target') { // search mode: source&target
			console.log('source & target');
			status = (p.status == 'all') ? '' : '.status-' + p.status;
			q = (singleSegment) ? '#' + $(singleSegment).attr('id') : "section" + status + ':not(.status-new)';
			var regSource = new RegExp('(' + htmlEncode(p.source) + ')', "g" + ignoreCase);
			var regTarget = new RegExp('(' + htmlEncode(p.target) + ')', "g" + ignoreCase);
			txtSrc = p.source;
			txtTrg = p.target;
			srcHasTags = (txtSrc.match(/<.*?\>/gi) !== null) ? true : false;
			trgHasTags = (txtTrg.match(/<.*?\>/gi) !== null) ? true : false;

			if (typeof where == 'undefined') {
				UI.doMarkSearchResults(srcHasTags, $(q + " .source:" + containsFunc + "('" + txtSrc + "')"), regSource, q, txtSrc, ignoreCase);
				UI.doMarkSearchResults(trgHasTags, $(q + " .editarea:" + containsFunc + "('" + txtTrg + "')"), regTarget, q, txtTrg, ignoreCase);
//				UI.execSearchResultsMarking(UI.filterExactMatch($(q + " .source:" + containsFunc + "('" + txtSrc + "')"), txtSrc), regSource, false);
//				UI.execSearchResultsMarking(UI.filterExactMatch($(q + " .editarea:" + containsFunc + "('" + txtTrg + "')"), txtTrg), regTarget, false);
				$('section').has('.source mark.searchPreMarker').has('.editarea mark.searchPreMarker').find('mark.searchPreMarker').addClass('searchMarker').removeClass('searchPreMarker');
//				$('section').has('.source mark.searchPreMarker').has('.editarea mark.searchPreMarker').find('mark.searchPreMarker').addClass('searchMarker');
				$('mark.searchPreMarker:not(.searchMarker)').each(function() {
					var a = $(this).text();
					$(this).replaceWith(a);
				});
			} else {
				sid = $(seg).attr('id');
				if (where == 'before') {
					$('section').each(function(index) {
						if ($(this).attr('id') < sid) {
							$(this).addClass('justAdded');
						}
					});
				} else {
					$('section').each(function(index) {
						if ($(this).attr('id') > sid) {
							$(this).addClass('justAdded');
						}
					});
				}
				UI.execSearchResultsMarking(UI.filterExactMatch($(q + ".justAdded:not(.status-new) .source:" + containsFunc + "('" + txtSrc + "')"), txtSrc), regSource, false);
				UI.execSearchResultsMarking(UI.filterExactMatch($(q + ".justAdded:not(.status-new) .editarea:" + containsFunc + "('" + txtTrg + "')"), txtTrg), regTarget, false);

				$('section').has('.source mark.searchPreMarker').has('.editarea mark.searchPreMarker').find('mark.searchPreMarker').addClass('searchMarker');
				$('mark.searchPreMarker').removeClass('searchPreMarker');
				$('section.justAdded').removeClass('justAdded');
			}
		} else { // search mode: normal
//			console.log('search mode: normal');
			status = (p.status == 'all') ? '' : '.status-' + p.status;
			var txt = (typeof p.source != 'undefined') ? p.source : (typeof p.target != 'undefined') ? p.target : '';
			if (singleSegment) {
				what = (typeof p.source != 'undefined') ? ' .source' : (typeof p.target != 'undefined') ? ' .editarea' : '';
				q = '#' + $(singleSegment).attr('id') + what;
			} else {
				what = (typeof p.source != 'undefined') ? ' .source' : (typeof p.target != 'undefined') ? ':not(.status-new) .editarea' : '';
				q = "section" + status + what;
			}
			hasTags = (txt.match(/<.*?\>/gi) !== null) ? true : false;
			var regTxt = txt.replace('<', UI.openTagPlaceholder).replace('>', UI.closeTagPlaceholder);
			var reg = new RegExp('(' + htmlEncode(regTxt) + ')', "g" + ignoreCase);

			if ((typeof where == 'undefined') || (where == 'no')) {
				UI.doMarkSearchResults(hasTags, $(q + ":" + containsFunc + "('" + txt + "')"), reg, q, txt, ignoreCase);
			} else {
				sid = $(seg).attr('id');
				if (where == 'before') {
					$('section').each(function(index) {
						if ($(this).attr('id') < sid) {
							$(this).addClass('justAdded');
						}
					});
				} else {
					$('section').each(function(index) {
						if ($(this).attr('id') > sid) {
							$(this).addClass('justAdded');
						}
					});
				}
				UI.doMarkSearchResults(hasTags, $("section" + status + ".justAdded" + what + ":" + containsFunc + "('" + txt + "')"), reg, q, txt, ignoreCase);
				$('section.justAdded').removeClass('justAdded');
			}
		}
		if (!singleSegment) {
			UI.unmarkNumItemsInSegments();
			UI.markNumItemsInSegments();
		}
	},
	doMarkSearchResults: function(hasTags, items, regex, q, txt, ignoreCase) {
		if (!hasTags) {
			this.execSearchResultsMarking(UI.filterExactMatch(items, txt), regex, false);
		} else {
			inputReg = new RegExp(txt, "g" + ignoreCase);
			this.execSearchResultsMarking($(q), regex, inputReg);
		}
	},
	execSearchResultsMarking: function(areas, regex, testRegex) {
		searchMarker = (UI.searchMode == 'source&target')? 'searchPreMarker' : 'searchMarker';
		$(areas).each(function() {
			if (!testRegex || ($(this).text().match(testRegex) !== null)) {
				var tt = $(this).html().replace(/&lt;/g, UI.openTagPlaceholder).replace(/&gt;/g, UI.closeTagPlaceholder).replace(regex, '<mark class="' + searchMarker + '">$1</mark>').replace(openTagReg, '&lt;').replace(closeTagReg, '&gt;').replace(/(<span(.*)?>).*?<mark.*?>(.*?)<\/mark>.*?(<\/span>)/gi, "$1$3$4");
				$(this).html(tt);
			}
		});
	},
	filterExactMatch: function(items, txt) {
		return (this.searchParams['exact-match']) ? items.filter(function() {
			if (UI.searchParams['match-case']) {
				return $(this).text() == txt;
			} else {
				return $(this).text().toUpperCase() == txt.toUpperCase();
			}
		}) : items;
	},
	clearSearchFields: function() {
		$('.searchbox form')[0].reset();
	},
	clearSearchMarkers: function() {
		$('mark.searchMarker').each(function() {
			$(this).replaceWith($(this).text());
		});
		$('section.currSearchResultSegment').removeClass('currSearchResultSegment');
	},
	gotoNextResultItem: function(unmark) {
//        if(UI.goingToNext) {
//			console.log('already going to next');
//			return false;
//		}
		var p = this.searchParams;

		if (this.searchMode == 'onlyStatus') {
			console.log('only status');
			var status = (p.status == 'all') ? '' : '.status-' + p.status;
			el = $('section.currSearchSegment');
			if (p.status == 'all') {
				this.scrollSegment($(el).next());
			} else {
				if (el.nextAll(status).length) {
					nextToGo = el.nextAll(status).first();
					$(el).removeClass('currSearchSegment');
					nextToGo.addClass('currSearchSegment');
					this.scrollSegment(nextToGo);
				} else {
					this.pendingRender = {
						firstLoad: false,
						applySearch: true,
						detectSegmentToScroll: true,
						segmentToScroll: this.nextUnloadedResultSegment()
					};
					$('#outer').empty();
					this.render(this.pendingRender);
					this.pendingRender = false;
				}

			}
		} else if (this.searchMode == 'source&target') {

			m = $(".editarea mark.currSearchItem"); // ***
//            console.log($(m).nextAll('mark.searchMarker').length);
			if ($(m).nextAll('mark.searchMarker').length) { // there are other subsequent results in the segment
				console.log('altri item nel segmento');
				$(m).removeClass('currSearchItem');
				$(m).nextAll('mark.searchMarker').first().addClass('currSearchItem');
				if (unmark)
					$(m).replaceWith($(m).text());
				UI.goingToNext = false;
			} else { // jump to results in subsequents segments
				console.log('m.length: ' + m.length);
				seg = (m.length) ? $(m).parents('section') : $('mark.searchMarker').first().parents('section');
				if (seg.length) {
					skipCurrent = $(seg).has("mark.currSearchItem").length;
					this.gotoSearchResultAfter({
						el: 'segment-' + $(seg).attr('id').split('-')[1],
						skipCurrent: skipCurrent,
						unmark: unmark
					});
				} else {//console.log('b');
					setTimeout(function() {
						UI.gotoNextResultItem(false);
					}, 500);
				}
			}


/*
			var seg = $("section.currSearchSegment");
//            var m = $("section.currSearchSegment mark.searchMarker");
//            seg = (m.length)? $(m).parents('section') : $('mark.searchMarker').first().parents('section');
			if (seg.length) {
//                $(seg).removeClass('currSearchSegment');
//                $(m).nextAll('mark.searchMarker').first().addClass('currSearchItem');
				this.gotoSearchResultAfter({
					el: 'segment-' + $(seg).attr('id').split('-')[1]
				});
			}
*/			
		} else {
			m = $("mark.currSearchItem");
//            console.log($(m).nextAll('mark.searchMarker').length);
			if ($(m).nextAll('mark.searchMarker').length) { // there are other subsequent results in the segment
				console.log('altri item nel segmento');
				$(m).removeClass('currSearchItem');
				$(m).nextAll('mark.searchMarker').first().addClass('currSearchItem');
				if (unmark)
					$(m).replaceWith($(m).text());
				UI.goingToNext = false;
			} else { // jump to results in subsequents segments
				seg = (m.length) ? $(m).parents('section') : $('mark.searchMarker').first().parents('section');
				if (seg.length) {//console.log('a');
					skipCurrent = $(seg).has("mark.currSearchItem").length;
					this.gotoSearchResultAfter({
						el: 'segment-' + $(seg).attr('id').split('-')[1],
						skipCurrent: skipCurrent,
						unmark: unmark
					});
				} else {//console.log('b');
					setTimeout(function() {
						UI.gotoNextResultItem(false);
					}, 500);
				}
			}
		}
//        console.log('stop');
//		UI.goingToNext = false;
	},
	gotoSearchResultAfter: function(options) {
		el = options.el;
		skipCurrent = (options.skipCurrent || false);
		unmark = (options.unmark || false);
//		        console.log(UI.goingToNext);

		var p = this.searchParams;
//        console.log($('#' + el + ":has(mark.searchMarker)").length);

		if (this.searchMode == 'onlyStatus') { // searchMode: onlyStatus
			var status = (p.status == 'all') ? '' : '.status-' + p.status;

			if (p.status == 'all') {
				this.scrollSegment($('#' + el).next());
			} else {
//				console.log($('#' + el));
//				console.log($('#' + el).nextAll(status).length);
				if ($('#' + el).nextAll(status).length) { // there is at least one next result loaded after the currently selected
					nextToGo = $('#' + el).nextAll(status).first();
					nextToGo.addClass('currSearchSegment');
					this.scrollSegment(nextToGo);
				} else {
					// load new segments
					if (!this.searchResultsSegments) {
						this.pendingRender = {
							firstLoad: false,
							applySearch: true,
							detectSegmentToScroll: true
						};
					} else {
						seg2scroll = this.nextUnloadedResultSegment();
						$('#outer').empty();
						this.render({
							firstLoad: false,
							applySearch: true,
							segmentToScroll: seg2scroll
						});
					}
				}


			}
		} else { // searchMode: source&target or normal
			var wh = (this.searchMode == 'source&target')? ' .editarea' : '';
			seg = $('section' + wh).has("mark.searchMarker");
			ss = (this.searchMode == 'source&target')? el + '-editarea' : el;
			found = false;
			$.each(seg, function(index) {
				if ($(this).attr('id') >= ss) {
					if (($(this).attr('id') == ss) && (skipCurrent)) {
					} else {
						found = true;
						$("html,body").animate({
							scrollTop: $(this).offset().top - 200
						}, 500);
						setTimeout(function() {
							UI.goingToNext = false;
						}, 500);
						var m = $("mark.currSearchItem");
						$(m).removeClass('currSearchItem');
						$(this).find('mark.searchMarker').first().addClass('currSearchItem');
						if (unmark)
							$(m).replaceWith($(m).text());
						return false;
					}
				}
			});			
			if (!found) {
				// load new segments
				if (!this.searchResultsSegments) {
					this.pendingRender = {
						firstLoad: false,
						applySearch: true,
						detectSegmentToScroll: true
					};
				} else {
					seg2scroll = this.nextUnloadedResultSegment();
					$('#outer').empty();
					this.render({
						firstLoad: false,
						applySearch: true,
						segmentToScroll: seg2scroll
					});
				}
			}
/*
			var status = (p['status'] == 'all') ? '' : '.status-' + p['status'];
			destination = (($('#' + el + ":has(mark.searchMarker)").length) && (!$('#' + el).hasClass('currSearchSegment'))) ? $('#' + el) : $('#' + el).nextAll(status + ":has(mark.searchMarker)").first();
//            destination = $('#'+el).nextAll(status + ":has(mark.searchMarker)").first();            
//            console.log(destination);
			if ($(destination).length) {
				$('section.currSearchSegment').removeClass('currSearchSegment');
				$(destination).addClass('currSearchSegment');
				this.scrollSegment(destination);
			} else {
				// load new segments
				if (!this.searchResultsSegments) {
					this.pendingRender = {
						firstLoad: false,
						applySearch: true,
						detectSegmentToScroll: true
					};
				} else {
					seg2scroll = this.nextUnloadedResultSegment();
					$('#outer').empty();
					this.render({
						firstLoad: false,
						applySearch: true,
						segmentToScroll: seg2scroll
					});
				}
			}
*/

		}
	},
	checkSearchChanges: function() {
		changes = false;
		var p = this.searchParams;
		if (p.source != $('#search-source').val()) {
			if (!((typeof p.source == 'undefined') && ($('#search-source').val() === '')))
				changes = true;
		}
		if (p.target != $('#search-target').val()) {
			if (!((typeof p.target == 'undefined') && ($('#search-target').val() === '')))
				changes = true;
		}
		if (p.status != $('#select-status').val()) {
			if ((typeof p.status != 'undefined'))
				changes = true;
		}
		if (p['match-case'] != $('#match-case').is(':checked')) {
			changes = true;
		}
		if (p['exact-match'] != $('#exact-match').is(':checked')) {
			changes = true;
		}
		return changes;
	},
	setFindFunction: function(func) {
		var b = $('#exec-find');
		if (func == 'next') {
			b.attr('data-func', 'next').attr('value', 'Next');
		} else {
			b.attr('data-func', 'find').attr('value', 'Find');
		}
		b.removeAttr('disabled');
	},
	unmarkNumItemsInSegments: function() {
		$('section[data-searchItems]').removeAttr("data-searchItems");
	},
	markNumItemsInSegments: function() {
		$('section').has("mark.searchMarker").each(function() {
			$(this).attr('data-searchItems', $('mark.searchMarker', this).length);
		});
	},
	toggleSearch: function(e) {
		if (!this.searchEnabled)
			return;
		e.preventDefault();
		if ($('body').hasClass('filterOpen')) {
			$('body').removeClass('filterOpen');
//            $("body").scrollTop($("body").scrollTop()+$('.searchbox').height());
		} else {
			$('body').addClass('filterOpen');
//            $("body").scrollTop($("body").scrollTop()-$('.searchbox').height());
			$('#search-source').focus();
		}
	},
});
/*
	Component: functions 
 */

function htmlEncode(value) {
	if (value) {
		a = jQuery('<div />').text(value).html();
		return a;
	} else {
		return '';
	}
}

function htmlDecode(value) {
	if (value) {
		return $('<div />').html(value).text();
	} else {
		return '';
	}
}

function utf8_to_b64(str) { // currently unused
	return window.btoa(unescape(encodeURIComponent(str)));
}

function b64_to_utf8(str) { // currently unused
	return decodeURIComponent(escape(window.atob(str)));
}


// START Get clipboard data at paste event (SEE http://stackoverflow.com/a/6804718)
function handlepaste(elem, e) {
	var savedcontent = elem.innerHTML;

	if (e && e.clipboardData && e.clipboardData.getData) {// Webkit - get data from clipboard, put into editdiv, cleanup, then cancel event
		if (/text\/html/.test(e.clipboardData.types)) {
			txt = (UI.tagSelection) ? UI.tagSelection : htmlEncode(e.clipboardData.getData('text/plain'));
			elem.innerHTML = txt;
		}
		else if (/text\/plain/.test(e.clipboardData.types)) {
			txt = (UI.tagSelection) ? UI.tagSelection : htmlEncode(e.clipboardData.getData('text/plain'));
			elem.innerHTML = txt;
		}
		else {
			elem.innerHTML = "";
		}
		waitforpastedata(elem, savedcontent);
		if (e.preventDefault) {
			e.stopPropagation();
			e.preventDefault();
		}
		return false;
	}
	else {// Everything else - empty editdiv and allow browser to paste content into it, then cleanup
		elem.innerHTML = "";
		waitforpastedata(elem, savedcontent);
		return true;
	}
}

function waitforpastedata(elem, savedcontent) {

	if (elem.childNodes && elem.childNodes.length > 0) {
		processpaste(elem, savedcontent);
	}
	else {
		that = {
			e: elem,
			s: savedcontent
		};
		that.callself = function() {
			waitforpastedata(that.e, that.s);
		};
		setTimeout(that.callself, 20);
	}
}

function processpaste(elem, savedcontent) {
	pasteddata = elem.innerHTML;

	//^^Alternatively loop through dom (elem.childNodes or elem.getElementsByTagName) here
	elem.innerHTML = savedcontent;
	
	// Do whatever with gathered data;
	$('#placeHolder').before(pasteddata);
	focusOnPlaceholder();
	$('#placeHolder').remove();
}
// END Get clipboard data at paste event

function focusOnPlaceholder() {
	var placeholder = document.getElementById('placeHolder');
	if (!placeholder)
		return;
	var sel, range;

	if (window.getSelection && document.createRange) {
		range = document.createRange();
		range.selectNodeContents(placeholder);
		range.collapse(true);
		sel = window.getSelection();
		sel.removeAllRanges();
		sel.addRange(range);
	} else if (document.body.createTextRange) {
		range = document.body.createTextRange();
		range.moveToElementText(placeholder);
		range.select();
	}
}

function truncate_filename(n, len) {
	var ext = n.substring(n.lastIndexOf(".") + 1, n.length).toLowerCase();
	var filename = n.replace('.' + ext, '');
	if (filename.length <= len) {
		return n;
	}
	filename = filename.substr(0, len) + (n.length > len ? '[...]' : '');
	return filename + '.' + ext;
}

function insertNodeAtCursor(node) {
	var range, html;
	if (window.getSelection && window.getSelection().getRangeAt) {
		if (window.getSelection().type == 'Caret') {
			range = window.getSelection().getRangeAt(0);
			range.insertNode(node);
		} else {
		}

	} else if (document.selection && document.selection.createRange) {
		range = document.selection.createRange();
		html = (node.nodeType == 3) ? node.data : node.outerHTML;
		range.pasteHTML(html);
	}
}

function pasteHtmlAtCaret(html, selectPastedContent) {
    var sel, range;
    if (window.getSelection) {
        // IE9 and non-IE
        sel = window.getSelection();
        if (sel.getRangeAt && sel.rangeCount) {
            range = sel.getRangeAt(0);
            range.deleteContents();

            // Range.createContextualFragment() would be useful here but is
            // only relatively recently standardized and is not supported in
            // some browsers (IE9, for one)
            var el = document.createElement("div");
            el.innerHTML = html;
            var frag = document.createDocumentFragment(), node, lastNode;
            while ( (node = el.firstChild) ) {
                lastNode = frag.appendChild(node);
            }
            var firstNode = frag.firstChild;
            range.insertNode(frag);

            // Preserve the selection
            if (lastNode) {
                range = range.cloneRange();
                range.setStartAfter(lastNode);
                if (selectPastedContent) {
                    range.setStartBefore(firstNode);
                } else {
                    range.collapse(true);
                }
                sel.removeAllRanges();
                sel.addRange(range);
            }
        }
    } else if ( (sel = document.selection) && sel.type != "Control") {
        // IE < 9
        var originalRange = sel.createRange();
        originalRange.collapse(true);
        sel.createRange().pasteHTML(html);
        if (selectPastedContent) {
            range = sel.createRange();
            range.setEndPoint("StartToStart", originalRange);
            range.select();
        }
    }
}

function setCursorPosition(el, pos) {
	pos = pos || 0;
	var range = document.createRange();
	var sel = window.getSelection();
	range.setStart(el, pos);
	if(pos == 'end') range.setStartAfter(el);
	range.collapse(true);
	sel.removeAllRanges();
	sel.addRange(range);
	el.focus();	
}

function removeSelectedText(editarea) {
	if (window.getSelection || document.getSelection) {
		var oSelection = (window.getSelection ? window : document).getSelection();
		if (oSelection.type == 'Caret') {
			if (oSelection.extentOffset != oSelection.baseOffset)
				oSelection.deleteFromDocument();
		} else if (oSelection.type == 'Range') {
			var ss = $(oSelection.baseNode).parent()[0];
			if ($(ss).hasClass('selected')) {
				$(ss).remove();
			} else {
				oSelection.deleteFromDocument();
			}
		}
	} else {
		document.selection.clear();
	}
}




/* FORMATTING FUNCTION  TO TEST */

var LTPLACEHOLDER = "##LESSTHAN##";
var GTPLACEHOLDER = "##GREATERTHAN##";
var re_lt = new RegExp(LTPLACEHOLDER, "g");
var re_gt = new RegExp(GTPLACEHOLDER, "g");
// test jsfiddle http://jsfiddle.net/YgKDu/

function placehold_xliff_tags(segment) {
	segment = segment.replace(/<(g\s*.*?)>/gi, LTPLACEHOLDER + "$1" + GTPLACEHOLDER);
	segment = segment.replace(/<(\/g)>/gi, LTPLACEHOLDER + "$1" + GTPLACEHOLDER);
	segment = segment.replace(/<(x.*?\/?)>/gi, LTPLACEHOLDER + "$1" + GTPLACEHOLDER);
	segment = segment.replace(/<(bx.*?\/?])>/gi, LTPLACEHOLDER + "$1" + GTPLACEHOLDER, segment);
	segment = segment.replace(/<(ex.*?\/?)>/gi, LTPLACEHOLDER + "$1" + GTPLACEHOLDER, segment);
	segment = segment.replace(/<(bpt\s*.*?)>/gi, LTPLACEHOLDER + "$1" + GTPLACEHOLDER, segment);
	segment = segment.replace(/<(\/bpt)>/gi, LTPLACEHOLDER + "$1" + GTPLACEHOLDER, segment);
	segment = segment.replace(/<(ept\s*.*?)>/gi, LTPLACEHOLDER + "$1" + GTPLACEHOLDER, segment);
	segment = segment.replace(/<(\/ept)>/gi, LTPLACEHOLDER + "$1" + GTPLACEHOLDER, segment);
	segment = segment.replace(/<(ph\s*.*?)>/gi, LTPLACEHOLDER + "$1" + GTPLACEHOLDER, segment);
	segment = segment.replace(/<(\/ph)>/gi, LTPLACEHOLDER + "$1" + GTPLACEHOLDER, segment);
	segment = segment.replace(/<(it\s*.*?)>/gi, LTPLACEHOLDER + "$1" + GTPLACEHOLDER, segment);
	segment = segment.replace(/<(\/ph)>/gi, LTPLACEHOLDER + "$1" + GTPLACEHOLDER, segment);
	segment = segment.replace(/<(it\s*.*?)>/gi, LTPLACEHOLDER + "$1" + GTPLACEHOLDER, segment);
	segment = segment.replace(/<(\/it)>/gi, LTPLACEHOLDER + "$1" + GTPLACEHOLDER, segment);
	segment = segment.replace(/<(mrk\s*.*?)>/gi, LTPLACEHOLDER + "$1" + GTPLACEHOLDER, segment);
	segment = segment.replace(/<(\/mrk)>/gi, LTPLACEHOLDER + "$1" + GTPLACEHOLDER, segment);
	return segment;
}

function restore_xliff_tags(segment) {
	segment = segment.replace(re_lt, "<");
	segment = segment.replace(re_gt, ">");
	return segment;
}

function restore_xliff_tags_for_view(segment) {
	segment = segment.replace(re_lt, "&lt;");
	segment = segment.replace(re_gt, "&gt;");
	return segment;
}

function view2rawxliff(segment) {
	// return segment+"____";
	// input : <g id="43">bang & olufsen < 3 </g> <x id="33"/>; --> valore della funzione .text() in cat.js su source, target, source suggestion,target suggestion
	// output : <g id="43"> bang &amp; olufsen are &gt; 555 </g> <x/>

	// caso controverso <g id="4" x="&lt; dfsd &gt;"> 
	//segment=htmlDecode(segment);
	segment = placehold_xliff_tags(segment);
	segment = htmlEncode(segment);

	segment = restore_xliff_tags(segment);

	return segment;
}

function rawxliff2view(segment) { // currently unused
	// input : <g id="43">bang &amp; &lt; 3 olufsen </g>; <x id="33"/>
	// output : &lt;g id="43"&gt;bang & < 3 olufsen &lt;/g&gt;;  &lt;x id="33"/&gt;
	segment = placehold_xliff_tags(segment);
	segment = htmlDecode(segment);
	segment = segment.replace(/<(.*?)>/i, "&lt;$1&gt;");
	segment = restore_xliff_tags_for_view(segment);		// li rendering avviene via concat o via funzione html()
	return segment;
}

function rawxliff2rawview(segment) { // currently unused
	// input : <g id="43">bang &amp; &lt; 3 olufsen </g>; <x id="33"/>
	segment = placehold_xliff_tags(segment);
	segment = htmlDecode(segment);
	segment = restore_xliff_tags_for_view(segment);
	return segment;
}

function saveSelection(pos) {//console.log(pos);
//	var editarea = (typeof editarea == 'undefined') ? UI.editarea : el;
	var editarea = UI.editarea;
	if (UI.savedSel) {
		rangy.removeMarkers(UI.savedSel);
	}
	UI.savedSel = rangy.saveSelection();
	// this is just to prevent the addiction of a couple of placeholders who may sometimes occur for a Rangy bug
	editarea.html(editarea.html().replace(UI.cursorPlaceholder, ''));

	UI.savedSelActiveElement = document.activeElement;
//	if(pos != 'noMove') UI.movePHOutOfTags(pos);
}

function restoreSelection() {
	if (UI.savedSel) {
		rangy.restoreSelection(UI.savedSel, true);
		UI.savedSel = null;
		window.setTimeout(function() {
			if (UI.savedSelActiveElement && typeof UI.savedSelActiveElement.focus != "undefined") {
				UI.savedSelActiveElement.focus();
			}
		}, 1);
	}
}

function selectText(element) { 
	var doc = document, text = element, range, selection;
	if (doc.body.createTextRange) {
		range = document.body.createTextRange();
		range.moveToElementText(text);
		range.select();
	} else if (window.getSelection) {
		selection = window.getSelection();
		range = document.createRange();
		range.selectNodeContents(text);
		selection.removeAllRanges();
		selection.addRange(range);
	}
}

function getSelectionHtml() {
	var html = "";
	if (typeof window.getSelection != "undefined") {
		var sel = window.getSelection();
		if (sel.rangeCount) {
			var container = document.createElement("div");
			for (var i = 0, len = sel.rangeCount; i < len; ++i) {
				container.appendChild(sel.getRangeAt(i).cloneContents());
			}
			html = container.innerHTML;
		}
	} else if (typeof document.selection != "undefined") {
		if (document.selection.type == "Text") {
			html = document.selection.createRange().htmlText;
		}
	}
	return html;
}

function setBrowserHistoryBehavior() {

	window.onpopstate = function(event) {
		segmentId = location.hash.substr(1);
		if (UI.segmentIsLoaded(segmentId)) {
			$(".editarea", $('#segment-' + segmentId)).click();
		} else {
			if ($('section').length)
				UI.pointBackToSegment(segmentId);
		}
	};

}

$.fn.isOnScreen = function() {

	var win = $(window);

	var viewport = {
		top: win.scrollTop(),
		left: win.scrollLeft()
	};
	viewport.right = viewport.left + win.width();
	viewport.bottom = viewport.top + win.height();

	var bounds = this.offset();
	bounds.right = bounds.left + this.outerWidth();
	bounds.bottom = bounds.top + this.outerHeight();


	return (!(viewport.right < bounds.left || viewport.left > bounds.right || viewport.bottom < bounds.top || viewport.top > bounds.bottom));

};

Object.size = function(obj) {
    var size = 0, key;
    for (key in obj) {
        if (obj.hasOwnProperty(key)) size++;
    }
    return size;
};

