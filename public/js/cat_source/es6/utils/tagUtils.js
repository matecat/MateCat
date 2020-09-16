// import SegmentStore  from '../stores/SegmentStore';
import TextUtils from './textUtils';

const TAGS_UTILS = {
    // TODO: move it in another module
    prepareTextToSend: function (text) {
        var div = document.createElement('div');
        var $div = $(div);
        $div.html(text);
        $div = this.transformPlaceholdersHtml($div);

        $div.find('span.space-marker').replaceWith(' ');
        $div.find('span.rangySelectionBoundary').remove();
        $div = this.encodeTagsWithHtmlAttribute($div);
        return TextUtils.view2rawxliff($div.text());
    },

    decodeText(segment, text) {
        var decoded_text;
        if (
            SegmentUtils.checkTPEnabled() &&
            !segment.tagged &&
            (segment.status.toLowerCase() === 'draft' || segment.status.toLowerCase() === 'new') &&
            !this.checkXliffTagsInText(segment.translation) &&
            this.removeAllTags(segment.segment) !== ''
        ) {
            decoded_text = this.removeAllTags(text);
        } else {
            decoded_text = text;
        }
        decoded_text = this.decodePlaceholdersToText(decoded_text || '');
        if (!(config.tagLockCustomizable && !UI.tagLockEnabled)) {
            decoded_text = this.transformTextForLockTags(decoded_text);
        }
        return decoded_text;
    },
    transformPlaceholdersAndTags: function (text) {
        text = this.decodePlaceholdersToText(text || '');
        if (!(config.tagLockCustomizable && !UI.tagLockEnabled)) {
            text = this.transformTextForLockTags(text);
        }
        return text;
    },
    /**
     * Called when a Segment string returned by server has to be visualized, it replace placeholders with tags
     * @param str
     * @returns {XML|string}
     */
    decodePlaceholdersToText: function (str) {
        let _str = str;

        _str = _str
            .replace(
                config.lfPlaceholderRegex,
                '<span class="monad marker softReturn ' + config.lfPlaceholderClass + '"><br /></span>'
            )
            .replace(
                config.crPlaceholderRegex,
                '<span class="monad marker softReturn ' + config.crPlaceholderClass + '"><br /></span>'
            );
        _str = _str
            .replace(
                config.lfPlaceholderRegex,
                '<span class="monad marker softReturn ' +
                    config.lfPlaceholderClass +
                    '" contenteditable="false"><br /></span>'
            )
            .replace(
                config.crPlaceholderRegex,
                '<span class="monad marker softReturn ' +
                    config.crPlaceholderClass +
                    '" contenteditable="false"><br /></span>'
            )
            .replace(config.crlfPlaceholderRegex, '<br class="' + config.crlfPlaceholderClass + '" />')
            .replace(
                config.tabPlaceholderRegex,
                '<span class="tab-marker monad marker ' +
                    config.tabPlaceholderClass +
                    '" contenteditable="false">&#8677;</span>'
            )
            .replace(
                config.nbspPlaceholderRegex,
                '<span class="nbsp-marker monad marker ' +
                    config.nbspPlaceholderClass +
                    '" contenteditable="false">&nbsp;</span>'
            )
            .replace(/(<\/span\>)$/gi, '</span><br class="end">'); // For rangy cursor after a monad marker

        return _str;
    },

    transformTextForLockTags: function (tx) {
        let brTx1 = '<_plh_ contenteditable="false" class="locked style-tag ">$1</_plh_>';
        let brTx2 = '<span contenteditable="false" class="locked style-tag">$1</span>';

        tx = tx
            .replace(/&amp;/gi, '&')
            .replace(/<span/gi, '<_plh_')
            .replace(/<\/span/gi, '</_plh_')
            .replace(/&lt;/gi, '<')
            .replace(/(<(ph.*?)\s*?\/&gt;)/gi, brTx1)
            .replace(/(<(g|x|bx|ex|bpt|ept|ph.*?|it|mrk)\sid[^<“]*?&gt;)/gi, brTx1)
            .replace(/(<(ph.*?)\sid[^<“]*?\/>)/gi, brTx1)
            .replace(/</gi, '&lt;')
            .replace(/\&lt;_plh_/gi, '<span')
            .replace(/\&lt;\/_plh_/gi, '</span')
            .replace(/\&lt;lxqwarning/gi, '<lxqwarning')
            .replace(/\&lt;\/lxqwarning/gi, '</lxqwarning')
            .replace(/\&lt;div\>/gi, '<div>')
            .replace(/\&lt;\/div\>/gi, '</div>')
            .replace(/\&lt;br\>/gi, '<br />')
            .replace(/\&lt;br \/>/gi, '<br />')
            .replace(/\&lt;mark /gi, '<mark ')
            .replace(/\&lt;\/mark/gi, '</mark')
            .replace(/\&lt;ins /gi, '<ins ') // For translation conflicts tab
            .replace(/\&lt;\/ins/gi, '</ins') // For translation conflicts tab
            .replace(/\&lt;del /gi, '<del ') // For translation conflicts tab
            .replace(/\&lt;\/del/gi, '</del') // For translation conflicts tab
            .replace(/\&lt;br class=["\'](.*?)["\'][\s]*[\/]*(\&gt;|\>)/gi, '<br class="$1" />')
            .replace(/(&lt;\s*\/\s*(g|x|bx|ex|bpt|ept|ph|it|mrk)\s*&gt;)/gi, brTx2);

        tx = tx.replace(
            /(<span contenteditable="false" class="[^"]*"\>)(:?<span contenteditable="false" class="[^"]*"\>)(.*?)(<\/span\>){2}/gi,
            '$1$3</span>'
        );
        tx = tx.replace(/(<\/span\>)$(\s){0,}/gi, '</span> ');
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
        let returnValue = tx;
        try {
            if (tx.indexOf('locked-inside') > -1) return tx;
            let base64Array = [];
            let phIDs = [];
            tx = tx.replace(/&quot;/gi, '"');

            tx = tx.replace(/&lt;ph.*?id="(.*?)".*?&gt/gi, function (match, text) {
                phIDs.push(text);
                return match;
            });

            tx = tx.replace(/&lt;ph.*?equiv-text="base64:.*?"(.*?\/&gt;)/gi, function (match, text) {
                return match.replace(
                    text,
                    "<span contenteditable='false' class='locked locked-inside tag-html-container-close' >\"" +
                        text +
                        '</span>'
                );
            });
            tx = tx.replace(/base64:(.*?)"/gi, function (match, text) {
                if (phIDs.length === 0) return text;
                base64Array.push(text);
                let id = phIDs.shift();
                return (
                    "<span contenteditable='false' class='locked locked-inside inside-attribute' data-original='base64:" +
                    text +
                    "'><a>(" +
                    id +
                    ')</a>' +
                    Base64.decode(text) +
                    '</span>'
                );
            });
            tx = tx.replace(/(&lt;ph.*?equiv-text=")/gi, function (match, text) {
                if (base64Array.length === 0) return text;
                let base = base64Array.shift();
                return (
                    "<span contenteditable='false' class='locked locked-inside tag-html-container-open' >" +
                    text +
                    'base64:' +
                    base +
                    '</span>'
                );
            });
            // delete(base64Array);
            returnValue = tx;
        } catch (e) {
            console.error('Error parsing tag ph in transformTagsWithHtmlAttribute function');
            returnValue = '';
        } finally {
            return returnValue;
        }
    },

    encodeSpacesAsPlaceholders: function (str, root) {
        let newStr = '';
        $.each($.parseHTML(str), function () {
            if (this.nodeName == '#text') {
                newStr += $(this)
                    .text()
                    .replace(/\s/gi, '<span class="space-marker marker monad" contenteditable="false"> </span>');
            } else {
                let match = this.outerHTML.match(/<.*?>/gi);
                if (match.length == 1) {
                    // se è 1 solo, è un tag inline
                } else if (match.length == 2) {
                    // se sono due, non ci sono tag innestati
                    newStr +=
                        TextUtils.htmlEncode(match[0]) +
                        this.innerHTML.replace(
                            /\s/gi,
                            '#@-lt-@#span#@-space-@#class="space-marker#@-space-@#marker#@-space-@#monad"#@-space-@#contenteditable="false"#@-gt-@# #@-lt-@#/span#@-gt-@#'
                        ) +
                        htmlEncode(match[1]);
                } else {
                    // se sono più di due, ci sono tag innestati

                    newStr +=
                        TextUtils.htmlEncode(match[0]) +
                        TAGS_UTILS.encodeSpacesAsPlaceholders(this.innerHTML) +
                        TextUtils.htmlEncode(match[1], false);
                }
            }
        });
        if (root) {
            newStr = newStr
                .replace(/#@-lt-@#/gi, '<')
                .replace(/#@-gt-@#/gi, '>')
                .replace(/#@-space-@#/gi, ' ');
        }
        return newStr;
    },

    /**
     * To transform text with the' ph' tags that have the attribute' equiv-text' into text only, without html tags
     */
    removePhTagsWithEquivTextIntoText: function (tx) {
        try {
            tx = tx.replace(/&quot;/gi, '"');

            tx = tx.replace(/&lt;ph.*?equiv-text="base64:.*?(\/&gt;)/gi, function (match, text) {
                return match.replace(text, '');
            });
            tx = tx.replace(/&lt;ph.*?equiv-text="base64:.*?(\/>)/gi, function (match, text) {
                return match.replace(text, '');
            });
            tx = tx.replace(/(&lt;ph.*?equiv-text=")/gi, function (match, text) {
                return '';
            });
            tx = tx.replace(/base64:(.*?)"/gi, function (match, text) {
                return Base64.decode(text);
            });
            return tx;
        } catch (e) {
            console.error('Error parsing tag ph in removePhTagsWithEquivTextIntoText function');
        }
    },

    detectTagType: function (area) {
        if (!UI.tagLockEnabled || config.tagLockCustomizable) {
            return false;
        }
        $('span.locked:not(.locked-inside)', area).each(function () {
            if ($(this).text().startsWith('</')) {
                $(this).addClass('endTag');
            } else {
                if ($(this).text().endsWith('/>')) {
                    $(this).addClass('selfClosingTag');
                } else {
                    $(this).addClass('startTag');
                }
            }
        });
    },
    indexTags: null,
    numCharsUntilTagRight: null,
    numCharsUntilTagLeft: null,
    nearTagOnRight: function (index, ar) {
        if ($(ar[index]).hasClass('locked')) {
            if (this.numCharsUntilTagRight === 0) {
                // count index of this tag in the tags list
                TAGS_UTILS.indexTags = 0;
                $.each(ar, function (ind) {
                    if (ind == index) {
                        return false;
                    } else {
                        if ($(this).hasClass('locked')) {
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

            if (ar[index].nodeName === '#text') {
                this.numCharsUntilTagRight += ar[index].data.length;
            }
            this.nearTagOnRight(index + 1, ar);
        }
    },
    nearTagOnLeft: function (index, ar) {
        if (index < 0) return false;
        if ($(ar[index]).hasClass('locked')) {
            if (this.numCharsUntilTagLeft === 0) {
                // count index of this tag in the tags list
                TAGS_UTILS.indexTags = 0;
                $.each(ar, function (ind) {
                    if (ind === index) {
                        return false;
                    } else {
                        if ($(this).hasClass('locked')) {
                            TAGS_UTILS.indexTags++;
                        }
                    }
                });
                return true;
            } else {
                return false;
            }
        } else {
            if (ar[index].nodeName === '#text') {
                this.numCharsUntilTagLeft += ar[index].data.length;
            }
            this.nearTagOnLeft(index - 1, ar);
        }
    },

    markSelectedTag: function ($tag) {
        let elem =
            $tag.hasClass('locked') && !$tag.hasClass('inside-attribute')
                ? $tag
                : $tag.closest('.locked:not(.inside-attribute)');
        if (elem.hasClass('selected')) {
            elem.removeClass('selected');
            TextUtils.setCursorPosition(elem[0], 'end');
        } else {
            TextUtils.setCursorPosition(elem[0]);
            CursorUtils.selectText(elem[0]);
            this.removeSelectedClassToTags();
            elem.addClass('selected');
            if (UI.body.hasClass('tagmode-default-compressed')) {
                $('.editor .tagModeToggle').click();
            }
        }
        if (elem.closest('.source').length > 0) {
            this.removeHighlightCorrespondingTags(elem.closest('.source'));
            this.highlightCorrespondingTags(elem);
            this.highlightEquivalentTaginSourceOrTarget(elem.closest('.source'), UI.editarea);
        } else {
            this.checkTagProximityFn();
        }
    },

    checkTagProximityFn: function () {
        if (!UI.editarea || UI.editarea.html() == '') return false;

        let selection = window.getSelection();
        if (selection.rangeCount < 1) return false;
        let range = selection.getRangeAt(0);
        UI.editarea.find('.temp-highlight-tags').remove();
        if (!range.collapsed) {
            if (UI.editarea.find('.locked.selected').length > 0) {
                UI.editarea.find('.locked.selected').after('<span class="temp-highlight-tags"/>');
            } else {
                return true;
            }
        } else {
            TextUtils.pasteHtmlAtCaret('<span class="temp-highlight-tags"/>');
        }
        let htmlEditarea = $.parseHTML(UI.editarea.html());
        if (htmlEditarea) {
            this.removeHighlightCorrespondingTags(UI.editarea);
            let self = this;
            $.each(htmlEditarea, function (index) {
                if ($(this).hasClass('temp-highlight-tags')) {
                    self.numCharsUntilTagRight = 0;
                    self.numCharsUntilTagLeft = 0;
                    let nearTagOnRight = self.nearTagOnRight(index + 1, htmlEditarea);
                    let nearTagOnLeft = self.nearTagOnLeft(index - 1, htmlEditarea);

                    if (
                        (typeof nearTagOnRight != 'undefined' && nearTagOnRight) ||
                        (typeof nearTagOnLeft != 'undefined' && nearTagOnLeft)
                    ) {
                        self.highlightCorrespondingTags(
                            $(UI.editarea.find('.locked:not(.locked-inside)')[TAGS_UTILS.indexTags])
                        );
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
        this.highlightEquivalentTaginSourceOrTarget(UI.editarea, UI.currentSegment.find('.source'));
    },

    checkTagProximity: _.debounce(() => TAGS_UTILS.checkTagProximityFn(), 500),

    /**
     * Search in container for a highlighted tad and switch on the corresponding
     * tag in source or target
     * @param containerSearch The container where to search for the tag
     * @param containerHighlight
     */
    highlightEquivalentTaginSourceOrTarget: function (containerSearch, containerHighlight) {
        this.removeHighlightCorrespondingTags(containerHighlight);
        let highlightedTag = containerSearch.find('.startTag.locked.highlight, .selfClosingTag.locked.highlight');
        if (highlightedTag.length > 0) {
            let sourceTag, text;
            if (highlightedTag.find('.locked-inside').length > 0) {
                text = highlightedTag.find('.inside-attribute').text();
                sourceTag = containerHighlight.find('span.inside-attribute:contains(' + text + ')').parent();
            } else {
                text = $(highlightedTag.get(0)).text();
                sourceTag = containerHighlight.find('span.locked:contains(' + text + ')');
            }
            this.highlightCorrespondingTags(sourceTag);
        }
    },
    highlightCorrespondingTags: function (el) {
        let pairEl, num, ind;
        if (el.hasClass('startTag')) {
            if (el.next('.endTag').length) {
                el.next('.endTag').addClass('highlight');
            } else {
                num = 1;
                ind = 0;
                $(el)
                    .nextAll('.locked')
                    .each(function () {
                        ind++;
                        if ($(this).hasClass('startTag')) {
                            num++;
                        } else if ($(this).hasClass('selfClosingTag')) {
                        } else {
                            // end tag
                            num--;
                            if (num == 0) {
                                pairEl = $(this);
                                return false;
                            }
                        }
                    });
                if (pairEl) {
                    $(pairEl).addClass('highlight');
                }
            }
        } else if (el.hasClass('endTag')) {
            if (el.prev('.startTag').length) {
                el.prev('.startTag').first().addClass('highlight');
            } else {
                num = 1;
                ind = 0;
                $(el)
                    .prevAll('.locked')
                    .each(function () {
                        ind++;
                        if ($(this).hasClass('endTag')) {
                            num++;
                        } else if ($(this).hasClass('selfClosingTag')) {
                        } else {
                            // end tag
                            num--;
                            if (num == 0) {
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
    markTagMismatch: function (tag_mismatch, sid) {
        if (!_.isUndefined(tag_mismatch.source) && tag_mismatch.source.length > 0) {
            $('#segment-' + sid + ' .source span.locked.style-tag:not(.temp)')
                .filter(function () {
                    let clone = $(this).clone();
                    clone.find('.inside-attribute').remove();
                    let tag = TextUtils.htmlEncode(clone.text());
                    return tag_mismatch.source.indexOf(tag) !== -1;
                })
                .last()
                .addClass('temp');
        }
        if (!_.isUndefined(tag_mismatch.target) && tag_mismatch.target.length > 0) {
            $('#segment-' + sid + ' .editarea span.locked.style-tag:not(.temp)')
                .filter(function () {
                    let clone = $(this).clone();
                    clone.find('.inside-attribute').remove();
                    let tag = TextUtils.htmlEncode(clone.text());
                    return tag_mismatch.target.indexOf(tag) !== -1;
                })
                .last()
                .addClass('temp');
        }
        // ??
        $('#segment-' + sid + ' span.locked.mismatch')
            .addClass('mismatch-old')
            .removeClass('mismatch');
        $('#segment-' + sid + ' span.locked.temp')
            .addClass('mismatch')
            .removeClass('temp');
        $('#segment-' + sid + ' span.locked.mismatch-old').removeClass('mismatch-old');

        $('#segment-' + sid + ' .editarea span.locked:not(.temp)').removeClass('order-error');
        if (!_.isUndefined(tag_mismatch.order) && tag_mismatch.order.length > 0) {
            $('#segment-' + sid + ' .editarea .locked.style-tag:not(.mismatch)')
                .filter(function () {
                    let clone = $(this).clone();
                    clone.find('.inside-attribute').remove();
                    return TextUtils.htmlEncode(clone.text()) === tag_mismatch.order[0];
                })
                .addClass('order-error');
        }
    },
    /**
     *  Return the Regular expression to match all xliff source tags
     */
    getXliffRegExpression: function () {
        return /(&lt;\s*\/*\s*(g|x|bx|ex|bpt|ept|ph|it|mrk)\s*.*?&gt;)/gim;
    },

    hasSourceOrTargetTags: function (sid) {
        let regExp = this.getXliffRegExpression();

        try {
            let segment = SegmentStore.getSegmentByIdToJS(sid);
            let sourceTags = segment.segment.match(regExp);
            return sourceTags && sourceTags.length > 0;
        } catch (error) {
            return false;
        }
    },

    /**
     * Add at the end of the target the missing tags
     */
    autoFillTagsInTarget: function (segmentObj) {
        let sourceTags = segmentObj.segment.match(/(&lt;\s*\/*\s*(g|x|bx|ex|bpt|ept|ph|it|mrk)\s*.*?&gt;)/gi);

        let newhtml = segmentObj.translation;

        let targetTags = segmentObj.translation.match(/(&lt;\s*\/*\s*(g|x|bx|ex|bpt|ept|ph|it|mrk)\s*.*?&gt;)/gi);

        if (targetTags == null) {
            targetTags = [];
        } else {
            targetTags = targetTags.map(function (elem) {
                return elem.replace(/<\/span>/gi, '').replace(/<span.*?>/gi, '');
            });
        }

        let missingTags = sourceTags.map(function (elem) {
            return elem.replace(/<\/span>/gi, '').replace(/<span.*?>/gi, '');
        });
        //remove from source tags all the tags in target segment
        for (let i = 0; i < targetTags.length; i++) {
            let pos = missingTags.indexOf(targetTags[i]);
            if (pos > -1) {
                missingTags.splice(pos, 1);
            }
        }

        //add tags into the target segment
        for (let i = 0; i < missingTags.length; i++) {
            if (!(config.tagLockCustomizable && !UI.tagLockEnabled)) {
                newhtml = newhtml + TagUtils.transformTextForLockTags(missingTags[i]);
            } else {
                newhtml = newhtml + missingTags[i];
            }
        }
        return newhtml;
    },

    /**
     * Check if the data-original attribute in the source of the segment contains special tags (Ex: <g id=1></g>)
     * (Note that in the data-original attribute there are the &amp;lt instead of &lt)
     * @param segmentSource
     * @returns {boolean}
     */
    hasDataOriginalTags: function (segmentSource) {
        var originalText = segmentSource;
        var reg = new RegExp(/(&lt;\s*\/*\s*(g|x|bx|ex|bpt|ept|ph|it|mrk)\s*.*?&gt;)/gim);
        return !_.isUndefined(originalText) && reg.test(originalText);
    },

    /**
     * Remove all xliff source tags from the string
     * @param currentString : the string to parse
     * @returns the decoded String
     */
    removeAllTags: function (currentString) {
        if (currentString) {
            var regExp = TagUtils.getXliffRegExpression();
            currentString = currentString.replace(regExp, '');
            return TagUtils.decodePlaceholdersToText(currentString);
        } else {
            return '';
        }
    },

    checkXliffTagsInText: function (text) {
        var reg = TagUtils.getXliffRegExpression();
        return reg.test(text);
    },

    /**
     * It does the same as postProcessEditarea function but does not remove the cursor span
     * @param text
     * @returns {*}
     */

    cleanTextFromPlaceholdersSpan: function (text) {
        var div = document.createElement('div');
        var $div = $(div);
        $div.html(text);
        $div = this.transformPlaceholdersHtml($div);
        $div.find('span.space-marker').replaceWith(' ');
        $div = this.encodeTagsWithHtmlAttribute($div);
        return $div.text();
    },
    transformPlaceholdersHtml: function ($elem) {
        var divs = $elem.find('div');

        if (divs.length) {
            divs.each(function () {
                $(this).find('br:not([class])').remove();
                $(this)
                    .prepend($('<span class="placeholder">' + config.crPlaceholder + '</span>'))
                    .replaceWith($(this).html());
            });
        } else {
            $elem
                .find('br:not([class])')
                .replaceWith($('<span class="placeholder">' + config.crPlaceholder + '</span>'));
            $elem
                .find('br.' + config.crlfPlaceholderClass)
                .replaceWith('<span class="placeholder">' + config.crlfPlaceholder + '</span>');
            $elem
                .find('span.' + config.lfPlaceholderClass)
                .replaceWith('<span class="placeholder">' + config.lfPlaceholder + '</span>');
            $elem
                .find('span.' + config.crPlaceholderClass)
                .replaceWith('<span class="placeholder">' + config.crPlaceholder + '</span>');
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

    /**
     * When you click on a tag, it is selected and the selected class is added (ui.events->382).
     * Clicking on the edititarea to remove the tags with the selected class that otherwise are
     * removed the first time you press the delete key (ui.editarea-> 51 )
     */
    removeSelectedClassToTags: function () {
        $('.targetarea .locked.selected').removeClass('selected');
        $('.editor .source .locked').removeClass('selected');
    },

    _treatTagsAsBlock: function (mainStr, transDecoded, replacementsMap) {
        var placeholderPhRegEx = /(&lt;ph id="mtc_.*?\/&gt;)/g;
        var reverseMapElements = {};

        var listMainStr = mainStr.match(placeholderPhRegEx);

        if (listMainStr === null) {
            return [mainStr, transDecoded, replacementsMap];
        }

        /**
         * UI.execDiff works at character level, when a tag differs only for a part of it in the source/translation it breaks the tag
         * Ex:
         *
         * Those 2 tags differs only by their IDs
         *
         * Original string: &lt;ph id="mtc_1" equiv-text="base64:JXt1c2VyX2NvbnRleHQuZGltX2NpdHl8fQ=="/&gt;
         * New String:      &lt;ph id="mtc_2" equiv-text="base64:JXt1c2VyX2NvbnRleHQuZGltX2NpdHl8fQ=="/&gt;
         *
         * After the dom rendering of the TextUtils.dmp.diff_prettyHtml function
         *
         *  <span contenteditable="false" class="locked style-tag ">
         *      <span contenteditable="false" class="locked locked-inside tag-html-container-open">&lt;ph id="mtc_</span>
         *
         *      <!-- ###### the only diff is the ID of the tag ###### -->
         *      <del class="diff">1</del>
         *      <ins class="diff">2</ins>
         *      <!-- ###### the only diff is the ID of the tag ###### -->
         *
         *      <span>" equiv-text="base64:JXt1c2VyX2NvbnRleHQuZGltX2NpdHl8fQ==</span>
         *      <span contenteditable="false" class="locked locked-inside inside-attribute" data-original="base64:JXt1c2VyX2NvbnRleHQuZGltX2NpdHl8fQ=="></span>
         *  </span>
         *
         *  When this happens, the function TagUtils.transformTextForLockTags fails to find the PH tag by regexp and do not lock the tags or lock it in a wrong way
         *
         *  So, transform the string in a single character ( Private Use Unicode char ) for the diff function, place it in a map and reinsert in the diff_obj after the UI.execDiff executed
         *
         * //U+E000..U+F8FF, 6,400 Private-Use Characters Unicode, should be impossible to have those in source/target
         */
        var charCodePlaceholder = 57344;

        listMainStr.forEach(function (element) {
            var actualCharCode = String.fromCharCode(charCodePlaceholder);

            /**
             * override because we already have an element in the map, so the content is the same
             * ( duplicated TAG, should be impossible but it's easy to cover the case ),
             * use such character
             */
            if (reverseMapElements[element]) {
                actualCharCode = reverseMapElements[element];
            }

            replacementsMap[actualCharCode] = element;
            reverseMapElements[element] = actualCharCode; // fill the reverse map with the current element ( override if equal )
            mainStr = mainStr.replace(element, actualCharCode);
            charCodePlaceholder++;
        });

        var listTransDecoded = transDecoded.match(placeholderPhRegEx);
        if (listTransDecoded) {
            listTransDecoded.forEach(function (element) {
                var actualCharCode = String.fromCharCode(charCodePlaceholder);

                /**
                 * override because we already have an element in the map, so the content is the same
                 * ( tag is present in source and target )
                 * use such character
                 */
                if (reverseMapElements[element]) {
                    actualCharCode = reverseMapElements[element];
                }

                replacementsMap[actualCharCode] = element;
                reverseMapElements[element] = actualCharCode; // fill the reverse map with the current element ( override if equal )
                transDecoded = transDecoded.replace(element, actualCharCode);
                charCodePlaceholder++;
            });
        }
        return [mainStr, transDecoded, replacementsMap];
    },
};
module.exports = TAGS_UTILS;
