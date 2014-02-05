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
	detectTags: function(area) {console.log('detect');
		$(area).html($(area).html().replace(/(&lt;\s*\/*\s*(g|x|bx|ex|bpt|ept|ph|it|mrk)\s*.*?&gt;)/gi, "<span contenteditable=\"false\" class=\"locked\">$1</span>"));
		if (!this.firstMarking) {
			$(area).html($(area).html().replace(/(<span contenteditable=\"false\" class=\".*?locked.*?\"\>){2,}(.*?)(<\/span\>){2,}/gi, "<span contenteditable=\"false\" class=\"locked\">$2</span>"));
		}
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
	enableTagMark: function() {console.log('enable tag mark');
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
		if (this.noTagsInSegment(1))
			return false;
		$('.source').each(function() {
			UI.detectTags(this);
		});

//		$('.editarea').each(function() {
//			if ($('#segment-' + $(this).data('sid')).hasClass('mismatch'))
//				return false;
//			UI.detectTags(this);
//		});
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
		if (this.body.hasClass('tagmarkDisabled'))
			return false;
		editarea = (typeof el == 'undefined') ? UI.editarea : el;
		if (!this.taglockEnabled)
			return false;
		if (this.noTagsInSegment())
			return false;
//		console.log('b');
//		console.log($(editarea).html());
		$(editarea).first().each(function(index) {
			saveSelection();

			var tx = $(this).html();
			brTx1 = (UI.isFirefox)? "<pl class=\"locked\" contenteditable=\"false\">$1</pl>" : "<pl contenteditable=\"false\" class=\"locked\">$1</pl>";
			brTx2 = (UI.isFirefox)? "<span class=\"locked\" contenteditable=\"false\">$1</span>" : "<span contenteditable=\"false\" class=\"locked\">$1</span>";
			tx = tx.replace(/<span/gi, "<pl")
					.replace(/<\/span/gi, "</pl")
					.replace(/&lt;/gi, "<")
					.replace(/(<(g|x|bx|ex|bpt|ept|ph|it|mrk)\sid[^<]*?&gt;)/gi, brTx1)
					.replace(/</gi, "&lt;")
					.replace(/\&lt;pl/gi, "<span")
					.replace(/\&lt;\/pl/gi, "</span")
					.replace(/\&lt;div\>/gi, "<div>")
					.replace(/\&lt;\/div\>/gi, "</div>")
					.replace(/\&lt;br\>/gi, "<br>")
					.replace(/\&lt;br class=["\'](.*?)["\'][\s]*[\/]*(\&gt;|\>)/gi, '<br class="$1" />')

					// encapsulate tags of closing
					.replace(/(&lt;\s*\/\s*(g|x|bx|ex|bpt|ept|ph|it|mrk)\s*&gt;)/gi, brTx2);

			if (UI.isFirefox) {
				tx = tx.replace(/(<span class=\"(.*?locked.*?)\" contenteditable=\"false\"\>)(<span class=\"(.*?locked.*?)\" contenteditable=\"false\"\>)(.*?)(<\/span\>){2,}/gi, "$1$5</span>");
				tx = tx.replace(/(<span class=\"(.*?locked.*?)\" contenteditable=\"false\"\>){2,}(.*?)(<\/span\>){2,}/gi, "<span class=\"$2\" contenteditable=\"false\">$3</span>");
			} else {
				// fix nested encapsulation
				tx = tx.replace(/(<span contenteditable=\"false\" class=\"(.*?locked.*?)\"\>)(<span contenteditable=\"false\" class=\"(.*?locked.*?)\"\>)(.*?)(<\/span\>){2,}/gi, "$1$5</span>");
				tx = tx.replace(/(<span contenteditable=\"false\" class=\"(.*?locked.*?)\"\>){2,}(.*?)(<\/span\>){2,}/gi, "<span contenteditable=\"false\" class=\"$2\">$3</span>");
			}

			tx = tx.replace(/(<\/span\>)$(\s){0,}/gi, "</span> ");
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
	},
	
	// TAG CLEANING
	cleanDroppedTag: function(area, beforeDropHTML) {

		if (area == this.editarea)
			this.droppingInEditarea = false;

		var diff = this.dmp.diff_main(beforeDropHTML, $(area).html());
		var draggedText = '';
		$(diff).each(function() {
			if (this[0] == 1) {
				draggedText += this[1];
			}
		});

		draggedText = draggedText.replace(/^(\&nbsp;)(.*?)(\&nbsp;)$/gi, "$2");
		var div = document.createElement("div");
		div.innerHTML = draggedText;
		saveSelection();
		var phcode = $('.rangySelectionBoundary')[0].outerHTML;
		$('.rangySelectionBoundary').text(this.cursorPlaceholder);

		closeTag = '</' + $(div).text().trim().replace(/<(.*?)\s.*?\>/gi, "$1") + '>';
		newTag = $(div).text();

		var newText = area.text().replace(draggedText, newTag);
		area.text(newText);
		area.html(area.html().replace(this.cursorPlaceholder, phcode));
		restoreSelection();
		area.html(area.html().replace(this.cursorPlaceholder, ''));

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
		$('.tag-autocomplete-marker').remove();
		UI.body.append('<div class="tag-autocomplete"><ul></ul></div>');
		$.each(UI.sourceTags, function(index) {
			$('.tag-autocomplete ul').append('<li' + ((index === 0)? ' class="current"' : '') + '>' + this + '</li>');
		});

		$('.tag-autocomplete').css('top', offset.top + 20);
		$('.tag-autocomplete').css('left', offset.left);
		this.checkAutocompleteTags();	
	},
});


