/*
	Component: ui.tags
 */

$('html').on('copySourceToTarget', 'section', function() {
    UI.lockTags(UI.editarea);
});

$.extend(UI, {
/*
    tagLockCustomize: function(e) {
        e.preventDefault();
        console.log('vediamo');
    },
*/
    noTagsInSegment: function(options) {
        editarea = options.area;
        starting = options.starting;
        if(starting) return false;

        try{
            if ($(editarea).html().match(/\&lt;.*?\&gt;/gi)) {
                return false;
            } else {
                return true;
            }
        } catch(e){
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
    disableTagMark: function() {
		this.taglockEnabled = false;
		this.body.addClass('tagmarkDisabled');
		$('.source span.locked').each(function() {
			$(this).replaceWith($(this).html());
		});
		$('.editarea span.locked').each(function() {
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
            $(this).html($(this).html().replace(/(&lt;[\/]*(g|x|bx|ex|bpt|ept|ph|it|mrk)\s*.*?&gt;)/gi, "<span contenteditable=\"false\" class=\"locked\">$1</span>"));
           // $(this).html($(this).html().replace(/(&lt;(g|x|bx|ex|bpt|ept|ph|it|mrk)\sid.*?&gt;)/gi, "<span contenteditable=\"false\" class=\"locked\">$1</span>"));
			if (UI.isFirefox) {
				$(this).html($(this).html().replace(/(<span class=\"(.*?locked.*?)\" contenteditable=\"false\"\>)(<span class=\"(.*?locked.*?)\" contenteditable=\"false\"\>)(.*?)(<\/span\>){2,}/gi, "$1$5</span>"));
			} else {
				$(this).html($(this).html().replace(/(<span contenteditable=\"false\" class=\"(.*?locked.*?)\"\>)(<span contenteditable=\"false\" class=\"(.*?locked.*?)\"\>)(.*?)(<\/span\>){2,}/gi, "$1$5</span>"));
			}
            UI.detectTagType(this);
        });
		$('.footer .translation').each(function() {
            $(this).html($(this).html().replace(/(&lt;[\/]*(g|x|bx|ex|bpt|ept|ph|it|mrk)\s*.*?&gt;)/gi, "<span contenteditable=\"false\" class=\"locked\">$1</span>"));
//			$(this).html($(this).html().replace(/(&lt;(g|x|bx|ex|bpt|ept|ph|it|mrk)\sid.*?&gt;)/gi, "<span contenteditable=\"false\" class=\"locked\">$1</span>"));
			if (UI.isFirefox) {
				$(this).html($(this).html().replace(/(<span class=\"(.*?locked.*?)\" contenteditable=\"false\"\>)(<span class=\"(.*?locked.*?)\" contenteditable=\"false\"\>)(.*?)(<\/span\>){2,}/gi, "$1$5</span>"));
			} else {
				$(this).html($(this).html().replace(/(<span contenteditable=\"false\" class=\"(.*?locked.*?)\"\>)(<span contenteditable=\"false\" class=\"(.*?locked.*?)\"\>)(.*?)(<\/span\>){2,}/gi, "$1$5</span>"));
			}
            UI.detectTagType(this);
        });

    },
	markTags: function() {
		if (!this.taglockEnabled) return false;
//		UI.checkHeaviness(); 

		if(this.noTagsInSegment({
            area: false,
            starting: true
        })) {
            return false;
        }

		$('.source, .editarea').each(function() {
			UI.lockTags(this);
		});
	},

	markTagsInSearch: function(el) {
		if (!this.taglockEnabled)
			return false;
		var elements = (typeof el == 'undefined') ? $('.editor .cc-search .input') : el;
		elements.each(function() {
//			UI.lockTags(this);
		});
	},

	// TAG LOCK
	lockTags: function(el) {
		if (this.body.hasClass('tagmarkDisabled'))
			return false;
		editarea = (typeof el == 'undefined') ? UI.editarea : el;
        el = (typeof el == 'undefined') ? UI.editarea : el;
//        console.log('typeof el: ', typeof el);
		if (!this.taglockEnabled)
			return false;
//        console.log('this.noTagsInSegment(): ', this.noTagsInSegment());
//		console.log('IL SEGMENTO: ', $('#segment-' + el.attr('data-sid')));
//        console.log('devo interrompere il lockTags?: ', this.noTagsInSegment($(el).parents('section').first()));
//        console.log('elemento: ', el);
        if(el != '') {
            if (this.noTagsInSegment({
                area: el,
                starting: false
            })) {
                return false;
            }
        }

        $(editarea).first().each(function() {
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

                   segment = $(this).parents('section');

                   if($('span.locked', this).length) {
                       segment.addClass('hasTags');
                   } else {
                       segment.removeClass('hasTags');
                   }
                   $('span.locked', this).addClass('monad');
                   UI.detectTagType(this);

//            UI.checkTagsInSegment();
               });



	},
    detectTagType: function (area) {
        $('span.locked', area).each(function () {
//                console.log(segment.attr('id') + ' - ' + $(this).text());
//                console.log($(this).text().startsWith('</'));
            if($(this).text().startsWith('</')) {
                $(this).addClass('endTag')
            } else {
                if($(this).text().endsWith('/>')) {
                    $(this).addClass('selfClosingTag')
                } else {
                    $(this).addClass('startTag')
                }
            }
        })
    },

    unlockTags: function() {
		if (!this.taglockEnabled)
			return false;
        this.editarea.html(this.removeLockTagsFromString(this.editarea.html()));
//		this.editarea.html(this.editarea.html().replace(/<span contenteditable=\"false\" class=\"locked\"\>(.*?)<\/span\>/gi, "$1"));
//		this.editarea.html(this.editarea.html().replace(/<span contenteditable=\"true\" class=\"locked\"\>(.*?)<\/span\>/gi, "$1"));
	},
    removeLockTagsFromString: function (str) {
        return str.replace(/<span contenteditable=\"false\" class=\"locked\"\>(.*?)<\/span\>/gi, "$1");
    },

    // TAG CLEANING
	cleanDroppedTag: function(area, beforeDropHTML) {
 //       if (area == this.editarea) {
			this.droppingInEditarea = false;

			diff = this.dmp.diff_main(beforeDropHTML, $(area).html());
			draggedText = '';
			$(diff).each(function() {
				if (this[0] == 1) {
					draggedText += this[1];
				}
			});
			draggedText = draggedText.replace(/^(\&nbsp;)(.*?)(\&nbsp;)$/gi, "$2");
			dr2 = draggedText.replace(/(<br>)$/, '').replace(/(<span.*?>)\&nbsp;/,'$1');

			area.html(area.html().replace(draggedText, dr2));

			div = document.createElement("div");
			div.innerHTML = draggedText;
			isMarkup = draggedText.match(/^<span style=\"font\-size\: 13px/gi);
			saveSelection();

			if ( $('span .rangySelectionBoundary', area).length > 1 ) {
                $('.rangySelectionBoundary', area).last().remove();
            }

			if($('span .rangySelectionBoundary', area).length) {
				spel = $('span', area).has('.rangySelectionBoundary');
				rsb = $('span .rangySelectionBoundary', area).detach();
				spel.after(rsb);
			}
			phcode = $('.rangySelectionBoundary')[0].outerHTML;
			$('.rangySelectionBoundary').text(this.cursorPlaceholder);

			newTag = $(div).text();
			var cloneEl = area;
			// encode br before textification
			$('br', cloneEl).each(function() {
				$(this).replaceWith('[**[br class="' + this.className + '"]**]');				
			});
			newText = cloneEl.text().replace(draggedText, newTag);
			cloneEl = null;
			if(typeof phcode == 'undefined') phcode = '';

			area.text(newText);
			area.html(area.html().replace(this.cursorPlaceholder, phcode));
			restoreSelection();
			area.html(area.html().replace(this.cursorPlaceholder, '').replace(/\[\*\*\[(.*?)\]\*\*\]/gi, "<$1>"));
	},
    setTagMode: function () {
        if(this.custom.extended_tagmode) {
            this.setExtendedTagMode();
        } else {
            this.setCrunchedTagMode();
        }
    },
    setExtendedTagMode: function () {
        this.body.addClass('tagmode-default-extended');
//        console.log('segment: ', segment);
        if(typeof UI.currentSegment != 'undefined') UI.pointToOpenSegment();
        this.custom.extended_tagmode = true;
        this.saveCustomization();
    },
    setCrunchedTagMode: function () {
        this.body.removeClass('tagmode-default-extended');
//        console.log('segment: ', segment);
        if(typeof UI.currentSegment != 'undefined') UI.pointToOpenSegment();
        this.custom.extended_tagmode = false;
        this.saveCustomization();
    },

    /*
        checkTagsInSegment: function (el) {
            segment = el || UI.currentSegment;
            hasTags = ($(segment).find('.wrap span.locked').length)? true : false;
            if(hasTags) {
                this.setExtendedTagMode(el);
            } else {
                this.setCrunchedTagMode(el);
            }
        },
    */
    enableTagMode: function () {
        UI.render(
            {tagModesEnabled: true}
        )
    },
    disableTagMode: function () {
        UI.render(
            {tagModesEnabled: false}
        )
    },
    nearTagOnRight: function (index, ar) {
//        console.log('nearTagOnRight');
//        console.log('html: ', UI.editarea.html());
        if($(ar[index]).hasClass('locked')) {
            if(UI.numCharsUntilTagRight == 0) {
                // count index of this tag in the tags list
                indexTags = 0;
                $.each(ar, function (ind) {
                    if(ind == index) {
                        return false;
                    } else {
                        if($(this).hasClass('locked')) {
                            indexTags++;
                        }
                    }
                });
                return true;
            } else {
                return false;
            }
        } else {
            if (typeof ar[index] == 'undefined') return false;

            if(ar[index].nodeName == '#text') {
                UI.numCharsUntilTagRight += ar[index].data.length;
            }
            this.nearTagOnRight(index+1, ar);
        }
    },
    nearTagOnLeft: function (index, ar) {
        if (index < 0) return false;
/*
        console.log('nearTagOnLeft');
        console.log('html: ', UI.editarea.html());
        console.log('index: ', index);
        console.log('ar: ', ar);
        console.log('$(ar[index]): ', $(ar[index]));
*/
//        console.log('UI.numCharsUntilTag: ', UI.numCharsUntilTag);
        if($(ar[index]).hasClass('locked')) {
            if(UI.numCharsUntilTagLeft == 0) {
                // count index of this tag in the tags list
                indexTags = 0;
                $.each(ar, function (ind) {
                    if(ind == index) {
                        return false;
                    } else {
                        if($(this).hasClass('locked')) {
                            indexTags++;
                        }
                    }
                });
                return true;
            } else {
                return false;
            }
        } else {
            if(ar[index].nodeName == '#text') {
                UI.numCharsUntilTagLeft += ar[index].data.length;
            }
            this.nearTagOnLeft(index-1, ar);
        }
    },
    checkTagProximity: function () {
//        return false;
        if(UI.editarea.html() == '') return false;

        selection = window.getSelection();
        if(selection.rangeCount < 1) return false;
        range = selection.getRangeAt(0);
        if(!range.collapsed) return true;
        nextEl = $(range.endContainer.nextElementSibling);
        prevEl = $(range.endContainer.previousElementSibling);
//        console.log('nextEl: ', nextEl.length);
//        console.log('prevEl: ', prevEl.length);
        tempRange = range;
        UI.editarea.find('.test-invisible').remove();
        pasteHtmlAtCaret('<span class="test-invisible"></span>');
        coso = $.parseHTML(UI.editarea.html());
//        console.log('coso: ', coso);
        $.each(coso, function (index) {
            if($(this).hasClass('test-invisible')) {
                UI.numCharsUntilTagRight = 0;
                UI.numCharsUntilTagLeft = 0;
//                console.log('index: ', index);
//                console.log('sssss: ', UI.nearTagOnRight(index+1, coso));
                nearTagOnRight = UI.nearTagOnRight(index+1, coso);
//                console.log('nearTagOnRight: ', nearTagOnRight);
                nearTagOnLeft = UI.nearTagOnLeft(index-1, coso);
//                console.log('nearTagOnLeft: ', nearTagOnLeft);

                if((typeof nearTagOnRight != 'undefined')&&(nearTagOnRight)) {//console.log('1');
                    UI.removeHighlightCorrespondingTags();
                    UI.highlightCorrespondingTags($(UI.editarea.find('.locked')[indexTags]));
                } else if((typeof nearTagOnLeft != 'undefined')&&(nearTagOnLeft)) {//console.log('2');
                    UI.removeHighlightCorrespondingTags();
                    UI.highlightCorrespondingTags($(UI.editarea.find('.locked')[indexTags]));
                } else {//console.log('3');
                    UI.removeHighlightCorrespondingTags();
                }

                UI.numCharsUntilTagRight = null;
                UI.numCharsUntilTagLeft = null;
                UI.editarea.find('.test-invisible').remove();
                return false;
            };
        });

    },
    highlightCorrespondingTags: function (el) {
//        console.log('highlighting: ', $(el));
        if(el.hasClass('startTag')) {
//            console.log('has start tag');
            if(el.next('.endTag').length) {
                el.next('.endTag').addClass('highlight');
            } else {
//                console.log('il successivo non è un end tag');
                num = 1;
                ind = 0;
                $(el).nextAll('.locked').each(function () {
                    ind++;
//                    console.log('ora stiamo valutando: ', $(this));
                    if($(this).hasClass('startTag')) {
                        num++;
                    } else if($(this).hasClass('selfClosingTag')) {

                    } else { // end tag
                        num--;
                        if(num == 0) {
//                            console.log('found el: ', $(this));
                            pairEl = $(this);
                            return false;
                        }
                    }
//                    $(this).addClass('test-' + num);

                })
//                console.log('pairEl: ', $(pairEl).text());
                $(pairEl).addClass('highlight');


            }
//            console.log('next endTag: ', el.next('.endTag'));
        } else if(el.hasClass('endTag')) {
//            console.log('is an end tag');
            if(el.prev('.startTag').length) {
//                console.log('and the previous element is a start tag');
                el.prev('.startTag').first().addClass('highlight');
            } else {
//                console.log('and the previous element is not a start tag');
                num = 1;
                ind = 0;
                $(el).prevAll('.locked').each(function () {
                    ind++;
//                    console.log('start tag: ', $(this));

                    if($(this).hasClass('endTag')) {
                        num++;
                    } else if($(this).hasClass('selfClosingTag')) {

                    } else { // end tag
                        num--;
                        if(num == 0) {
//                            console.log('found el: ', $(this));
                            pairEl = $(this);
                            return false;
                        }
                    }

                });
                $(pairEl).addClass('highlight');
            }
        }
//        console.log('$(el): ', $(el).text());
        $(el).addClass('highlight');
//        console.log('vediamo: ', UI.editarea.html());


//        console.log('$(pairEl).length: ', $(pairEl).length);

//        UI.editarea.find('.locked')

    },
    removeHighlightCorrespondingTags: function () {
//        console.log('REMOVED HIGHLIGHTING');
        $(UI.editarea).find('.locked.highlight').removeClass('highlight');
    },

    // TAG MISMATCH
	markTagMismatch: function(d) {
        if(($.parseJSON(d.warnings).length)) {
//            $('#segment-' + d.id_segment).attr('data-tagMode', 'extended');
        }
//        $('#segment-' + d.id_segment).attr('data-tagMode', 'extended');
//        this.setExtendedTagMode($('#segment-' + d.id_segment));
        // temp
//        d.tag_mismatch.order = 2;
        if((typeof d.tag_mismatch.order == 'undefined')||(d.tag_mismatch.order === '')) {
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
        } else {
            $('#segment-' + d.id_segment + ' .editarea .locked' ).filter(function() {
                return $(this).text() === d.tag_mismatch.order[0];
            }).addClass('order-error');
        }

	},	

	// TAG AUTOCOMPLETE
	checkAutocompleteTags: function() {//console.log('checkAutocompleteTags');
//        console.log('checkAutocompleteTags: ', UI.editarea.html() );
		added = this.getPartialTagAutocomplete();
//		console.log('added: "', added + '"');
//		console.log('aa: ', UI.editarea.html());
		$('.tag-autocomplete li.hidden').removeClass('hidden');
		$('.tag-autocomplete li').each(function() {
			var str = $(this).text();
//            console.log('"' + str.substring(0, added.length) + '" == "' + added + '"');
			if( str.substring(0, added.length) === added ) {
				$(this).removeClass('hidden');
			} else {
				$(this).addClass('hidden');	
			}
		});
//		console.log('bb: ', UI.editarea.html());
		if(!$('.tag-autocomplete li:not(.hidden)').length) { // no tags matching what the user is writing

			$('.tag-autocomplete').addClass('empty');
			if(UI.preCloseTagAutocomplete) {
				UI.closeTagAutocompletePanel();
				return false;				
			}
			UI.preCloseTagAutocomplete = true;
		} else {
//			console.log('dd: ', UI.editarea.html());

			$('.tag-autocomplete li.current').removeClass('current');
			$('.tag-autocomplete li:not(.hidden)').first().addClass('current');
			$('.tag-autocomplete').removeClass('empty');		
//			console.log('ee: ', UI.editarea.html());
			UI.preCloseTagAutocomplete = false;
		}
	},
	closeTagAutocompletePanel: function() {
		$('.tag-autocomplete, .tag-autocomplete-endcursor').remove();
		UI.preCloseTagAutocomplete = false;
	},
	getPartialTagAutocomplete: function() {
//		console.log('inizio di getPartialTagAutocomplete: ', UI.editarea.html());
//		var added = UI.editarea.html().match(/&lt;([&;"\w\s\/=]*?)<span class="tag-autocomplete-endcursor">/gi);
		var added = UI.editarea.html().match(/&lt;(?:[a-z]*(?:&nbsp;)*["\w\s\/=]*)?<span class="tag-autocomplete-endcursor">/gi);
//        console.log('prova: ', UI.editarea.html().match(/&lt;(?:[a-z]*(?:&nbsp;)*["\w\s\/=]*)?<span class="tag-autocomplete-endcursor">\&/gi));
//		console.log('added 1: ', added);
		added = (added === null)? '' : htmlDecode(added[0].replace(/<span class="tag-autocomplete-endcursor"\>/gi, '')).replace(/\xA0/gi," ");
//        console.log('added 2: ', added);
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
//        console.log('prima di inserire endcursor: ', UI.editarea.html());
		insertNodeAtCursor(endCursor);
//		console.log('inserito endcursor: ', UI.editarea.html());
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
	jumpTag: function(range) {
/*
        console.log('RANGE IN JUMPTAG: ', range.endContainer);
        console.log('range.endContainer.data.length: ', range.endContainer.data.length);
        console.log('range.endOffset: ', range.endOffset);
        console.log('range.endContainer.nextElementSibling.className: ', range.endContainer.nextElementSibling.className);

        for(var key in range.endContainer) {
            console.log('key: ' + key + '\n' + 'value: "' + range.endContainer[key] + '"');
        }
 */
//        console.log('data: ', range.endContainer);
		if(typeof range.endContainer.data != 'undefined') {
            if((range.endContainer.data.length == range.endOffset)&&(range.endContainer.nextElementSibling.className == 'monad')) {
//			console.log('da saltare');
                setCursorAfterNode(range, range.endContainer.nextElementSibling);
            }
        }

	},

});


