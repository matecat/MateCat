
const CursorUtils = {

    savedSel: null,
    savedSelActiveElement: null,

    saveSelection() {
        if (this.savedSel) {
            rangy.removeMarkers(this.savedSel);
        }

        this.savedSel = rangy.saveSelection();
        this.savedSelActiveElement = document.activeElement;
    },
    restoreSelection() {
        if (this.savedSel) {
            rangy.restoreSelection(this.savedSel, true);
            this.savedSel = null;
            window.setTimeout(() => {
                if (this.savedSelActiveElement && typeof this.savedSelActiveElement.focus != "undefined") {
                    this.savedSelActiveElement.focus();
                }
            }, 1);
        }
    },

    selectText(element) {
        let text = element, range, selection;
        if (document.body.createTextRange) {
            range = document.body.createTextRange();
            range.moveToElementText(text);
            range.select();
        } else if (window.getSelection) {
            selection = window.getSelection();
            range = document.createRange();
            range.selectNodeContents(text);
            selection.removeAllRanges();
            selection.addRange(range);
        }
    },
    getSelectionHtml() {
        var html = "";
        if (typeof window.getSelection != "undefined") {
            var sel = window.getSelection();
            if (sel.rangeCount) {
                var container = document.createElement("div");
                for (var i = 0, len = sel.rangeCount; i < len; ++i) {
                    container.appendChild(sel.getRangeAt(i).cloneContents());
                }
                html = container.innerHTML;
            }
        } else if (typeof document.selection != "undefined") {
            if (document.selection.type == "Text") {
                html = document.selection.createRange().htmlText;
            }
        }
        return html;
    },
    insertHtmlAfterSelection(html) {
        var sel, range;
        if (window.getSelection) {
            sel = window.getSelection();
            if (sel.getRangeAt && sel.rangeCount) {
                range = window.getSelection().getRangeAt(0);
                // range.collapse(false);

                // Range.createContextualFragment() would be useful here but is
                // non-standard and not supported in all browsers (IE9, for one)
                var el = document.createElement("div");
                el.innerHTML = html;
                var frag = document.createDocumentFragment(), node, lastNode;
                while ( (node = el.firstChild) ) {
                    lastNode = frag.appendChild(node);
                }
                range.insertNode(frag);
            }
        } else if (document.selection && document.selection.createRange) {
            range = document.selection.createRange();
            range.collapse(false);
            range.pasteHTML(html);
        }
        return range;
    },
    replaceSelectedHtml(replacementHtml, range) {
        var sel;
        if (range) {
            range.deleteContents();
            TextUtils.pasteHtmlAtCaret(replacementHtml);
        } else if (window.getSelection) {
            sel = window.getSelection();
            if (sel.rangeCount) {
                range = sel.getRangeAt(0);
                range.deleteContents();
                TextUtils.pasteHtmlAtCaret(replacementHtml);
//            range.pasteHtml(replacementHtml);
            }
        } else if (document.selection && document.selection.createRange) {
            range = document.selection.createRange();
            range.text = replacementText;
        }
    },
    getSelectionData(selection, container) {
        var data = {};
        data.start_node = $.inArray( selection.anchorNode, container.contents() );
        if (data.start_node<0) {
            //this means that the selection is probably ending inside a lexiqa tag,
            //or matecat tag/marking
            data.start_node = $.inArray( $(selection.anchorNode).parent()[0], container.contents() );
        }
        var nodes = container.contents();//array of nodes
        if (data.start_node ===0) {
            data.start_offset = selection.anchorOffset;
        } else {
            data.start_offset = 0;
            for (var i=0;i<data.start_node;i++) {
                data.start_offset += nodes[i].textContent.length;
            }
            data.start_offset += selection.anchorOffset;
            data.start_node = 0;
        }

        data.end_node = $.inArray( selection.focusNode, container.contents() );
        if (data.end_node<0) {
            //this means that the selection is probably ending inside a lexiqa tag,
            //or matecat tag/marking
            data.end_node = $.inArray( $(selection.focusNode).parent()[0], container.contents() );
        }
        if (data.end_node ===0)
            data.end_offset =  selection.focusOffset;
        else {
            data.end_offset = 0;
            for (var i=0;i<data.end_node;i++) {
                data.end_offset += nodes[i].textContent.length;
            }
            data.end_offset += selection.focusOffset;
            data.end_node = 0;
        }
        data.selected_string = selection.toString() ;
        return data ;
    },

    placeCaretAtEnd: function(el) {

        $(el).focus();
        if (typeof window.getSelection != "undefined" && typeof document.createRange != "undefined") {
            var range = document.createRange();
            range.selectNodeContents(el);
            range.collapse(false);
            var sel = window.getSelection();
            sel.removeAllRanges();
            sel.addRange(range);
        } else if (typeof document.body.createTextRange != "undefined") {
            var textRange = document.body.createTextRange();
            textRange.moveToElementText(el);
            textRange.collapse(false);
            textRange.select();
        }

    },

};

module.exports = CursorUtils;