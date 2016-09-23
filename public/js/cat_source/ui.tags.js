/*
	Component: ui.tags
 */

$('html').on('copySourceToTarget', 'section', function( el ) {
    UI.lockTags(UI.editarea);
});

$.extend(UI, {
    noTagsInSegment: function(options) {
        var editarea = options.area;
        var starting = options.starting;

        if (starting) return false;

        try{
            if ( $(editarea).html().match(/\&lt;.*?\&gt;/gi) ) {
                return false;
            } else {
                return true;
            }
        } catch(e){
            return true;
        }

	},
	tagCompare: function(sourceTags, targetTags, prova) {

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
	enableTagMark: function() {
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
			if (UI.isFirefox) {
				$(this).html($(this).html().replace(/(<span class=\"(.*?locked.*?)\" contenteditable=\"false\"\>)(<span class=\"(.*?locked.*?)\" contenteditable=\"false\"\>)(.*?)(<\/span\>){2,}/gi, "$1$5</span>"));
			} else {
				$(this).html($(this).html().replace(/(<span contenteditable=\"false\" class=\"(.*?locked.*?)\"\>)(<span contenteditable=\"false\" class=\"(.*?locked.*?)\"\>)(.*?)(<\/span\>){2,}/gi, "$1$5</span>"));
			}
            UI.detectTagType(this);
        });
		$('.footer .translation').each(function() {
            $(this).html($(this).html().replace(/(&lt;[\/]*(g|x|bx|ex|bpt|ept|ph|it|mrk)\s*.*?&gt;)/gi, "<span contenteditable=\"false\" class=\"locked\">$1</span>"));
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


    transformTextForLockTags : function( tx ) {
        var brTx1 = (UI.isFirefox)? "<pl class=\"locked\" contenteditable=\"false\">$1</pl>" : "<pl contenteditable=\"false\" class=\"locked\">$1</pl>";
        var brTx2 = (UI.isFirefox)? "<span class=\"locked\" contenteditable=\"false\">$1</span>" : "<span contenteditable=\"false\" class=\"locked\">$1</span>";

        tx = tx.replace(/<span/gi, "<pl")
            .replace(/<\/span/gi, "</pl")
            .replace(/&lt;/gi, "<")
            .replace(/(<(g|x|bx|ex|bpt|ept|ph[^a-z]*|it|mrk)\sid[^<“]*?&gt;)/gi, brTx1)
            .replace(/</gi, "&lt;")
            .replace(/\&lt;pl/gi, "<span")
            .replace(/\&lt;\/pl/gi, "</span")
            .replace(/\&lt;lxqwarning/gi, "<lxqwarning")
            .replace(/\&lt;\/lxqwarning/gi, "</lxqwarning")
            .replace(/\&lt;div\>/gi, "<div>")
            .replace(/\&lt;\/div\>/gi, "</div>")
            .replace(/\&lt;br\>/gi, "<br>")
            .replace(/\&lt;mark/gi, "<mark")
            .replace(/\&lt;\/mark/gi, "</mark")
            .replace(/\&lt;br class=["\'](.*?)["\'][\s]*[\/]*(\&gt;|\>)/gi, '<br class="$1" />')
            .replace(/(&lt;\s*\/\s*(g|x|bx|ex|bpt|ept|ph|it|mrk)\s*&gt;)/gi, brTx2);

            if (UI.isFirefox) {
                tx = tx.replace(/(<span class="[^"]*" contenteditable="false"\>)(:?<span class="[^"]*" contenteditable="false"\>)(.*?)(<\/span\>){2}/gi, "$1$3</span>");
            } else {
                tx = tx.replace(/(<span contenteditable="false" class="[^"]*"\>)(:?<span contenteditable="false" class="[^"]*"\>)(.*?)(<\/span\>){2}/gi, "$1$3</span>");
            }

            tx = tx.replace(/(<\/span\>)$(\s){0,}/gi, "</span> ");
            tx = tx.replace(/(<\/span\>\s)$/gi, "</span><br class=\"end\">");
        return tx;
    },


	markTagsInSearch: function(el) {
		if (!this.taglockEnabled)
			return false;
		var elements = (typeof el == 'undefined') ? $('.editor .cc-search .input') : el;
	},

    /**
     * This function replaces tags with monads
     */
	lockTags: function(el) {
        var self = this;
		if (this.body.hasClass('tagmarkDisabled')) {
			return false;
        }

        if (!this.taglockEnabled) {
            return false;
        }

		var area = (typeof el == 'undefined') ? UI.editarea : el;

        if (this.noTagsInSegment({
            area: area,
            starting: false
        })) {
            return false;
        }

        $(area).first().each(function() {
            var segment = $(this).closest('section');
			if (LXQ.enabled()) {
            	$.powerTip.destroy($('.tooltipa',segment));
            	$.powerTip.destroy($('.tooltipas',segment));
            }
            saveSelection();

            var html = $(this).html() ;

            var tx = UI.transformTextForLockTags( html ) ;
            $(this).html(tx);

            var prevNumTags = $('span.locked', this).length;

            restoreSelection();
            if (LXQ.enabled())
                LXQ.reloadPowertip(segment);
            if ($('span.locked', this).length != prevNumTags) UI.closeTagAutocompletePanel();



            UI.evalCurrentSegmentTranslationAndSourceTags( segment );

            if ( UI.hasSourceOrTargetTags( segment ) ) {
                segment.addClass( 'hasTagsToggle' );
            } else {
                segment.removeClass( 'hasTagsToggle' );
            }

            if ( UI.hasMissingTargetTags( segment ) ) {
                segment.addClass( 'hasTagsAutofill' );
            } else {
                segment.removeClass( 'hasTagsAutofill' );
            }

            $('span.locked', this).addClass('monad');

            UI.detectTagType(this);
        });
    },

    detectTagType: function (area) {
        $('span.locked', area).each(function () {
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
	},

    toggleTagsMode: function (elem) {
        if (elem) {
            $(elem).toggleClass('active');
        }
        UI.body.toggleClass('tagmode-default-extended');
    },

    removeLockTagsFromString: function (str) {
        return str.replace(/<span contenteditable=\"false\" class=\"locked\"\>(.*?)<\/span\>/gi, "$1");
    },

    // TAG CLEANING
    cleanDroppedTag: function ( area, beforeDropHTML ) {

        this.droppingInEditarea = false;

        //detect selected text
        var html = "";
        if ( typeof window.getSelection != "undefined" ) {
            var sel = window.getSelection();
            if ( sel.rangeCount ) {
                var container = document.createElement( "div" );
                for ( var i = 0, len = sel.rangeCount; i < len; ++i ) {
                    container.appendChild( sel.getRangeAt( i ).cloneContents() );
                }
                html = container.innerHTML;
            }
        } else if ( typeof document.selection != "undefined" ) {
            if ( document.selection.type == "Text" ) {
                html = document.selection.createRange().htmlText;
            }
        }
        draggedText = html;


        draggedText = draggedText.replace( /^(\&nbsp;)(.*?)(\&nbsp;)$/gi, "$2" );
        dr2 = draggedText.replace( /(<br>)$/, '' );

        area.html( area.html().replace( draggedText, dr2 ) );
        saveSelection();

        if ( $( 'span .rangySelectionBoundary', area ).length > 1 ) {
            $( '.rangySelectionBoundary', area ).last().remove();
        }

        if ( $( 'span .rangySelectionBoundary', area ).length ) {
            spel = $( 'span', area ).has( '.rangySelectionBoundary' );
            rsb = $( 'span .rangySelectionBoundary', area ).detach();
            spel.after( rsb );
        }

        phcode = $( '.rangySelectionBoundary' )[0].outerHTML;
        $( '.rangySelectionBoundary' ).text( this.cursorPlaceholder );


        //map with special simbols
        var mapSpecialSimbols = {
            "<span class=\"tab-marker monad marker _09\">⇥</span>": "##PlaceHolderTABS##"
        };

        var clonedEl = area.clone();
        //replace special simbol with placeholder
        var replacementSpecialSimbol = clonedEl.html();
        for ( key in mapSpecialSimbols ) {
            if ( clonedEl.html().indexOf( key ) > -1 ) {
                var reg = new RegExp( key, "g" );
                replacementSpecialSimbol = replacementSpecialSimbol.replace( reg, mapSpecialSimbols[key] );
            }
        }

        // encode br before textification
        $( 'br', clonedEl ).each( function () {
            $( this ).replaceWith( '[**[br class="' + this.className + '"]**]' );
        } );

        //new target text with placeholder
        var drag = document.createElement( "drag" );
        var newText = $( drag ).html( replacementSpecialSimbol ).text().replace( /(<span.*?>)\&nbsp;/, '$1' );

        if ( typeof phcode == 'undefined' ) phcode = '';

        clonedEl.text( newText );

        //replace placeholder with special simbol
        var areaHTML = clonedEl.html();
        for ( key in mapSpecialSimbols ) {
            if ( areaHTML.indexOf( mapSpecialSimbols[key] ) > -1 ) {
                var reg = new RegExp( mapSpecialSimbols[key], "g" );
                areaHTML = areaHTML.replace( reg, key );
            }
        }

        clonedEl.html( areaHTML );
        clonedEl.html( clonedEl.html().replace( this.cursorPlaceholder, phcode ) );
        restoreSelection();
        area.html( clonedEl.html().replace( this.cursorPlaceholder, '' ).replace( /\[\*\*\[(.*?)\]\*\*\]/gi, "<$1>" ) );

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
        if(typeof UI.currentSegment != 'undefined') UI.pointToOpenSegment();
        this.custom.extended_tagmode = true;
        this.saveCustomization();
    },
    setCrunchedTagMode: function () {
        this.body.removeClass('tagmode-default-extended');
        if(typeof UI.currentSegment != 'undefined') UI.pointToOpenSegment();
        this.custom.extended_tagmode = false;
        this.saveCustomization();
    },

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
        if(UI.editarea.html() == '') return false;

        selection = window.getSelection();
        if(selection.rangeCount < 1) return false;
        range = selection.getRangeAt(0);
        if(!range.collapsed) return true;
        nextEl = $(range.endContainer.nextElementSibling);
        prevEl = $(range.endContainer.previousElementSibling);
        tempRange = range;
        UI.editarea.find('.test-invisible').remove();
        pasteHtmlAtCaret('<span class="test-invisible"></span>');
        var coso = $.parseHTML(UI.editarea.html());
        $.each(coso, function (index) {
            if($(this).hasClass('test-invisible')) {
                UI.numCharsUntilTagRight = 0;
                UI.numCharsUntilTagLeft = 0;
                nearTagOnRight = UI.nearTagOnRight(index+1, coso);
                nearTagOnLeft = UI.nearTagOnLeft(index-1, coso);

                if((typeof nearTagOnRight != 'undefined')&&(nearTagOnRight)) {//console.log('1');
                    UI.removeHighlightCorrespondingTags();
                    UI.highlightCorrespondingTags($(UI.editarea.find('.locked')[indexTags]));
                } else if((typeof nearTagOnLeft != 'undefined')&&(nearTagOnLeft)) {//console.log('2');
                    UI.removeHighlightCorrespondingTags();
                    UI.highlightCorrespondingTags($(UI.editarea.find('.locked')[indexTags]));
                } else {
                    UI.removeHighlightCorrespondingTags();
                }

                UI.numCharsUntilTagRight = null;
                UI.numCharsUntilTagLeft = null;
                UI.editarea.find('.test-invisible').remove();
                UI.editarea.get(0).normalize();
                return false;
            }
        });


    },
    highlightCorrespondingTags: function (el) {
        var pairEl;
        if(el.hasClass('startTag')) {
            if(el.next('.endTag').length) {
                el.next('.endTag').addClass('highlight');
            } else {
                num = 1;
                ind = 0;
                $(el).nextAll('.locked').each(function () {
                    ind++;
                    if($(this).hasClass('startTag')) {
                        num++;
                    } else if($(this).hasClass('selfClosingTag')) {

                    } else { // end tag
                        num--;
                        if(num == 0) {
                            pairEl = $(this);
                            return false;
                        }
                    }

                });
                if (pairEl) {
                    $(pairEl).addClass('highlight');
                }


            }
        } else if(el.hasClass('endTag')) {
            if(el.prev('.startTag').length) {
                el.prev('.startTag').first().addClass('highlight');
            } else {
                num = 1;
                ind = 0;
                $(el).prevAll('.locked').each(function () {
                    ind++;
                    if($(this).hasClass('endTag')) {
                        num++;
                    } else if($(this).hasClass('selfClosingTag')) {

                    } else { // end tag
                        num--;
                        if(num == 0) {
                            pairEl = $(this);
                            return false;
                        }
                    }

                });
                if (pairEl) {
                    $(pairEl).addClass('highlight');
                }
            }
        }
        $(el).addClass('highlight');
    },
    removeHighlightCorrespondingTags: function () {
        $(UI.editarea).find('.locked.highlight').removeClass('highlight');
    },

    // TAG MISMATCH
	markTagMismatch: function(d) {

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
	checkAutocompleteTags: function() {
		added = this.getPartialTagAutocomplete();
		$('.tag-autocomplete li.hidden').removeClass('hidden');
		$('.tag-autocomplete li').each(function() {
			var str = $(this).text();
			if( str.substring(0, added.length) === added ) {
				$(this).removeClass('hidden');
			} else {
				$(this).addClass('hidden');	
			}
		});
		if(!$('.tag-autocomplete li:not(.hidden)').length) { // no tags matching what the user is writing

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
		var added = UI.editarea.html().match(/&lt;(?:[a-z]*(?:&nbsp;)*["\w\s\/=]*)?<span class="tag-autocomplete-endcursor">/gi);
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
	jumpTag: function(range) {
		if(typeof range.endContainer.data != 'undefined') {
            if((range.endContainer.data.length == range.endOffset)&&(range.endContainer.nextElementSibling.className == 'monad')) {
                setCursorAfterNode(range, range.endContainer.nextElementSibling);
            }
        }
	},

    hasSourceOrTargetTags: function ( segment ) {
        return ( $(segment).find( '.locked' ).length > 0 || UI.sourceTags.length > 0 )
    },
    hasMissingTargetTags: function ( segment ) {
        if ( segment.length == 0 ) return ;
        var regExp = this.getXliffRegExpression();
        var sourceTags = $( '.source', segment ).html()
            .match( regExp );

        var targetTags = $( '.target', segment ).html()
            .match( regExp );

        return ( $( sourceTags ).not( targetTags ).length > 0 )

    },
    /**
     * Check if the data-original attribute in the source of the segment contains special tags (Ex: <g id=1></g>z)
     * (Note that in the data-original attribute there are the &amp;lt instead of &lt)
     * @param segment
     * @returns {boolean}
     */
    hasDataOriginalTags: function (segment) {
        var originalText = $(segment).find('.source').data('original');
        var reg = new RegExp(/(&amp;lt;\s*\/*\s*(g|x|bx|ex|bpt|ept|ph|it|mrk)\s*.*?&amp;gt;)/gmi);
        if (!_.isUndefined(originalText) && reg.test(originalText)) {
            return true;
        }
        return false;
    },
    /**
     * Remove all xliff source tags from the string
     * @param currentString : the string to parse
     * @returns the decoded String
     */
    removeAllTags: function (currentString) {
        if (currentString) {
            var regExp = this.getXliffRegExpression();
            return currentString.replace(regExp, '');
        } else {
            return '';
        }
    },
    /**
     *  Return the Regular expression to match all xliff source tags
     */
    getXliffRegExpression: function () {
        return /(&lt;\s*\/*\s*(g|x|bx|ex|bpt|ept|ph|it|mrk)\s*.*?&gt;)/gmi;
    },
    checkXliffTagsInText: function (text) {
        var reg = this.getXliffRegExpression();
        return reg.test(text);
    }

});


