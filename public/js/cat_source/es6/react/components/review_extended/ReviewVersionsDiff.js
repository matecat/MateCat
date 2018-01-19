/**
 * React Component .

 */
let React = require('react');
class ReviewVersionsDiff extends React.Component {

    constructor(props) {
        super(props);
        this.handleDocumentClick = this.handleDocumentClick.bind(this);
    }

    textSelected(event) {
        if (this.props.textSelectedFn && this.props.selectable) {
            let selection = window.getSelection();
            if (selection.focusNode && this.textSelectedInsideSelectionArea(selection, $(this.diffPatchElem))) {
                let data = this.getSelectionData(selection);
                this.props.textSelectedFn(data);
            } else {
                this.props.removeSelection();
            }
        }
    }

    textSelectedInsideSelectionArea( selection, container ) {
        return container.contents().text().indexOf(selection.focusNode.textContent)>=0 &&
            container.contents().text().indexOf(selection.anchorNode.textContent)>=0 &&
            selection.toString().length > 0 ;
    }

    getSelectionData(selection) {
        let containerEl = $(this.diffPatchElem)[0];
        if (selection.rangeCount > 0) {
            var range = selection.getRangeAt(0);
            return {
                start_offset: this.getNodeOffset(containerEl, range.startContainer) + this.totalOffsets(range.startContainer, range.startOffset),
                end_offset: this.getNodeOffset(containerEl, range.endContainer) + this.totalOffsets(range.endContainer, range.endOffset),
                selected_string : selection.toString()
            };
        }
        else {
            return null;
        }
    }
    handleDocumentClick(evt)  {
        evt.stopPropagation();
        const area = ReactDOM.findDOMNode(this.diffPatchElem);

        if (this.diffPatchElem && !area.contains(evt.target) ) {
            this.textSelected(evt)
        }
    }
    /*
     Gets the offset of a node within another node. Text nodes are
     counted a n where n is the length. Entering (or passing) an
     element is one offset. Exiting is 0.
     */
    getNodeOffset(start, dest) {
        var offset = 0;

        var node = start;
        var stack = [];

        while (true) {
            if (node === dest) {
                return offset;
            }

            // Go into children
            if (node.firstChild) {
                // Going into first one doesn't count
                if (node !== start)
                    offset += 1;
                stack.push(node);
                node = node.firstChild;
            }
            // If can go to next sibling
            else if (stack.length > 0 && node.nextSibling) {
                // If text, count length (plus 1)
                if (node.nodeType === 3)
                    offset += node.nodeValue.length + 1;
                else
                    offset += 1;

                node = node.nextSibling;
            }
            else {
                // If text, count length
                if (node.nodeType === 3)
                    offset += node.nodeValue.length + 1;
                else
                    offset += 1;

                // No children or siblings, move up stack
                while (true) {
                    if (stack.length <= 1)
                        return offset;

                    var next = stack.pop();

                    // Go to sibling
                    if (next.nextSibling) {
                        node = next.nextSibling;
                        break;
                    }
                }
            }
        }
    }

    // Calculate the total offsets of a node
    calculateNodeOffset(node) {
        var offset = 0;

        // If text, count length
        if (node.nodeType === 3)
            offset += node.nodeValue.length + 1;
        else
            offset += 1;

        if (node.childNodes) {
            for (var i=0;i<node.childNodes.length;i++) {
                offset += this.calculateNodeOffset(node.childNodes[i]);
            }
        }

        return offset;
    }

    // Determine total offset length from returned offset from ranges
    totalOffsets(parentNode, offset) {
        if (parentNode.nodeType == 3)
            return offset;

        if (parentNode.nodeType == 1) {
            var total = 0;
            // Get child nodes
            for (var i=0;i<offset;i++) {
                total += this.calculateNodeOffset(parentNode.childNodes[i]);
            }
            return total;
        }

        return 0;
    };

    getNodeAndOffsetAt(start, offset) {
        var node = start;
        var stack = [];

        while (true) {
            // If arrived
            if (offset <= 0)
                return { node: node, offset: 0 };

            // If will be within current text node
            if (node.nodeType == 3 && (offset <= node.nodeValue.length))
                return { node: node, offset: Math.min(offset, node.nodeValue.length) };

            // Go into children (first one doesn't count)
            if (node.firstChild) {
                if (node !== start)
                    offset -= 1;
                stack.push(node);
                node = node.firstChild;
            }
            // If can go to next sibling
            else if (stack.length > 0 && node.nextSibling) {
                // If text, count length
                if (node.nodeType === 3)
                    offset -= node.nodeValue.length + 1;
                else
                    offset -= 1;

                node = node.nextSibling;
            }
            else {
                // No children or siblings, move up stack
                while (true) {
                    if (stack.length <= 1) {
                        // No more options, use current node
                        if (node.nodeType == 3)
                            return { node: node, offset: Math.min(offset, node.nodeValue.length) };
                        else
                            return { node: node, offset: 0 };
                    }

                    var next = stack.pop();

                    // Go to sibling
                    if (next.nextSibling) {
                        // If text, count length
                        if (node.nodeType === 3)
                            offset -= node.nodeValue.length + 1;
                        else
                            offset -= 1;

                        node = next.nextSibling;
                        break;
                    }
                }
            }
        }
    }

    getDiffHtml() {
        if (this.props.diffPatch && this.props.diffPatch.length > 0) {
            return trackChangesHTMLFromDiffArray(this.props.diffPatch);
        } else {
        	return ''
		}
    }

    allowHTML(string) {
        return { __html: string };
    }

    componentDidMount () {
        window.addEventListener('click', this.handleDocumentClick);
    }

    componentWillUnmount() {
        window.removeEventListener('click', this.handleDocumentClick);
    }

    render() {
		let classes;
        let diffHTML = this.getDiffHtml();
        diffHTML = UI.transformTextForLockTags(diffHTML);
        let diffClass = classnames({
            "re-track-changes": true,
            "no-select": !this.props.selectable,
        });
        if(this.props.customClass){
			classes = [diffClass,this.props.customClass].join(' ');
		}else{
			classes = diffClass;
		}
        return <div className={classes} ref={(node)=>this.diffPatchElem=node}
                  dangerouslySetInnerHTML={ this.allowHTML(diffHTML) }
                  onMouseUp={this.textSelected.bind(this)}/>
    }
}

ReviewVersionsDiff.defaultProps = {
    selectable: true
};

export default ReviewVersionsDiff;