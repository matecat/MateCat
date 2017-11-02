/**
 * React Component .

 */
var React = require('react');
var SegmentConstants = require('../../constants/SegmentConstants');
var SegmentStore = require('../../stores/SegmentStore');
class SegmentFooterTabRevise2 extends React.Component {

    constructor(props) {
        super(props);
        this.originalTranslation = this.props.translation;
        this.state = {
            diff: this.props.decodeTextFn(this.props.segment, this.props.translation),
            selectedText: '',
            selectionObj: null,
            selectionWrappers: []
        };
    }

    trackChanges(sid, editareaText) {
        if (this.props.id_segment === sid) {
            var source = this.originalTranslation;
            var target = editareaText;
            var diffHTML = trackChangesHTML(source, target);
            let wrappers = this.getWrappers(diffHTML);
            this.setState({
                diff: this.props.decodeTextFn(this.props.segment, diffHTML),
                selectionWrappers: wrappers
            });
        }
    }

    textSelected(event) {
        var selection = window.getSelection();
        if (this.textSelectedInsideSelectionArea(selection, $(this.diffElem))) {
            let data = this.getSelectionData(selection);
            this.setState({
                selectedText: data.selected_string,
                selectionObj: data
            });
        } else {
            this.setState({
                selectedText: '',
                selectionObj: null
            });
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
                start: this.getNodeOffset(containerEl, range.startContainer) + this.totalOffsets(range.startContainer, range.startOffset),
                end: this.getNodeOffset(containerEl, range.endContainer) + this.totalOffsets(range.endContainer, range.endOffset),
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
        startNodeOffset = this.getNodeAndOffsetAt(containerEl, savedSel.start);
        endNodeOffset = this.getNodeAndOffsetAt(containerEl, savedSel.end);

        range.setStart(startNodeOffset.node, startNodeOffset.offset);
        range.setEnd(endNodeOffset.node, endNodeOffset.offset);

        var sel = window.getSelection();
        sel.removeAllRanges();
        sel.addRange(range);
    }

    saveSelectionWrapper(event) {
        event.preventDefault();
        let selectionsWrappers = this.state.selectionWrappers.slice(0);
        let selObj =  this.state.selectionObj;
        let errorsElem = $(".error-type").find('input:checked');
        let errors = [];
        _.forEach(errorsElem, function (elem) {
            let error = {
                name: $(elem).attr('name'),
                value: $(elem).attr('value')
            };
            errors.push(error);
        });
        selObj.errors =  errors;
        selObj.id = selectionsWrappers.length + 1;
        selectionsWrappers.push(selObj);
        this.setState({
            selectedText: '',
            selectionObj: null,
            selectionWrappers: selectionsWrappers
        });
    }

    getWrappers(newDiff) {
        return this.state.selectionWrappers.filter(function (wrapper) {
            let text = newDiff.substring(wrapper.start, wrapper.end);
            return (text === wrapper.selected_string)
        });

    }

    applyWrapper(idWrapper) {
        let self = this;
        let wrapper = _.find(this.state.selectionWrappers, function (item) {
            return item.id === idWrapper;
        });
        this.restoreSelection(wrapper);
    }

    getErrorsList() {
        if (this.state.selectionWrappers.length > 0) {
            let html = [];
            let self = this;
            _.forEach(this.state.selectionWrappers, function (item) {
                let error = <tr key={"error" + item.id}
                                className="error-list-item"
                                onClick={self.applyWrapper.bind(self, item.id)}
                                onMouseOver={self.applyWrapper.bind(self, item.id)}>
                        <td>{item.selected_string}</td>
                        <td>{item.errors[0].name}</td>
                        <td>{item.errors[0].value}</td>
                    </tr>;
                html.push(error);
            });
            return html;
        } else {
            return "";
        }
    }

    addIssues(sid, data) {
        if (this.props.id_segment === sid) {
            this.setState({
                selectedText: '',
                selectionObj: null,
                selectionWrappers: data
            });
        }
    }

    allowHTML(string) {
        return { __html: string };
    }

    componentDidMount() {
        console.log("Mount SegmentFooterRevise" + this.props.id_segment);
        SegmentStore.addListener(SegmentConstants.UPDATE_TRANSLATION, this.trackChanges.bind(this));
        SegmentStore.addListener(SegmentConstants.RENDER_REVISE_ISSUES, this.addIssues.bind(this));
    }

    componentWillUnmount() {
        console.log("Unmount SegmentFooterRevise" + this.props.id_segment);
        SegmentStore.removeListener(SegmentConstants.UPDATE_TRANSLATION, this.trackChanges);
        SegmentStore.removeListener(SegmentConstants.RENDER_REVISE_ISSUES, this.addIssues);
    }

    componentWillMount() {

    }

    componentDidUpdate() {
        // this.applyWrappers()
    }

    allowHTML(string) {
        return { __html: string };
    }

    render() {
        let errorsHtml = this.getErrorsList();
        return  <div key={"container_" + this.props.code}
                     className={"tab sub-editor "+ this.props.active_class + " " + this.props.tab_class}
                     id={"segment-" + this.props.id_segment + " " + this.props.tab_class}>


            {this.state.selectedText !== '' ? (
                <div className="error-type">
                    <h3>Select the type of issue</h3>
                    <table>
                        <thead>
                        <tr>
                            <th>None</th>
                            <th>Enhancement</th>
                            <th>Error</th>
                            <th><a className="tooltip">?<span>Error that changes the meaning of the segment</span></a></th>
                        </tr>
                        </thead>
                        <tbody>
                        <tr>
                            <td><input type="radio" name="t1" value="0"/></td>
                            <td><input type="radio" name="t1" value="1"/></td>
                            <td><input type="radio" name="t1" value="2"/></td>
                            <td className="align-left">Tag issues (mismatches, whitespaces)</td>
                        </tr>
                        <tr>
                            <td><input type="radio" name="t2" value="0"/></td>
                            <td><input type="radio" name="t2" value="1"/></td>
                            <td><input type="radio" name="t2" value="2"/></td>
                            <td className="align-left">Translation errors (mistranslation, additions/omissions)</td>
                        </tr>
                        <tr>
                            <td><input type="radio" name="t3" value="0"/></td>
                            <td><input type="radio" name="t3" value="1"/></td>
                            <td><input type="radio" name="t3" value="2"/></td>
                            <td className="align-left">Terminology and translation consistency</td>
                        </tr>
                        <tr>
                            <td><input type="radio" name="t4" value="0"/></td>
                            <td><input type="radio" name="t4" value="1"/></td>
                            <td><input type="radio" name="t4" value="2"/></td>
                            <td className="align-left">Language quality (grammar, punctuation, spelling)</td>
                        </tr>
                        <tr>
                            <td><input type="radio" name="t5" value="0"/></td>
                            <td><input type="radio" name="t5" value="1"/></td>
                            <td><input type="radio" name="t5" value="2"/></td>
                            <td className="align-left">Style (readability, consistent style and tone)</td>
                        </tr>

                        </tbody>
                    </table>
                    <div className="mc-button blue-button"
                         style={{float: "left", marginTop: "10px"}}
                         onClick={this.saveSelectionWrapper.bind(this)}>
                        Save
                    </div>
                </div>
            ) : ( this.state.selectionWrappers.length > 0 ? (
                <div className="error-list" style={{float: "left", padding: "20px"}}>
                    <table>
                        <thead>
                        <tr>
                            <th>Errors</th>
                            <th>Type</th>
                            <th>Value</th>
                        </tr>
                        </thead>
                        <tbody>
                            {errorsHtml}
                        </tbody>
                    </table>
                </div>
                ) : (null)
            )}

            <div className="track-changes">
                <h3>Revision (track changes)</h3>
                <p ref={(node)=>this.diffElem=node}
                   dangerouslySetInnerHTML={ this.allowHTML(this.state.diff) }
                   onMouseUp={this.textSelected.bind(this)}/>
            </div>
        </div>
    }
}

export default SegmentFooterTabRevise2;