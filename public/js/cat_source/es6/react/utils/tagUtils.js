let TAGS_UTILS =  {

    /**
     * Called when a Segment string returned by server has to be visualized, it replace placeholders with tags
     * @param str
     * @returns {XML|string}
     */
    decodePlaceholdersToText: function (str) {
        let _str = str;
        // if(UI.markSpacesEnabled) {
        //     if(jumpSpacesEncode) {
        //         _str = this.encodeSpacesAsPlaceholders(htmlDecode(_str), true);
        //     }
        // }

        _str = _str.replace( config.lfPlaceholderRegex, '<span class="monad marker softReturn ' + config.lfPlaceholderClass +'"><br /></span>' )
            .replace( config.crPlaceholderRegex, '<span class="monad marker softReturn' + config.crPlaceholderClass +'"><br /></span>' )
        _str = _str.replace( config.lfPlaceholderRegex, '<span class="monad marker softReturn ' + config.lfPlaceholderClass +'" contenteditable="false"><br /></span>' )
            .replace( config.crPlaceholderRegex, '<span class="monad marker softReturn' + config.crPlaceholderClass +'" contenteditable="false"><br /></span>' )
            .replace( config.crlfPlaceholderRegex, '<br class="' + config.crlfPlaceholderClass +'" />' )
            .replace( config.tabPlaceholderRegex, '<span class="tab-marker monad marker ' + config.tabPlaceholderClass +'" contenteditable="false">&#8677;</span>' )
            .replace( config.nbspPlaceholderRegex, '<span class="nbsp-marker monad marker ' + config.nbspPlaceholderClass +'" contenteditable="false">&nbsp;</span>' )
            .replace(/(<\/span\>)$/gi, "</span><br class=\"end\">"); // For rangy cursor after a monad marker

        return _str;
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

            tx = tx.replace( /&lt;ph.*?id="(.*?)"/gi, function (match, text) {
                phIDs.push(text);
                return match;
            });

            tx = tx.replace( /&lt;ph.*?equiv-text="base64:.*?"(.*?\/&gt;)/gi, function (match, text) {
                return match.replace(text, "<span contenteditable='false' class='locked locked-inside tag-html-container-close' >\"" + text + "</span>");
            });
            tx = tx.replace( /base64:(.*?)"/gi , function (match, text) {
                base64Array.push(text);
                var id = phIDs.shift();
                return "<span contenteditable='false' class='locked locked-inside inside-attribute' data-original='base64:" + text+ "'><a>("+ id + ")</a>" + Base64.decode(text) + "</span>";
            });
            tx = tx.replace( /(&lt;ph.*?equiv-text=")/gi, function (match, text) {
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

    /**
     * To transform text with the' ph' tags that have the attribute' equiv-text' into text only, without html tags
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

    detectTagType: function (area) {
        if (!UI.tagLockEnabled || config.tagLockCustomizable ) {
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
    indexTags: null,
    numCharsUntilTagRight: null,
    numCharsUntilTagLeft: null,
    nearTagOnRight: function (index, ar) {
        if($(ar[index]).hasClass('locked')) {
            if(this.numCharsUntilTagRight === 0) {
                // count index of this tag in the tags list
                TAGS_UTILS.indexTags = 0;
                $.each(ar, function (ind) {
                    if(ind == index) {
                        return false;
                    } else {
                        if($(this).hasClass('locked')) {
                            TAGS_UTILS.indexTags++;
                        }
                    }
                });
                return true;
            } else {
                return false;
            }
        } else {
            if (typeof ar[index] == 'undefined') return false;

            if(ar[index].nodeName === '#text') {
                this.numCharsUntilTagRight += ar[index].data.length;
            }
            this.nearTagOnRight(index+1, ar);
        }
    },
    nearTagOnLeft: function (index, ar) {
        if (index < 0) return false;
        if($(ar[index]).hasClass('locked')) {
            if(this.numCharsUntilTagLeft === 0) {
                // count index of this tag in the tags list
                TAGS_UTILS.indexTags = 0;
                $.each(ar, function (ind) {
                    if(ind === index) {
                        return false;
                    } else {
                        if($(this).hasClass('locked')) {
                            TAGS_UTILS.indexTags++;
                        }
                    }
                });
                return true;
            } else {
                return false;
            }
        } else {
            if(ar[index].nodeName === '#text') {
                this.numCharsUntilTagLeft += ar[index].data.length;
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
            this.checkTagProximityFn();
        }
    },

    checkTagProximityFn:  function () {
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
            let self = this;
            $.each(htmlEditarea, function (index) {
                if($(this).hasClass('temp-highlight-tags')) {
                    self.numCharsUntilTagRight = 0;
                    self.numCharsUntilTagLeft = 0;
                    var nearTagOnRight = self.nearTagOnRight(index+1, htmlEditarea);
                    var nearTagOnLeft = self.nearTagOnLeft(index-1, htmlEditarea);

                    if( (typeof nearTagOnRight != 'undefined') && (nearTagOnRight) ||
                        (typeof nearTagOnLeft != 'undefined')&&(nearTagOnLeft)) {
                        UI.highlightCorrespondingTags($(UI.editarea.find('.locked:not(.locked-inside)')[TAGS_UTILS.indexTags]));
                    }

                    self.numCharsUntilTagRight = null;
                    self.numCharsUntilTagLeft = null;
                    UI.editarea.find('.temp-highlight-tags').remove();
                    UI.editarea.get(0).normalize();
                    return false;
                }
            });
        }
        $('body').find('.temp-highlight-tags').remove();
        UI.highlightEquivalentTaginSourceOrTarget(UI.editarea, UI.currentSegment.find('.source'));
    },

    checkTagProximity : _.debounce(()=> TAGS_UTILS.checkTagProximityFn(), 500),


};
module.exports =  TAGS_UTILS;