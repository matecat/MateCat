/**
 * React Component for the warnings.

 */
import React from 'react';
import ReactDOM from 'react-dom';
import escapeStringRegexp from 'escape-string-regexp';
import TagUtils from '../../utils/tagUtils';
import TextUtils from '../../utils/textUtils';
import CursorUtils from '../../utils/cursorUtils';

class TagsMenu extends React.Component {
    constructor(props) {
        super(props);
        this.state = {};

        this.tagsRefs = {};
        let missingTags = this.getMissingTags();
        let uniqueSourceTags = TagsMenu.arrayUnique(this.evalCurrentSegmentSourceTags());
        let addedTags = _.filter(uniqueSourceTags, function (item) {
            return missingTags.indexOf(item.replace(/&quot;/g, '"')) === -1;
        });
        this.menuHeight = (this.props.height * 150) / 100;
        this.menuHeight = this.menuHeight > 500 ? 500 : this.menuHeight;
        this.menuWidth = 270;
        this.state = {
            selectedItem: 0,
            missingTags: missingTags,
            addedTags: addedTags,
            totalTags: missingTags.concat(addedTags),
            filteredTags: [],
            filter: '',
            coord: this.getSelectionCoords(),
        };
        this.handleKeydownFunction = this.handleKeydownFunction.bind(this);
        this.handleResizeEvent = this.handleResizeEvent.bind(this);
    }

    static arrayUnique(a) {
        return a.reduce(function (p, c) {
            if (p.indexOf(c) < 0) p.push(c);
            return p;
        }, []);
    }

    evalCurrentSegmentSourceTags() {
        var sourceTags = this.props.segment.segment.match(/(&lt;\s*\/*\s*(g|x|bx|ex|bpt|ept|ph|it|mrk)\s*.*?&gt;)/gi);
        return sourceTags || [];
    }

    getSelectionCoords() {
        let win = window;
        let doc = win.document;
        let sel = doc.selection,
            range,
            rects,
            rect;
        let x = 0,
            y = 0,
            offsetParent;
        let $container;
        if (win.getSelection) {
            sel = win.getSelection();
            if (sel.rangeCount) {
                range = sel.getRangeAt(0).cloneRange();

                let span = doc.createElement('span');
                if (span.getClientRects) {
                    // Ensure span has dimensions and position by
                    // adding a zero-width space character
                    span.appendChild(doc.createTextNode('\u200b'));
                    range.insertNode(span);
                    // rect = span.getClientRects()[0];
                    x = $(span).position().left;
                    y = $(span).position().top;
                    offsetParent = $(span).offsetParent().offset();
                    let spanParent = span.parentNode;
                    spanParent.removeChild(span);

                    // Glue any broken text nodes back together
                    spanParent.normalize();
                }
            }
        }
        if (window.innerHeight - offsetParent.top - y < this.menuHeight) {
            y = y;
        } else {
            y = y + 20;
        }
        if (window.innerWidth - offsetParent.left - x < this.menuWidth) {
            x = x - this.menuWidth + 20;
        }
        return { x: x, y: y };
    }

    getMissingTags() {
        const selfClosedTag = '&lt;/g&gt;';
        var sourceHtml = this.props.segment.segment;
        var sourceTags = sourceHtml.match(/(&lt;\s*\/*\s*(g|x|bx|ex|bpt|ept|ph|it|mrk)\s*.*?&gt;)/gi);
        //get target tags from the segment
        var targetTags = this.props.segment.translation.match(
            /(&lt;\s*\/*\s*(g|x|bx|ex|bpt|ept|ph|it|mrk)\s*.*?&gt;)/gi
        );

        if (targetTags == null) {
            targetTags = [];
        } else {
            targetTags = targetTags.map(function (elem) {
                return elem.replace(/<\/span>/gi, '').replace(/<span.*?>/gi, '');
            });
        }
        var missingTags = sourceTags.map(function (elem) {
            return elem.replace(/<\/span>/gi, '').replace(/<span.*?>/gi, '');
        });
        //remove from source tags all the tags in target segment
        let pos,
            lastFoundTagPos = 0;
        for (var i = 0; i < targetTags.length; i++) {
            if (targetTags[i] === selfClosedTag) {
                pos = missingTags.indexOf(targetTags[i], lastFoundTagPos);
            } else {
                pos = missingTags.indexOf(targetTags[i]);
            }
            if (pos > -1) {
                missingTags.splice(pos, 1);
                lastFoundTagPos = pos;
            }
        }
        return missingTags;
    }

    getItemsMenuHtml() {
        let menuItems = [];
        let missingItems = [];
        let addedItems = [];
        let textDecoded;
        let tagIndex = 0;
        if (this.state.missingTags.length > 0) {
            missingItems.push(
                <div className="head-tag-list missing" key={'missing-header'}>
                    {' '}
                    Missing source <span className="style-tag mismatch">tags</span> in the target{' '}
                </div>
            );
            _.each(this.state.missingTags, (item, index) => {
                if (this.state.filter !== '' && this.state.totalTags.indexOf(item) === -1) {
                    return;
                }
                let textDecoded = TagUtils.transformTextForLockTags(item);
                let label =
                    textDecoded.indexOf('inside-attribute') !== -1
                        ? $(textDecoded).find('.inside-attribute').html()
                        : $(textDecoded).html();
                if (this.state.filter !== '') {
                    label = label.replace(
                        TextUtils.htmlEncode(this.state.filter),
                        '<mark>' + TextUtils.htmlEncode(this.state.filter) + '</mark>'
                    );
                } else {
                    textDecoded = TagUtils.transformTextForLockTags(item);
                }

                let classSelected = this.state.selectedItem === tagIndex ? 'active' : '';
                let indexTag = _.clone(tagIndex);
                missingItems.push(
                    <div
                        className={'item missing-tag ' + classSelected}
                        key={'missing' + indexTag}
                        data-original="item"
                        dangerouslySetInnerHTML={this.allowHTML(label)}
                        onClick={this.selectTag.bind(this, textDecoded)}
                        ref={(elem) => {
                            this.tagsRefs['item' + indexTag] = elem;
                        }}
                    />
                );
                tagIndex++;
            });
        }
        if (this.state.addedTags.length > 0) {
            addedItems.push(
                <div className="head-tag-list added" key={'added-header'}>
                    {' '}
                    Added <span className="style-tag">tags</span> in the target
                </div>
            );
            _.each(this.state.addedTags, (item, index) => {
                if (this.state.filter !== '' && this.state.totalTags.indexOf(item) === -1) {
                    return;
                }
                let textDecoded = TagUtils.transformTextForLockTags(item);
                let label =
                    textDecoded.indexOf('inside-attribute') !== -1
                        ? $(textDecoded).find('.inside-attribute').html()
                        : $(textDecoded).html();
                if (this.state.filter !== '') {
                    label = label.replace(
                        TextUtils.htmlEncode(this.state.filter),
                        '<mark>' + TextUtils.htmlEncode(this.state.filter) + '</mark>'
                    );
                } else {
                    textDecoded = TagUtils.transformTextForLockTags(item);
                }

                let classSelected = this.state.selectedItem === tagIndex ? 'active' : '';
                let indexTag = _.clone(tagIndex);
                addedItems.push(
                    <div
                        className={'item added-tag ' + classSelected}
                        key={'added' + indexTag}
                        data-original="item"
                        dangerouslySetInnerHTML={this.allowHTML(label)}
                        onClick={this.selectTag.bind(this, textDecoded)}
                        ref={(elem) => {
                            this.tagsRefs['item' + indexTag] = elem;
                        }}
                    />
                );
                tagIndex++;
            });
        }

        if (missingItems.length <= 1 && addedItems.length <= 1) {
            menuItems.push(
                <div className={'item no-results'} key={0} data-original="item">
                    No matches
                </div>
            );
            return (
                <div className="ui vertical menu">
                    <div>{menuItems}</div>
                </div>
            );
        }
        return (
            <div className="ui vertical menu">
                {missingItems.length > 1 && <div>{missingItems}</div>}
                {addedItems.length > 1 && <div>{addedItems}</div>}
            </div>
        );
    }

    openTagAutocompletePanel() {
        try {
            if ($('.selected', UI.editarea).length) {
                let range = window.getSelection().getRangeAt(0);
                TextUtils.setCursorAfterNode(range, $('.selected', UI.editarea)[0]);
            }
            var endCursor = document.createElement('span');
            endCursor.setAttribute('class', 'tag-autocomplete-endcursor');
            TextUtils.insertNodeAtCursor(endCursor);
        } catch (e) {
            console.log('Fail to insert tag', e);
        }
    }

    chooseTagAutocompleteOption(tag) {
        try {
            if ($('.tag-autocomplete-endcursor', UI.editarea).length === 0) {
                this.openTagAutocompletePanel();
            }
            TextUtils.setCursorPosition($('.tag-autocomplete-endcursor', UI.editarea)[0]);
            // CursorUtils.saveSelection();
        } catch (e) {
            console.log(e);
        }
        // Todo: refactor this part
        let editareaClone = UI.editarea.clone();
        if ($('.selected', $(editareaClone)).length) {
            if ($('.selected', $(editareaClone)).hasClass('inside-attribute')) {
                $('.selected', $(editareaClone)).parent('span.locked').remove();
            } else {
                $('.selected', $(editareaClone)).remove();
            }
        }
        let regeExp =
            this.state.filter !== '' &&
            new RegExp(
                '(' +
                    escapeStringRegexp(TextUtils.htmlEncode(this.state.filter)) +
                    ')?(<span class="tag-autocomplete-endcursor">)',
                'gi'
            );
        let regStartTarget = new RegExp(
            '(<span class="tag-autocomplete-endcursor"><\\/span>)(' +
                tag.trim() +
                ').*?(&lt;)+' +
                TextUtils.htmlEncode(this.state.filter),
            'gi'
        );

        editareaClone
            .find('.rangySelectionBoundary')
            .before(editareaClone.find('.rangySelectionBoundary + .tag-autocomplete-endcursor'));
        editareaClone
            .find('.tag-autocomplete-endcursor')
            .after(editareaClone.find('.tag-autocomplete-endcursor').html());
        editareaClone.find('.tag-autocomplete-endcursor').html('');

        editareaClone.html(editareaClone.html().replace(regStartTarget, '$2$3$1'));
        this.state.filter !== '' && editareaClone.html(editareaClone.html().replace(regeExp, '$2'));

        editareaClone.html(
            editareaClone
                .html()
                .replace(/&lt;(?:[a-z]*(?:&nbsp;)*["<\->\w\s\/=]*)?(<span class="tag-autocomplete-endcursor">)/gi, '$1')
        );
        editareaClone.html(
            editareaClone
                .html()
                .replace(/&lt;(?:[a-z]*(?:&nbsp;)*["\w\s\/=]*)?(<span class="tag-autocomplete-endcursor"\>)/gi, '$1')
        );

        var ph = '';
        if ($('.rangySelectionBoundary', editareaClone).length) {
            // click, not keypress
            ph = $('.rangySelectionBoundary', editareaClone)[0].outerHTML;
        }

        $('.rangySelectionBoundary', editareaClone).remove();
        $('.tag-autocomplete-endcursor', editareaClone).after(ph);
        $('.tag-autocomplete-endcursor', editareaClone).before(tag.trim()); //Trim to remove space at the end
        $('.tag-autocomplete, .tag-autocomplete-endcursor', editareaClone).remove();

        //Close menu
        SegmentActions.closeTagsMenu();
        $('.tag-autocomplete-endcursor').remove();

        let cleanTag = TextUtils.htmlEncode(TagUtils.cleanTextFromPlaceholdersSpan(tag));
        SegmentActions.replaceEditAreaTextContent(
            UI.getSegmentId(UI.currentSegment),
            UI.getSegmentFileId(UI.currentSegment),
            editareaClone.html(),
            cleanTag.trim().length
        );
        setTimeout(function () {
            UI.segmentQA(UI.currentSegment);
            TagUtils.checkTagProximity();
        });
    }

    selectTag(tag) {
        this.chooseTagAutocompleteOption(tag);
    }

    allowHTML(string) {
        return { __html: string };
    }

    handleKeydownFunction(event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            let tag = this.state.totalTags[this.state.selectedItem];
            if (!_.isUndefined(tag)) {
                tag = TagUtils.transformTextForLockTags(tag);
                this.selectTag(tag);
            }
        } else if (event.key === 'Escape') {
            event.preventDefault();
            //Close menu
            SegmentActions.closeTagsMenu();
            $('.tag-autocomplete-endcursor').remove();
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            this.setState({
                selectedItem: this.getNextIdx('prev'),
            });
        } else if (event.key === 'ArrowDown') {
            event.preventDefault();
            this.setState({
                selectedItem: this.getNextIdx('next'),
            });
        } else if (event.key === 'Backspace') {
            this.state.filter.length > 0 && this.filterTags(event.key);
            this.state.filter.length === 0 && SegmentActions.closeTagsMenu();
        } else if (
            event.code === 'Space' ||
            (event.keyCode >= 48 && event.keyCode <= 90) ||
            (event.keyCode >= 96 && event.keyCode <= 111) ||
            (event.keyCode >= 186 && event.keyCode <= 222)
        ) {
            this.filterTags(event.key);
        }
    }

    filterTags(newCharacter) {
        let filter;
        let tags;
        if (newCharacter === ' ' && this.state.filter === '') {
            return;
        }

        if (newCharacter === 'Backspace') {
            filter = this.state.filter.substring(0, this.state.filter.length - 1);
            tags = this.state.missingTags.concat(this.state.addedTags);
        } else {
            filter = this.state.filter + newCharacter;
            tags = _.clone(this.state.totalTags);
        }
        let filteredTags = _.filter(tags, (tag) => {
            if (tag.indexOf('equiv-text') > -1) {
                let tagHtml = TagUtils.transformTextForLockTags(tag);
                return $(tagHtml).find('.inside-attribute').text().indexOf(filter) !== -1;
            } else {
                return TextUtils.htmlDecode(tag).indexOf(filter) !== -1;
            }
        });

        this.setState({
            selectedItem: 0,
            totalTags: filteredTags,
            filter: filter,
        });
    }

    handleResizeEvent(event) {
        let sel = window.getSelection();
        if ($(sel.focusNode).closest('.editarea').length > 0) {
            let coord = this.getSelectionCoords();
            this.setState({
                coord,
            });
        }
    }

    getNextIdx(direction) {
        let idx = this.state.selectedItem;
        let length = this.state.totalTags.length;
        switch (direction) {
            case 'next':
                return (idx + 1) % length;
            case 'prev':
                return (idx === 0 && length - 1) || idx - 1;
            default:
                return idx;
        }
    }

    componentDidMount() {
        document.addEventListener('keydown', this.handleKeydownFunction);
        window.addEventListener('resize', this.handleResizeEvent);
        $('#outer').on('scroll', this.handleResizeEvent);
        this.openTagAutocompletePanel();
        UI.tagMenuOpen = true;
    }

    componentWillUnmount() {
        document.removeEventListener('keydown', this.handleKeydownFunction);
        window.removeEventListener('resize', this.handleResizeEvent);
        $('#outer').off('scroll', this.handleResizeEvent);
        $('.tag-autocomplete-endcursor').remove();
        UI.tagMenuOpen = false;
    }

    componentDidUpdate(prevProps, prevState) {
        // only scroll into view if the active item changed last render
        if (this.state.selectedItem !== prevState.selectedItem) {
            this.ensureActiveItemVisible();
        }
    }

    ensureActiveItemVisible() {
        var itemComponent = this.tagsRefs['item' + this.state.selectedItem];
        if (itemComponent) {
            var domNode = ReactDOM.findDOMNode(itemComponent);
            this.scrollElementIntoViewIfNeeded(domNode);
        }
    }

    scrollElementIntoViewIfNeeded(domNode) {
        var containerDomNode = ReactDOM.findDOMNode(this.menu);
        $(containerDomNode).animate(
            {
                scrollTop: $(domNode)[0].offsetTop - 60,
            },
            150
        );
    }

    render() {
        let style = {
            position: 'absolute',
            zIndex: 2,
            maxHeight: this.menuHeight + 'px',
            overflowY: 'auto',
            top: this.state.coord.y,
            left: this.state.coord.x,
        };

        let tags;
        try {
            tags = this.getItemsMenuHtml();
        } catch (e) {
            console.error('Not supported tags');
        }
        return (
            <div
                className="tags-auto-complete-menu"
                style={style}
                ref={(menu) => {
                    this.menu = menu;
                }}
            >
                {tags}
            </div>
        );
    }
}

export default TagsMenu;
