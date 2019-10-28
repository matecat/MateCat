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

    /**
     * Called when a Segment string returned by server has to be visualized, it replace placeholders with tags
     * @param str
     * @returns {XML|string}
     */
    decodePlaceholdersToText: function (str) {
        if(!UI.hiddenTextEnabled) return str;
        var _str = str;
        if(UI.markSpacesEnabled) {
            if(jumpSpacesEncode) {
                _str = this.encodeSpacesAsPlaceholders(htmlDecode(_str), true);
            }
        }

        _str = _str.replace( config.lfPlaceholderRegex, '<span class="monad marker softReturn ' + config.lfPlaceholderClass +'" contenteditable="false"><br /></span>' )
            .replace( config.crPlaceholderRegex, '<span class="monad marker softReturn ' + config.crPlaceholderClass +'" contenteditable="false"><br /></span>' )
        _str = _str.replace( config.lfPlaceholderRegex, '<span class="monad marker softReturn ' + config.lfPlaceholderClass +'" contenteditable="false"><br /></span>' )
            .replace( config.crPlaceholderRegex, '<span class="monad marker softReturn ' + config.crPlaceholderClass +'" contenteditable="false"><br /></span>' )
            .replace( config.crlfPlaceholderRegex, '<br class="' + config.crlfPlaceholderClass +'" />' )
            .replace( config.tabPlaceholderRegex, '<span class="tab-marker monad marker ' + config.tabPlaceholderClass +'" contenteditable="false">&#8677;</span>' )
            .replace( config.nbspPlaceholderRegex, '<span class="nbsp-marker monad marker ' + config.nbspPlaceholderClass +'" contenteditable="false">&nbsp;</span>' )
            .replace(/(<\/span\>)$/gi, "</span><br class=\"end\">"); // For rangy cursor after a monad marker

        return _str;
    },

    encodeSpacesAsPlaceholders: function(str, root) {
        var newStr = '';
        $.each($.parseHTML(str), function() {

            if(this.nodeName == '#text') {
                newStr += $(this).text().replace(/\s/gi, '<span class="space-marker marker monad" contenteditable="false"> </span>');
            } else {
                match = this.outerHTML.match(/<.*?>/gi);
                if(match.length == 1) { // se è 1 solo, è un tag inline

                } else if(match.length == 2) { // se sono due, non ci sono tag innestati
                    newStr += htmlEncode(match[0]) + this.innerHTML.replace(/\s/gi, '#@-lt-@#span#@-space-@#class="space-marker#@-space-@#marker#@-space-@#monad"#@-space-@#contenteditable="false"#@-gt-@# #@-lt-@#/span#@-gt-@#') + htmlEncode(match[1]);
                } else { // se sono più di due, ci sono tag innestati

                    newStr += htmlEncode(match[0]) + UI.encodeSpacesAsPlaceholders(this.innerHTML) + htmlEncode(match[1], false);

                }
            }
        });
        if(root) {
            newStr = newStr.replace(/#@-lt-@#/gi, '<').replace(/#@-gt-@#/gi, '>').replace(/#@-space-@#/gi, ' ');
        }
        return newStr;
    },

    transformTextForLockTags: function ( tx ) {
        var brTx1 = "<_plh_ contenteditable=\"false\" class=\"locked style-tag \">$1</_plh_>";
        var brTx2 =  "<span contenteditable=\"false\" class=\"locked style-tag\">$1</span>";

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
            .replace( /\&lt;mark /gi, "<mark " )
            .replace( /\&lt;\/mark/gi, "</mark" )
            .replace( /\&lt;ins /gi, "<ins " ) // For translation conflicts tab
            .replace( /\&lt;\/ins/gi, "</ins" ) // For translation conflicts tab
            .replace( /\&lt;del /gi, "<del " ) // For translation conflicts tab
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
        var returnValue = tx;
        try {
            if (tx.indexOf('locked-inside') > -1) return tx;
            var base64Array=[];
            var phIDs =[];
            tx = tx.replace( /&quot;/gi, '"' );

            tx = tx.replace( /&lt;ph.*?id="(.*?)".*?&gt/gi, function (match, text) {
                phIDs.push(text);
                return match;
            });

            tx = tx.replace( /&lt;ph.*?equiv-text="base64:.*?"(.*?\/&gt;)/gi, function (match, text) {
                return match.replace(text, "<span contenteditable='false' class='locked locked-inside tag-html-container-close' >\"" + text + "</span>");
            });
            tx = tx.replace( /base64:(.*?)"/gi , function (match, text) {
                if ( phIDs.length === 0 ) return text;
                base64Array.push(text);
                var id = phIDs.shift();
                return "<span contenteditable='false' class='locked locked-inside inside-attribute' data-original='base64:" + text+ "'><a>("+ id + ")</a>" + Base64.decode(text) + "</span>";
            });
            tx = tx.replace( /(&lt;ph.*?equiv-text=")/gi, function (match, text) {
                if ( base64Array.length === 0 ) return text;
                var base = base64Array.shift();
                return "<span contenteditable='false' class='locked locked-inside tag-html-container-open' >" + text + "base64:" + base + "</span>";
            });
            // delete(base64Array);
            returnValue = tx;
        } catch (e) {
            console.error("Error parsing tag ph in transformTagsWithHtmlAttribute function");
            returnValue = "";
        } finally {
            return returnValue;
        }


    },
    /**
     * To transform text with the' ph' tags that have the attribute' equiv-text' into text only, without html
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
        $('span.locked:not(.locked-inside)', area).each(function () {
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
        if (UI.body.hasClass('tagmode-default-compressed')) {
            this.setExtendedTagMode();
        } else {
            this.setCrunchedTagMode();
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
        this.body.removeClass('tagmode-default-compressed');
        $(".tagModeToggle").addClass('active');
        if(typeof UI.currentSegment != 'undefined') UI.pointToOpenSegment();
        this.custom.extended_tagmode = true;
        this.saveCustomization();
    },
    setCrunchedTagMode: function () {
        this.body.addClass('tagmode-default-compressed');
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

    markSelectedTag: function($tag) {
        var elem = $tag.hasClass('locked') && !$tag.hasClass('inside-attribute')? $tag : $tag.closest('.locked:not(.inside-attribute)');
        if( elem.hasClass('selected') ) {
            elem.removeClass('selected');
            setCursorPosition(elem[0], 'end');
        } else {
            setCursorPosition(elem[0]);
            selectText(elem[0]);
            UI.removeSelectedClassToTags();
            elem.addClass('selected');
            if(UI.body.hasClass('tagmode-default-compressed')) {
                $('.editor .tagModeToggle').click();
            }
        }
        if ( elem.closest('.source').length > 0 ) {
            UI.removeHighlightCorrespondingTags(elem.closest('.source'));
            UI.highlightCorrespondingTags(elem);
            UI.highlightEquivalentTaginSourceOrTarget(elem.closest('.source'), UI.editarea);
        } else {
            UI.checkTagProximityFn();
        }
    },

    checkTagProximityFn: function () {
        if(!UI.editarea || UI.editarea.html() == '') return false;

        var selection = window.getSelection();
        if(selection.rangeCount < 1) return false;
        var range = selection.getRangeAt(0);
        UI.editarea.find('.temp-highlight-tags').remove();
        if(!range.collapsed) {
            if ( UI.editarea.find( '.locked.selected' ).length > 0 ) {
                UI.editarea.find( '.locked.selected' ).after('<span class="temp-highlight-tags"/>');
            } else {
                return true
            }
        } else {
            pasteHtmlAtCaret('<span class="temp-highlight-tags"/>');
        }
        var htmlEditarea = $.parseHTML(UI.editarea.html());
        if (htmlEditarea) {
            UI.removeHighlightCorrespondingTags(UI.editarea);
            $.each(htmlEditarea, function (index) {
                if($(this).hasClass('temp-highlight-tags')) {
                    UI.numCharsUntilTagRight = 0;
                    UI.numCharsUntilTagLeft = 0;
                    var nearTagOnRight = UI.nearTagOnRight(index+1, htmlEditarea);
                    var nearTagOnLeft = UI.nearTagOnLeft(index-1, htmlEditarea);

                    if( (typeof nearTagOnRight != 'undefined') && (nearTagOnRight) ||
                        (typeof nearTagOnLeft != 'undefined')&&(nearTagOnLeft)) {
                        UI.highlightCorrespondingTags($(UI.editarea.find('.locked:not(.locked-inside)')[indexTags]));
                    }

                    UI.numCharsUntilTagRight = null;
                    UI.numCharsUntilTagLeft = null;
                    UI.editarea.find('.temp-highlight-tags').remove();
                    UI.editarea.get(0).normalize();
                    return false;
                }
            });
        }
        $('body').find('.temp-highlight-tags').remove();
        UI.highlightEquivalentTaginSourceOrTarget(UI.editarea, UI.currentSegment.find('.source'));
    },
    /**
     * Search in container for a highlighted tad and switch on the corresponding
     * tag in source or target
     * @param containerSearch The container where to search for the tag
     * @param containerHighlight
     */
    highlightEquivalentTaginSourceOrTarget: function(containerSearch, containerHighlight) {
        UI.removeHighlightCorrespondingTags(containerHighlight);
        var highlightedTag = containerSearch.find('.startTag.locked.highlight, .selfClosingTag.locked.highlight');
        if ( highlightedTag.length > 0 ) {
            var sourceTag, text;
            if ( highlightedTag.find('.locked-inside').length > 0 ) {
                text = highlightedTag.find('.inside-attribute').text();
                sourceTag = containerHighlight.find('span.inside-attribute:contains('+text+')').parent();
            } else {
                text = $(highlightedTag.get(0)).text();
                sourceTag = containerHighlight.find('span.locked:contains('+text+')');
            }
            UI.highlightCorrespondingTags(sourceTag);
        }
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

    removeHighlightCorrespondingTags: function (segment$) {
        segment$.find('.locked.highlight').removeClass('highlight');
    },

    removeHighlightErrorsTags: function (segment$) {
        segment$.find('.locked.mismatch').removeClass('mismatch');
        segment$.find('.locked.order-error').removeClass('order-error');
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
        // ??
        $('#segment-' + d.id_segment + ' span.locked.mismatch').addClass('mismatch-old').removeClass('mismatch');
        $('#segment-' + d.id_segment + ' span.locked.temp').addClass('mismatch').removeClass('temp');
        $('#segment-' + d.id_segment + ' span.locked.mismatch-old').removeClass('mismatch-old');

        $('#segment-' + d.id_segment + ' .editarea span.locked:not(.temp)').removeClass( 'order-error' )
        if( !_.isUndefined(d.tag_mismatch.order) && d.tag_mismatch.order.length > 0 ) {
            $( '#segment-' + d.id_segment + ' .editarea .locked:not(.mismatch)' ).filter( function () {
                var clone = $( this ).clone();
                clone.find( '.inside-attribute' ).remove();
                return htmlEncode(clone.text()) === d.tag_mismatch.order[0];
            } ).addClass( 'order-error' );
        }
	},	

	closeTagAutocompletePanel: function() {
        SegmentActions.closeTagsMenu();
		$('.tag-autocomplete-endcursor').remove();
	},

    hasSourceOrTargetTags: function ( segment ) {
        return ((UI.sourceTags && UI.sourceTags.length > 0 || $(segment).find( '.locked' ).length > 0 ) )
    },
    hasMissingTargetTags: function ( segment ) {
        if ( segment.length == 0 ) return ;
        var regExp = this.getXliffRegExpression();
        var sourceTags = $( '.source', segment ).html()
            .match( regExp );
        if ( $(sourceTags).length === 0 ) {
            return false;
        }
        var targetTags = $( '.targetarea', segment ).html()
            .match( regExp );

        return $(sourceTags).length > $(targetTags).length || !_.isEqual(sourceTags.sort(), targetTags.sort());

    },
    /**
     * Add at the end of the target the missing tags
     */
    autoFillTagsInTarget: function (  ) {
        //get source tags from the segment
        var sourceClone = $( '.source', UI.currentSegment ).clone();
        //Remove inside-attribute for ph with equiv-text tags
        sourceClone.find('.locked.inside-attribute').remove();
        sourceClone.find( 'mark.inGlossary' ).each( function () {
            $( this ).replaceWith( $( this ).html() );
        } );
        var sourceTags = sourceClone.html()
            .match( /(&lt;\s*\/*\s*(g|x|bx|ex|bpt|ept|ph|it|mrk)\s*.*?&gt;)/gi );

        //get target tags from the segment
        var targetClone =  $( '.targetarea', UI.currentSegment ).clone();
        //Remove from the target the tags with mismatch
        targetClone.find('.locked.mismatch').remove();
        var newhtml = targetClone.html();
        //Remove inside-attribute for ph with equiv-text tags
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
        setTimeout(function (  ) {
            UI.segmentQA(UI.currentSegment);
        }, 100);
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
            currentString =  currentString.replace(regExp, '');
            return UI.decodePlaceholdersToText(currentString);
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
        return view2rawxliff( area.text() );
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
        $div = this.transformPlaceholdersHtml($div);
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

    handleCopyEvent: function ( e ) {
        var elem = $(e.target);
        var cloneTag, text;
        if ( elem.hasClass('inside-attribute') || elem.parent().hasClass('inside-attribute') ) {
            var tag = (elem.hasClass('inside-attribute')) ? elem.parent('span.locked') : elem.parent().parent('span.locked');
            cloneTag = tag.clone();
            cloneTag.find('.inside-attribute').remove();
            text = cloneTag.text();
            e.clipboardData.setData('text/plain', text.trim());
            e.preventDefault();
        } else if (elem.hasClass('locked')) {
            cloneTag = elem.clone();
            cloneTag.find('.inside-attribute').remove();
            text = htmlEncode(cloneTag.text());
            e.clipboardData.setData('text/plain', text.trim());
            e.clipboardData.setData('text/html', text.trim());
            e.preventDefault();
        }
    },
    handleDragEvent: function ( e ) {
        var elem = $(e.target);
        if ( elem.hasClass('inside-attribute') || elem.parent().hasClass('inside-attribute') ) {
            var tag = elem.closest('span.locked:not(.inside-attribute)');
            var cloneTag = tag.clone();
            cloneTag.find('.inside-attribute').remove();
            var text = htmlEncode(cloneTag.text());
            e.dataTransfer.setData('text/plain', UI.transformTextForLockTags(text).trim());
            e.dataTransfer.setData('text/html', UI.transformTextForLockTags(text).trim());
        } else if (elem.hasClass('locked')) {
            var text = htmlEncode(elem.text());
            e.dataTransfer.setData('text/plain', UI.transformTextForLockTags(text).trim());
            e.dataTransfer.setData('text/html', UI.transformTextForLockTags(text).trim());
        }
    },
    /**
     * When you click on a tag, it is selected and the selected class is added (ui.events->382).
     * Clicking on the edititarea to remove the tags with the selected class that otherwise are
     * removed the first time you press the delete key (ui.editarea-> 51 )
     */
    removeSelectedClassToTags: function (  ) {
        if (UI.editarea) {
            UI.editarea.find('.locked.selected').removeClass('selected');
            $('.editor .source .locked').removeClass('selected');
        }
    }

});


