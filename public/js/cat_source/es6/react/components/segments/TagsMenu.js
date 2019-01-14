/**
 * React Component for the warnings.

 */
var React = require('react');
var ReactDOM = require('react-dom');

class TagsMenu extends React.Component {

    constructor(props) {
        super(props);
        this.state = {};
        this.menuHeight = 300;
        this.state = {
            selectedItem: 0,
            tags : TagsMenu.arrayUnique(this.props.sourceTags)
        };
        this.handleKeydownFunction = this.handleKeydownFunction.bind(this);
        this.handleResizeEvent = this.handleResizeEvent.bind(this);
    }

    static arrayUnique(a) {
        return a.reduce(function(p, c) {
            if (p.indexOf(c) < 0) p.push(c);
            return p;
        }, []);
    };

    getSelectionCoords() {
        var win = window;
        var doc = win.document;
        var sel = doc.selection, range, rects, rect;
        var x = 0, y = 0;
        if (win.getSelection) {
            sel = win.getSelection();
            if (sel.rangeCount) {
                range = sel.getRangeAt(0).cloneRange();

                var span = doc.createElement("span");
                if (span.getClientRects) {
                    // Ensure span has dimensions and position by
                    // adding a zero-width space character
                    span.appendChild( doc.createTextNode( "\u200b" ) );
                    range.insertNode( span );
                    rect = span.getClientRects()[0];
                    x = rect.left;
                    y = rect.bottom;
                    var spanParent = span.parentNode;
                    spanParent.removeChild( span );

                    // Glue any broken text nodes back together
                    spanParent.normalize();

                }
            }
        }
        if ( (window.innerHeight - y) < (this.menuHeight + 200) ) {
            y = y - this.menuHeight - 20;
        }
        return { x: x, y: y };
    }

    getItemsMenuHtml() {
        let menuItems = [];

        _.each(this.state.tags, ( item, index ) => {
            let textDecoded = UI.transformTextForLockTags(item);
            let classSelected = ( this.state.selectedItem === index ) ? "active" : "";
            menuItems.push(<a className={"item " + classSelected} key={index} data-original="item"
                              dangerouslySetInnerHTML={ this.allowHTML(textDecoded) }
                              onClick={this.selectTag.bind(this, textDecoded)}
                              ref={(elem)=>{this["item" + index]=elem;}}
                              />
            );
        });
        return menuItems;
    }

    openTagAutocompletePanel() {
        var endCursor = document.createElement("span");
        endCursor.setAttribute('class', 'tag-autocomplete-endcursor');
        insertNodeAtCursor(endCursor);
    }

    chooseTagAutocompleteOption(tag) {
        // if(!$('.rangySelectionBoundary', UI.editarea).length) { // click, not keypress
            setCursorPosition($(".tag-autocomplete-endcursor", UI.editarea)[0]);
        // }
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
        $('.tag-autocomplete-endcursor', editareaClone).after(ph);
        $('.tag-autocomplete-endcursor', editareaClone).before(tag.trim()); //Trim to remove space at the end
        $('.tag-autocomplete, .tag-autocomplete-endcursor', editareaClone).remove();

        //Close menu
        SegmentActions.closeTagsMenu();
        $('.tag-autocomplete-endcursor').remove();

        SegmentActions.replaceEditAreaTextContent(UI.getSegmentId(UI.currentSegment), UI.getSegmentFileId(UI.currentSegment), editareaClone.html());
        setTimeout(function () {
            restoreSelection();
        });
        UI.segmentQA(UI.currentSegment);
    }

    selectTag(tag) {
        this.chooseTagAutocompleteOption(tag);
    }

    allowHTML(string) {
        return { __html: string };
    }

    handleKeydownFunction( event) {
        if ( event.key === 'Enter' ) {
            event.preventDefault();
            let tag = this.state.tags[this.state.selectedItem];
            if ( !_.isUndefined(tag) ) {
                tag = UI.transformTextForLockTags(tag);
                this.selectTag(tag)
            }
        } else if ( event.key ===  'Escape' ) {
            event.preventDefault();
            //Close menu
            SegmentActions.closeTagsMenu();
            $('.tag-autocomplete-endcursor').remove();
        } else if ( event.key ===  'ArrowUp' ) {
            event.preventDefault();
            this.setState({
                selectedItem: this.getNextIdx("prev")
            });
        } else if ( event.key ===  'ArrowDown' ) {
            event.preventDefault();
            this.setState({
                selectedItem: this.getNextIdx("next")
            });
        }
    }

    handleResizeEvent( event ) {
        this.forceUpdate()
    }

    getNextIdx(direction) {
        let idx = this.state.selectedItem;
        let length = this.state.tags.length;
        switch (direction) {
            case 'next': return (idx + 1) % length;
            case 'prev': return (idx === 0) && length - 1 || idx - 1;
            default:     return idx;
        }
    }


    componentDidMount() {
        document.addEventListener("keydown", this.handleKeydownFunction);
        window.addEventListener("resize", this.handleResizeEvent);
        $("#outer").on("scroll", this.handleResizeEvent);
        this.openTagAutocompletePanel();
        UI.tagMenuOpen = true;
    }

    componentWillUnmount() {
        document.removeEventListener( "keydown", this.handleKeydownFunction );
        window.removeEventListener( "resize", this.handleResizeEvent );
        $("#outer").off("scroll", this.handleResizeEvent);
        UI.tagMenuOpen = false;
    }

    componentDidUpdate(prevProps, prevState) {
        // only scroll into view if the active item changed last render
        if (this.state.selectedItem !== prevState.selectedItem) {
            this.ensureActiveItemVisible();
        }
    }

    ensureActiveItemVisible() {
        var itemComponent = this['item'+this.state.selectedItem];
        if (itemComponent) {
            var domNode = ReactDOM.findDOMNode(itemComponent);
            this.scrollElementIntoViewIfNeeded(domNode);
        }
    }

    scrollElementIntoViewIfNeeded(domNode) {
        var containerDomNode = ReactDOM.findDOMNode( this.menu );
        $(containerDomNode).animate({
            scrollTop: $(domNode)[0].offsetTop
        }, 150);
    }

    render() {
        var coord = this.getSelectionCoords();
        let style = {
            position: "fixed",
            zIndex: 2,
            maxHeight: "300px",
            overflowX: "auto",
            top: coord.y,
            left: coord.x
        };

        let tags = this.getItemsMenuHtml();
        return <div className="tags-auto-complete-menu" style={style}
                    ref={(menu)=>{this.menu=menu;}}>
                <div className="ui vertical menu">
                    {tags}
                </div>
        </div>;
    }
}

export default TagsMenu;

