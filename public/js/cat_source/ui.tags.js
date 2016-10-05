/*
	Component: ui.tags
 */


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
        SegmentActions.updateAllSegments();

	},
	enableTagMark: function() {
		this.taglockEnabled = true;
		this.body.removeClass('tagmarkDisabled');
        SegmentActions.updateAllSegments();
	},
    //TODO This method do the same of UI.transformTextForLockTags that receive the text not the segment
	markSuggestionTags: function(segment) {
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

    detectTagType: function (area) {
        if (!this.taglockEnabled || config.tagLockCustomizable ) {
            return false;
        }
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

    toggleTagsMode: function (elem) {
        if (elem) {
            $(elem).toggleClass('active');
        }
        if (UI.body.hasClass('tagmode-default-extended')) {
            this.setCrunchedTagMode();
        } else {
            this.setExtendedTagMode();
        }
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

        var selection = window.getSelection();
        if(selection.rangeCount < 1) return false;
        var range = selection.getRangeAt(0);
        if(!range.collapsed) return true;
        UI.editarea.find('.test-invisible').remove();
        pasteHtmlAtCaret('<span class="test-invisible"></span>');
        var htmlEditarea = $.parseHTML(UI.editarea.html());
        $.each(htmlEditarea, function (index) {
            if($(this).hasClass('test-invisible')) {
                UI.numCharsUntilTagRight = 0;
                UI.numCharsUntilTagLeft = 0;
                var nearTagOnRight = UI.nearTagOnRight(index+1, htmlEditarea);
                var nearTagOnLeft = UI.nearTagOnLeft(index-1, htmlEditarea);

                if( (typeof nearTagOnRight != 'undefined') && (nearTagOnRight) ||
                    (typeof nearTagOnLeft != 'undefined')&&(nearTagOnLeft)) {
                    UI.highlightCorrespondingTags($(UI.editarea.find('.locked')[indexTags]));
                }
                UI.removeHighlightCorrespondingTags();

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
		var added = this.getPartialTagAutocomplete();
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


