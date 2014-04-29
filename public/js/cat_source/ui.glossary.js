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
		if(config.tms_enabled) {
			$('.sub-editor.glossary .overflow .results', n).empty();
			$('.sub-editor.glossary .overflow .graysmall.message', n).empty();			
		}
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
				UI.processLoadedGlossary(d, this);
				UI.markGlossaryItemsInSource(d, this);
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
		console.log('d: ', d);
		console.log('context: ', context);
		if (Object.size(d.data.matches)) {
			i = 0;	
			cleanString = $('.source', UI.currentSegment).html();
			console.log('cleanString: ', cleanString);
			var intervals = [];
			$.each(d.data.matches, function(k) {
				i++;
				console.log(k);
//				console.log(i);
				var re = new RegExp("(" + k + ")", "gi");
				coso = cleanString.replace(re, '<mark>' + k + '</mark>');
				console.log('position 1: ', coso.indexOf('<mark>'));
				console.log('position 2: ', coso.indexOf('</mark>') - 6);
				int = {
					x: coso.indexOf('<mark>'), 
					y: coso.indexOf('</mark>') - 6
				} 
				intervals.push(int);
//				console.log(UI.checkIntervalsUnions(intervals, i));
				
				
//				$('.source', UI.currentSegment).html($('.source', UI.currentSegment).html().replace(re, '<mark class="glossary-' + i + '">' + k + '</mark>'));

//				numRes++;
//				$('.sub-editor.glossary .overflow .results', segment).append('<div class="glossary-item"><span>' + k + '</span></div>');
//				$.each(this, function(index) {
//					if ((this.segment === '') || (this.translation === ''))
//						return;
//					var disabled = (this.id == '0') ? true : false;
//					cb = this.created_by;
//					if(typeof this.target_note == 'undefined'){ this.comment = ''; }
//					else { this.comment = this.target_note; }
//					cl_suggestion = UI.getPercentuageClass(this.match);
//					var leftTxt = this.segment;
//					leftTxt = leftTxt.replace(/\#\{/gi, "<mark>");
//					leftTxt = leftTxt.replace(/\}\#/gi, "</mark>");
//					var rightTxt = this.translation;
//					rightTxt = rightTxt.replace(/\#\{/gi, "<mark>");
//					rightTxt = rightTxt.replace(/\}\#/gi, "</mark>");
//					$('.sub-editor.glossary .overflow .results', segment).append('<ul class="graysmall" data-item="' + (index + 1) + '" data-id="' + this.id + '"><li class="sugg-source">' + ((disabled) ? '' : ' <a id="' + segment_id + '-tm-' + this.id + '-delete" href="#" class="trash" title="delete this row"></a>') + '<span id="' + segment_id + '-tm-' + this.id + '-source" class="suggestion_source">' + leftTxt + '</span></li><li class="b sugg-target"><!-- span class="switch-editing">Edit</span --><span id="' + segment_id + '-tm-' + this.id + '-translation" class="translation">' + rightTxt + '</span></li><li class="details">' + ((this.comment === '')? '' : '<div class="comment">' + this.comment + '</div>') + '<ul class="graysmall-details"><li>' + this.last_update_date + '</li><li class="graydesc">Source: <span class="bold">' + cb + '</span></li></ul></li></ul>');
//				});
			});

//			console.log('intervals: ', intervals);
//			console.log(UI.smallestInterval(intervals));
			UI.intervalsUnion = [];
			
			UI.checkIntervalsUnions(intervals);
//			console.log(UI.checkIntervalsUnions(intervals));

/*
			intervalsUnion = [];
			intervalsUnion.push(UI.smallestInterval(intervals));
			$.each(intervals, function(index) {
			});
			console.log('intervalsUnion: ', intervalsUnion);
*/
		}		
	},
	checkIntervalsUnions: function(intervals) { 
		// ricordati di togliere la chiamata dal console.log e di eliminare il return sotto
		// se intervals è vuoto uscire dalla funzione e vai ad una funzione che applica la formattazione al source basandosi su UI.intervalsUnion
		smallest = UI.smallestInterval(intervals);
		$.each(intervals, function(indice) {
			if(this === smallest) smallestIndex = indice;
		});
		mod = 0;
		$.each(intervals, function(i) {
//			console.log('i: ' + i + ', index: ' + smallestIndex);
			if(i != smallestIndex )  {
				console.log(this.x + ' è tra ' + smallest.x + ' e ' + smallest.y + '?');
				if((smallest.x <= this.x)&&(smallest.y >= this.x)) { // this item is to be merged to the smallest
					console.log(this.x + ' è da unire');
					smallest.y = this.y;
					mod++;
				}
			}
		});
		if(mod) {
			// aggiorna lo smallest.y e riesegui la funzione
			intervals[smallestIndex].y = smallest.y;
//			this.checkIntervalsUnions(intervals);
		} else {
			console.log('non modificato: ', intervals);
			// copia lo smallest in UI.intervalsUnion, eliminalo da intervals e riesegui la funzione.
		}


//		intervals.splice(smallestIndex, 1);
//		console.log('intervals meno lo smallest', intervals);
//		return intervals;
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

		if (Object.size(d.data.matches)) {console.log('ci sono match');
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


