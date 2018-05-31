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
		this.tagLockEnabled = false;
        SegmentActions.disableTagLock();

	},
	enableTagMark: function() {
		this.tagLockEnabled = true;
        SegmentActions.enableTagLock();
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


    transformTextForLockTags: function ( tx ) {
        var brTx1 = "<_plh_ contenteditable=\"false\" class=\"locked\">$1</_plh_>";
        var brTx2 =  "<span contenteditable=\"false\" class=\"locked\">$1</span>";

        tx = tx.replace( /&amp;/gi, "&" )
            .replace( /<span/gi, "<_plh_" )
            .replace( /<\/span/gi, "</_plh_" )
            .replace( /&lt;/gi, "<" )
            .replace( /(<(ph.*?)\s*?\/&gt;)/gi, brTx1 )
            .replace( /(<(g|x|bx|ex|bpt|ept|ph.*?|it|mrk)\sid[^<“]*?&gt;)/gi, brTx1 )
            .replace( /(<(ph.*?)\sid[^<“]*?\/>)/gi, brTx1 )
            .replace( /</gi, "&lt;" )
            .replace( /\&lt;_plh_/gi, "<span" )
            .replace( /\&lt;\/_plh_/gi, "</span" )
            .replace( /\&lt;lxqwarning/gi, "<lxqwarning" )
            .replace( /\&lt;\/lxqwarning/gi, "</lxqwarning" )
            .replace( /\&lt;div\>/gi, "<div>" )
            .replace( /\&lt;\/div\>/gi, "</div>" )
            .replace( /\&lt;br\>/gi, "<br />" )
            .replace( /\&lt;br \/>/gi, "<br />" )
            .replace( /\&lt;mark/gi, "<mark" )
            .replace( /\&lt;\/mark/gi, "</mark" )
            .replace( /\&lt;ins/gi, "<ins" ) // For translation conflicts tab
            .replace( /\&lt;\/ins/gi, "</ins" ) // For translation conflicts tab
            .replace( /\&lt;del/gi, "<del" ) // For translation conflicts tab
            .replace( /\&lt;\/del/gi, "</del" ) // For translation conflicts tab
            .replace( /\&lt;br class=["\'](.*?)["\'][\s]*[\/]*(\&gt;|\>)/gi, '<br class="$1" />' )
            .replace( /(&lt;\s*\/\s*(g|x|bx|ex|bpt|ept|ph|it|mrk)\s*&gt;)/gi, brTx2 );


        tx = tx.replace( /(<span contenteditable="false" class="[^"]*"\>)(:?<span contenteditable="false" class="[^"]*"\>)(.*?)(<\/span\>){2}/gi, "$1$3</span>" );
        tx = tx.replace( /(<\/span\>)$(\s){0,}/gi, "</span> " );
        tx = this.transformTagsWithHtmlAttribute(tx);
        // tx = tx.replace( /(<\/span\>\s)$/gi, "</span><br class=\"end\">" );  // This to show the cursor after the last tag, moved to editarea component
        return tx;
    },

    /**
     * Used to transform special ph tags that may contain html within the equiv-text attribute.
     * Example: &lt;ph id="2" equiv-text="base64:Jmx0O3NwYW4gY2xhc3M9JnF1b3Q7c3BhbmNsYXNzJnF1b3Q7IGlkPSZxdW90OzEwMDAmcXVvdDsgJmd0Ow=="/&gt;
     * The attribute is encoded in base64
     * @param tx
     * @returns {*}
     */
    transformTagsWithHtmlAttribute: function (tx) {
        try {
            var base64Array=[];
            var phIDs =[];
            tx = tx.replace( /&quot;/gi, '"' );

            tx = tx.replace( /&lt;ph.*?id="(.*?)"/gi, function (match, text) {
                phIDs.push(text);
                return match;
            });

            tx = tx.replace( /&lt;ph.*?equiv-text="base64:.*?"(.*?\/&gt;)/gi, function (match, text) {
                return match.replace(text, "<span contenteditable='false' class='locked tag-html-container-close' contenteditable='false'>\"" + text + "</span>");
            });
            tx = tx.replace( /base64:(.*?)"/gi , function (match, text) {
                base64Array.push(text);
                var id = phIDs.shift();
                return "<span contenteditable='false' class='locked inside-attribute' contenteditable='false' data-original='base64:" + text+ "'><a>("+ id + ")</a>" + Base64.decode(text) + "</span>";
            });
            tx = tx.replace( /(&lt;ph.*?equiv-text=")/gi, function (match, text) {
                var base = base64Array.shift();
                return "<span contenteditable='false' class='locked tag-html-container-open' contenteditable='false'>" + text + "base64:" + base + "</span>";
            });
            delete(base64);
            return tx;
        } catch (e) {
            console.error("Error parsing tag ph in transformTagsWithHtmlAttribute function");
        }


    },
    /**
     * To transform text with the' ph' tags that have the attribute' equival-text' into text only, without html
     */
    removePhTagsWithEquivTextIntoText: function ( tx ) {
        try {
            tx = tx.replace( /&quot;/gi, '"' );

            tx = tx.replace( /&lt;ph.*?equiv-text="base64:.*?(\/&gt;)/gi, function (match, text) {
                return match.replace(text, "");
            });
            tx = tx.replace( /&lt;ph.*?equiv-text="base64:.*?(\/>)/gi, function (match, text) {
                return match.replace(text, "");
            });
            tx = tx.replace( /(&lt;ph.*?equiv-text=")/gi, function (match, text) {
                return "";
            });
            tx = tx.replace( /base64:(.*?)"/gi , function (match, text) {
                return Base64.decode(text);
            });
            return tx;
        } catch (e) {
            console.error("Error parsing tag ph in removePhTagsWithEquivTextIntoText function");
        }
    },

    evalCurrentSegmentTranslationAndSourceTags : function( segment ) {
        if ( segment.length == 0 ) return ;

        var sourceTags = htmlDecode($('.source', segment).data('original'))
            .match(/(&lt;\s*\/*\s*(g|x|bx|ex|bpt|ept|ph|it|mrk)\s*.*?&gt;)/gi);
        this.sourceTags = sourceTags || [];
        this.currentSegmentTranslation = segment.find( UI.targetContainerSelector() ).text();
    },

    detectTagType: function (area) {
        if (!this.tagLockEnabled || config.tagLockCustomizable ) {
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

    toggleTagsMode: function () {
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
        $(".tagModeToggle").addClass('active');
        if(typeof UI.currentSegment != 'undefined') UI.pointToOpenSegment();
        this.custom.extended_tagmode = true;
        this.saveCustomization();
    },
    setCrunchedTagMode: function () {
        this.body.removeClass('tagmode-default-extended');
        $(".tagModeToggle").removeClass('active');
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
        if(!UI.editarea || UI.editarea.html() == '') return false;

        var selection = window.getSelection();
        if(selection.rangeCount < 1) return false;
        var range = selection.getRangeAt(0);
        if(!range.collapsed) return true;
        UI.editarea.find('.test-invisible').remove();
        pasteHtmlAtCaret('<span class="test-invisible"></span>');
        var htmlEditarea = $.parseHTML(UI.editarea.html());
        if (htmlEditarea) {
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
        }
        // TODO test.inivisible break some doms with text
        $('body').find('.test-invisible').remove();


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

        if( !_.isUndefined(d.tag_mismatch.source) && d.tag_mismatch.source.length > 0 ) {
            $.each(d.tag_mismatch.source, function(index) {
                $('#segment-' + d.id_segment + ' .source span.locked:not(.temp)').filter(function() {
                    var clone = $(this).clone();
                    clone.find('.inside-attribute').remove();
                    return htmlEncode(clone.text()) === d.tag_mismatch.source[index];
                }).last().addClass('temp');
            });
        }
        if( !_.isUndefined(d.tag_mismatch.target) && d.tag_mismatch.target.length > 0 ) {
            $.each(d.tag_mismatch.target, function(index) {
                $('#segment-' + d.id_segment + ' .editarea span.locked:not(.temp)').filter(function() {
                    var clone = $(this).clone();
                    clone.find('.inside-attribute').remove();
                    return htmlEncode(clone.text()) === d.tag_mismatch.target[index];
                }).last().addClass('temp');
            });
        }

        $('#segment-' + d.id_segment + ' span.locked.mismatch').addClass('mismatch-old').removeClass('mismatch');
        $('#segment-' + d.id_segment + ' span.locked.temp').addClass('mismatch').removeClass('temp');
        $('#segment-' + d.id_segment + ' span.locked.mismatch-old').removeClass('mismatch-old');

        if( !_.isUndefined(d.tag_mismatch.order) && d.tag_mismatch.order.length > 0 ) {
            $( '#segment-' + d.id_segment + ' .editarea .locked' ).filter( function () {
                var clone = $( this ).clone();
                clone.find( '.inside-attribute' ).remove();
                return htmlEncode(clone.text()) === d.tag_mismatch.order[0];
            } ).addClass( 'order-error' );
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
        var self = this;
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
        $.each(UI.sourceTags, function(index, text) {
            var textDecoded = UI.transformTextForLockTags(text);
            $('.tag-autocomplete ul').append('<li' + ((index === 0)? ' class="current"' : '') + ' data-original="' + text + '">' + textDecoded + '</li>');
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
        return ( $(segment).find( '.locked' ).length > 0 || (UI.sourceTags && UI.sourceTags.length > 0) )
    },
    hasMissingTargetTags: function ( segment ) {
        if ( segment.length == 0 ) return ;
        var regExp = this.getXliffRegExpression();
        var sourceTags = $( '.source', segment ).html()
            .match( regExp );

        var targetTags = $( '.targetarea', segment ).html()
            .match( regExp );

        return $(sourceTags).length > $(targetTags).length ;

    },

    /**
     *
     */
    autoFillTagsInTarget: function (  ) {
        //get source tags from the segment
        var sourceClone = $( '.source', UI.currentSegment ).clone();
        sourceClone.find('.locked.inside-attribute').remove();
        var sourceTags = sourceClone.html()
            .match( /(&lt;\s*\/*\s*(g|x|bx|ex|bpt|ept|ph|it|mrk)\s*.*?&gt;)/gi );

        //get target tags from the segment
        var targetClone =  $( '.targetarea', UI.currentSegment ).clone();
        targetClone.find('.locked.inside-attribute').remove();
        var targetTags = targetClone.html()
            .match( /(&lt;\s*\/*\s*(g|x|bx|ex|bpt|ept|ph|it|mrk)\s*.*?&gt;)/gi );

        if(targetTags == null ) {
            targetTags = [];
        } else {
            targetTags = targetTags.map(function(elem) {
                return elem.replace(/<\/span>/gi, "").replace(/<span.*?>/gi, "");
            });
        }

        var missingTags = sourceTags.map(function(elem) {
            return elem.replace(/<\/span>/gi, "").replace(/<span.*?>/gi, "");
        });
        //remove from source tags all the tags in target segment
        for(var i = 0; i < targetTags.length; i++ ){
            var pos = missingTags.indexOf(targetTags[i]);
            if( pos > -1){
                missingTags.splice(pos,1);
            }
        }

        var undoCursorPlaceholder = $('.undoCursorPlaceholder', UI.currentSegment ).detach();
        var brEnd = $('br.end', UI.currentSegment ).detach();

        var newhtml = UI.editarea.html();
        //add tags into the target segment
        for(var i = 0; i < missingTags.length; i++){
            if ( !(config.tagLockCustomizable && !this.tagLockEnabled) ) {
                newhtml = newhtml + UI.transformTextForLockTags(missingTags[i]);
            } else {
                newhtml = newhtml + missingTags[i];
            }
        }
        SegmentActions.replaceEditAreaTextContent(UI.getSegmentId(UI.editarea), UI.getSegmentFileId(UI.editarea), newhtml);
        //add again undoCursorPlaceholder
        UI.editarea.append(undoCursorPlaceholder );
        // .append(brEnd);

        //lock tags and run again getWarnings
        UI.segmentQA(UI.currentSegment);
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
    },
    /**
     * Call from UI. events when clicking on a menu item to add tags
     * @param tag: the jquery object of the chosen tag
     */
    chooseTagAutocompleteOption: function ($tag) {
        if(!$('.rangySelectionBoundary', UI.editarea).length) { // click, not keypress
            setCursorPosition($(".tag-autocomplete-endcursor", UI.editarea)[0]);
        }
        saveSelection();

        // Todo: refactor this part
        var editareaClone = UI.editarea.clone();
        editareaClone.html(editareaClone.html().replace(/<span class="tag-autocomplete-endcursor"><\/span>&lt;/gi, '&lt;<span class="tag-autocomplete-endcursor"></span>'));
        editareaClone.find('.rangySelectionBoundary').before(editareaClone.find('.rangySelectionBoundary + .tag-autocomplete-endcursor'));
        editareaClone.html(editareaClone.html().replace(/&lt;(?:[a-z]*(?:&nbsp;)*["<\->\w\s\/=]*)?(<span class="tag-autocomplete-endcursor">)/gi, '$1'));
        editareaClone.html(editareaClone.html().replace(/&lt;(?:[a-z]*(?:&nbsp;)*["\w\s\/=]*)?(<span class="tag-autocomplete-endcursor"\>)/gi, '$1'));
        editareaClone.html(editareaClone.html().replace(/&lt;(?:[a-z]*(?:&nbsp;)*["\w\s\/=]*)?(<span class="undoCursorPlaceholder monad" contenteditable="false"><\/span><span class="tag-autocomplete-endcursor"\>)/gi, '$1'));
        editareaClone.html(editareaClone.html().replace(/(<span class="tag-autocomplete-endcursor"\><\/span><span class="undoCursorPlaceholder monad" contenteditable="false"><\/span>)&lt;/gi, '$1'));
        editareaClone.html(editareaClone.html().replace(/(<span class="tag-autocomplete-endcursor"\>.+<\/span><span class="undoCursorPlaceholder monad" contenteditable="false"><\/span>)&lt;/gi, '$1'));

        var ph = "";
        if($('.rangySelectionBoundary', editareaClone).length) { // click, not keypress
            ph = $('.rangySelectionBoundary', editareaClone)[0].outerHTML;
        }

        $('.rangySelectionBoundary', editareaClone).remove();
        $('.rangySelectionBoundary', $tag).remove();
        $('br.end', $tag).remove();
        $('.tag-autocomplete-endcursor', editareaClone).after(ph);
        $('.tag-autocomplete-endcursor', editareaClone).before($tag.html());
        $('.tag-autocomplete, .tag-autocomplete-endcursor', editareaClone).remove();
        UI.closeTagAutocompletePanel();
        SegmentActions.replaceEditAreaTextContent(UI.getSegmentId(UI.currentSegment), UI.getSegmentFileId(UI.currentSegment), editareaClone.html());
        setTimeout(function () {
            restoreSelection();
        });
        UI.segmentQA(UI.currentSegment);
    },
    /**
     *
     * This function is used before the text is sent to the server or to transform editArea content.
     * @return Return a cloned element without tag inside
     *
     * @param context
     * @param selector
     * @returns {*|jQuery}
     */
    postProcessEditarea: function(context, selector) {
        selector = (typeof selector === "undefined") ? UI.targetContainerSelector() : selector;
        var area = $( selector, context ).clone();
        area = this.transformPlaceholdersHtml(area);

        area.find('span.space-marker').replaceWith(' ');
        area.find('span.rangySelectionBoundary, span.undoCursorPlaceholder').remove();
        area = this.encodeTagsWithHtmlAttribute(area);
        return area.text();
    },

    prepareTextToSend: function (text) {
        var div =  document.createElement('div');
        var $div = $(div);
        $div.html(text);
        $div = this.transformPlaceholdersHtml($div);

        $div.find('span.space-marker').replaceWith(' ');
        $div.find('span.rangySelectionBoundary, span.undoCursorPlaceholder').remove();
        $div = this.encodeTagsWithHtmlAttribute($div);
        return $div.text();
    },

    /**
     * It does the same as postProcessEditarea function but does not remove the cursor span
     * @param text
     * @returns {*}
     */

    cleanTextFromPlaceholdersSpan: function (text) {
        var div =  document.createElement('div');
        var $div = $(div);
        $div.html(text);
        div = this.transformPlaceholdersHtml($div);
        $div.find('span.space-marker').replaceWith(' ');
        $div = this.encodeTagsWithHtmlAttribute($div);
        return $div.text();
    },

    transformPlaceholdersHtml: function ($elem) {
        var divs = $elem.find( 'div' );

        if( divs.length ){
            divs.each(function(){
                $(this).find( 'br:not([class])' ).remove();
                $(this).prepend( $('<span class="placeholder">' + config.crPlaceholder + '</span>' ) ).replaceWith( $(this).html() );
            });
        } else {
            $elem.find( 'br:not([class])' ).replaceWith( $('<span class="placeholder">' + config.crPlaceholder + '</span>') );
            $elem.find('br.' + config.crlfPlaceholderClass).replaceWith( '<span class="placeholder">' + config.crlfPlaceholder + '</span>' );
            $elem.find('span.' + config.lfPlaceholderClass).replaceWith( '<span class="placeholder">' + config.lfPlaceholder + '</span>' );
            $elem.find('span.' + config.crPlaceholderClass).replaceWith( '<span class="placeholder">' + config.crPlaceholder + '</span>' );
        }

        $elem.find('span.' + config.tabPlaceholderClass).replaceWith(config.tabPlaceholder);
        $elem.find('span.' + config.nbspPlaceholderClass).replaceWith(config.nbspPlaceholder);

        return $elem;
    },

    /**
     * This function is called to return the tag inside ph attribute 'equiv-text' to base64
     * @param $elem
     * @returns {*}
     */
    encodeTagsWithHtmlAttribute: function ($elem) {
        $elem.find('.inside-attribute').remove();
        return $elem;
    },

    handleSourceCopyEvent: function ( e ) {
        var elem = $(e.target);
        if ( elem.hasClass('inside-attribute') || elem.parent().hasClass('inside-attribute') ) {
            var tag = (elem.hasClass('inside-attribute')) ? elem.parent('span.locked') : elem.parent().parent('span.locked');
            var cloneTag = tag.clone();
            cloneTag.find('.inside-attribute').remove();
            var text = cloneTag.text();
            e.clipboardData.setData('text/plain', text);
            e.preventDefault();
        }
    },
    handleDragEvent: function ( e ) {
        var elem = $(e.target);
        if ( elem.hasClass('inside-attribute') || elem.parent().hasClass('inside-attribute') ) {
            var tag = elem.parent('span.locked:not(.inside-attribute)');
            var cloneTag = tag.clone();
            cloneTag.find('.inside-attribute').remove();
            var text = htmlEncode(cloneTag.text());
            e.dataTransfer.setData('text/plain', UI.transformTextForLockTags(text));
            e.dataTransfer.setData('text/html', UI.transformTextForLockTags(text));
        } else if (elem.hasClass('locked')) {
            var text = htmlEncode(elem.text());
            e.dataTransfer.setData('text/plain', UI.transformTextForLockTags(text));
            e.dataTransfer.setData('text/html', UI.transformTextForLockTags(text));
        }
    },
    /**
     * When you click on a tag, it is selected and the selected class is added (ui.events->382).
     * Clicking on the edititarea to remove the tags with the selected class that otherwise are
     * removed the first time you press the delete key (ui.editarea-> 51 )
     */
    removeSelectedClassToTags: function (  ) {
        UI.editarea.find('.locked.selected').removeClass('selected');
    }

});


