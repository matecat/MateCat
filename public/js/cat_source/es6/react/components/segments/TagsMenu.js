/**
 * React Component for the warnings.

 */
var React = require('react');

class TagsMenu extends React.Component {

    constructor(props) {
        super(props);
        this.state = {};
        this.menuHeight = 300;
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
        let tags = TagsMenu.arrayUnique(this.props.sourceTags);
        _.each(tags, ( item, index ) => {
            let textDecoded = UI.transformTextForLockTags(item);
            menuItems.push(<a className="item" key={index} data-original="item"
                              dangerouslySetInnerHTML={ this.allowHTML(textDecoded) }/>);
        });
        return menuItems;
    }

    allowHTML(string) {
        return { __html: string };
    }

    componentDidMount() {
    }

    componentWillUnmount() {
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
        return <div className="tags-auto-complete-menu" style={style}>
                <div className="ui vertical menu">
                    {tags}
                </div>
        </div>;
    }
}

export default TagsMenu;

