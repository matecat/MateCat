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
			error: function() {
				UI.failedConnection(0, 'deleteGlossaryItem');
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
//		console.log('segment: ', segment);
//		console.log('entireSegment: ', entireSegment);
//		console.log('next: ', next);
		if (typeof next != 'undefined') {
			if(entireSegment) {
				n = (next === 0) ? $(segment) : (next == 1) ? $('#segment-' + this.nextSegmentId) : $('#segment-' + this.nextUntranslatedSegmentId);
			}
		} else {
			n = segment;
		}
//		if(($(n).hasClass('glossary-loaded'))&&(entireSegment)) return false;
		$(n).addClass('glossary-loaded');
		$('.gl-search', n).addClass('loading');
		if(config.tms_enabled) {
			$('.sub-editor.glossary .overflow .results', n).empty();
			$('.sub-editor.glossary .overflow .graysmall.message', n).empty();			
		}
		txt = (entireSegment)? $('.text .source', n).attr('data-original') : view2rawxliff($('.gl-search .search-source', n).text());
//		console.log('typeof n: ', typeof $(n).attr('id'));
//		console.log('n: ', $(n).attr('id').split('-')[1]);
//		if((typeof $(n).attr('id') != 'undefined')&&($(n).attr('id').split('-')[1] == '13735228')) console.log('QUI 1: ', $('.source', n).html()); 
//		if($(n).attr('id').split('-')[1] == '13735228') {
//			console.log('QUI 1: ', $('.source', n).html()); 
//		}

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
			error: function() {
				UI.failedConnection(0, 'glossary');
			},
			success: function(d) {
				if(typeof d.errors != 'undefined') {
					if(d.errors[0].code == -1) {
						UI.noGlossary = true;
//						UI.body.addClass('noGlossary');
					}
				}
				n = this[0];
//				if($(n).attr('id').split('-')[1] == '13735228') console.log('QUI 2: ', $('.source', n).html()); 
//				if((typeof $(n).attr('id') != 'undefined')&&($(n).attr('id').split('-')[1] == '13735228')) console.log('QUI 2: ', $('.source', n).html()); 

				UI.processLoadedGlossary(d, this);
//				if((typeof $(n).attr('id') != 'undefined')&&($(n).attr('id').split('-')[1] == '13735228')) console.log('QUI 3: ', $('.source', n).html()); 
//				if($(n).attr('id').split('-')[1] == '13735228') console.log('QUI 3: ', $('.source', n).html()); 
//				console.log('next?: ', this[1]);
				if(!this[1]) UI.markGlossaryItemsInSource(d, this);
//				if((typeof $(n).attr('id') != 'undefined')&&($(n).attr('id').split('-')[1] == '13735228')) console.log('QUI 4: ', $('.source', n).html()); 
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
	markGlossaryItemsInSource: function(d, context) {
		if (Object.size(d.data.matches)) {
			i = 0;	
			cleanString = $('.source', UI.currentSegment).html();
			var intervals = [];
			$.each(d.data.matches, function(k) {
				i++;
				k1 = UI.decodePlaceholdersToText(k, true);
                k2 = k1.replace(/<\//gi, '<\\/').replace(/\(/gi, '\\(').replace(/\)/gi, '\\)');
                var re = new RegExp(k2.trim(), "gi");
                var cs = cleanString;
                coso = cs.replace(re, '<mark>' + k1 + '</mark>');

                if(coso.indexOf('<mark>') == -1) return;
				int = {
					x: coso.indexOf('<mark>'), 
					y: coso.indexOf('</mark>') - 6
				} 
				intervals.push(int);
			});
			UI.intervalsUnion = [];
			UI.checkIntervalsUnions(intervals);
			UI.startGlossaryMark = '<mark class="inGlossary">';
			UI.endGlossaryMark = '</mark>';
			markLength = UI.startGlossaryMark.length + UI.endGlossaryMark.length;
			sourceString = $('.editor .source').html();
			$.each(UI.intervalsUnion, function(index) {
				added = markLength * index;
				sourceString = sourceString.splice(this.x + added, 0, UI.startGlossaryMark);				
				sourceString = sourceString.splice(this.y + added + UI.startGlossaryMark.length, 0, UI.endGlossaryMark);
				$('.editor .source').html(sourceString);
			});		
		}		
	},
	removeGlossaryMarksFormSource: function() {
		$('.editor mark.inGlossary').each(function() {
			$(this).replaceWith($(this).html());
		})
	},

	checkIntervalsUnions: function(intervals) {
//		console.log('intervals: ', intervals);
		UI.endedIntervalAnalysis = false;
		smallest = UI.smallestInterval(intervals);
//		console.log('smallest: ', smallest);
		$.each(intervals, function(indice) {
			if(this === smallest) smallestIndex = indice;
		});
		mod = 0;
		$.each(intervals, function(i) {
			if(i != smallestIndex )  {
				if((smallest.x <= this.x)&&(smallest.y >= this.x)) { // this item is to be merged to the smallest
					mod++;
					smallest.y = this.y;
					intervals.splice(i, 1);
					UI.checkIntervalsUnions(intervals);
				}
//				if((i == (intervals.length -1))&&(!mod)) {
//					console.log('il primo non ha trovato unioni');
////					UI.checkIntervalsUnions(intervals);
//					return false;
//				}
			}
		});
		if(UI.endedIntervalAnalysis) {
			if(!intervals.length) return false;
			UI.checkIntervalsUnions(intervals);
			return false;
		}
		if(smallest.x < 1000000) UI.intervalsUnion.push(smallest);
//			console.log('intervals 1: ', JSON.stringify(intervals));
		intervals.splice(smallestIndex, 1);
//			console.log('intervals 2: ', JSON.stringify(intervals));
			if(!intervals.length) return false;
			if(!mod) UI.checkIntervalsUnions(intervals);
		UI.endedIntervalAnalysis = true;
		return false;
	},

	smallestInterval: function(ar) {
		smallest = {
					x: 1000000, 
					y: 2000000
				} 
		$.each(ar, function(index) {
			if(this.x < smallest.x) smallest = this;
		});
		return smallest;
	},

	renderGlossary: function(d, seg) {
		segment = seg;
		segment_id = segment.attr('id');
		$('.sub-editor.glossary .overflow .results', segment).empty();
		$('.sub-editor.glossary .overflow .message', segment).remove();
		numRes = 0;

		if (Object.size(d.data.matches)) {//console.log('ci sono match');
			$.each(d.data.matches, function(k) {
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
					$('.sub-editor.glossary .overflow .results', segment).append('<ul class="graysmall" data-item="' + (index + 1) + '" data-id="' + this.id + '"><li class="sugg-source">' + ((disabled) ? '' : ' <a id="' + segment_id + '-tm-' + this.id + '-delete" href="#" class="trash" title="delete this row"></a>') + '<span id="' + segment_id + '-tm-' + this.id + '-source" class="suggestion_source">' + UI.decodePlaceholdersToText(leftTxt, true) + '</span></li><li class="b sugg-target"><!-- span class="switch-editing">Edit</span --><span id="' + segment_id + '-tm-' + this.id + '-translation" class="translation">' + UI.decodePlaceholdersToText(rightTxt, true) + '</span></li><li class="details">' + ((this.comment === '')? '' : '<div class="comment">' + this.comment + '</div>') + '<ul class="graysmall-details"><li>' + this.last_update_date + '</li><li class="graydesc">Source: <span class="bold">' + cb + '</span></li></ul></li></ul>');
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
			error: function() {
				UI.failedConnection(0, 'glossary');
			},
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


