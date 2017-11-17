/**
 * React Component .

 */
let React = require('react');
let SegmentConstants = require('../../constants/SegmentConstants');
let SegmentStore = require('../../stores/SegmentStore');
class ReviewVersionsDiff extends React.Component {

    constructor(props) {
        super(props);
        this.originalTranslation = this.props.translation;
        this.pippo = "pippo + " + Math.floor((Math.random() * 100) + 1);
        this.applyWrapper = this.applyWrapper.bind(this);
    }


    textSelected(event) {
        if (this.props.textSelectedFn && this.props.selectable) {
            let selection = window.getSelection();
            if (this.textSelectedInsideSelectionArea(selection, $(this.diffElem))) {
                let data = this.getSelectionData(selection);
                this.props.textSelectedFn(data, this.getDiffPatch());
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
        let containerEl = $(this.diffElem)[0];
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


    restoreSelection( savedSel) {
        if (!savedSel)
            return;
        let containerEl = $(this.diffElem)[0];
        var range = document.createRange();

        var startNodeOffset, endNodeOffset;
        startNodeOffset = this.getNodeAndOffsetAt(containerEl, parseInt(savedSel.start_offset));
        endNodeOffset = this.getNodeAndOffsetAt(containerEl, parseInt(savedSel.end_offset));

        range.setStart(startNodeOffset.node, startNodeOffset.offset);
        range.setEnd(endNodeOffset.node, endNodeOffset.offset);

        var sel = window.getSelection();
        sel.removeAllRanges();
        sel.addRange(range);
    }

    getWrappers(newDiff) {
        return this.state.selectionWrappers.filter(function (wrapper) {
            let text = newDiff.substring(wrapper.start_offset, wrapper.end_offset);
            return (text === wrapper.selected_string)
        });

    }

    applyWrapper(sid, issue) {
        if (this.props.sid === sid && this.props.versionNumber === parseInt(issue.translation_version)) {
            this.restoreSelection(issue);
        }
    }

    getDiffHtml() {
        if (this.props.diff && this.props.diff.length > 0) {
            return trackChangesHTMLFromDiffArray(this.props.diff);
        } else {
            return trackChangesHTML(this.props.previousVersion, this.props.translation);
        }
    }

    getDiffPatch() {
        if (this.props.diff && this.props.diff.length > 0) {
            return this.props.diff;
        } else {
            return getDiffPatch(this.props.previousVersion, this.props.translation);
        }
    }

    componentDidMount() {
        console.log("Mount " + this.pippo);
        SegmentStore.addListener(SegmentConstants.SHOW_SELECTION, this.applyWrapper);
    }

    componentWillUnmount() {
        console.log("Unmount " + this.pippo);
        SegmentStore.removeListener(SegmentConstants.SHOW_SELECTION, this.applyWrapper);
    }
    componentWillMount() {}

    componentDidUpdate() {

    }

    allowHTML(string) {
        return { __html: string };
    }

    render() {
        console.log("Render " + this.pippo);
        let diffHTML = this.getDiffHtml();
        let diffClass = classnames({
            "ui ignored message segment-diff-container": true,
            "no-select": !this.props.selectable
        });
        return <div className={diffClass} ref={(node)=>this.diffElem=node}
                  dangerouslySetInnerHTML={ this.allowHTML(diffHTML) }
                  onMouseUp={this.textSelected.bind(this)}/>
    }
}

ReviewVersionsDiff.defaultProps = {
    selectable: true
};

export default ReviewVersionsDiff;