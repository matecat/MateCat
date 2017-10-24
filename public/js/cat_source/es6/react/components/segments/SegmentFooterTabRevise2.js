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
            selectedText: ''
        };
    }

    trackChanges(sid, editareaText) {
        if (this.props.id_segment === sid) {


            // var source = UI.currentSegment.find('.original-translation').text();
            // source = UI.clenaupTextFromPleaceholders(source);

            // var target = editareaText.replace(/(<\s*\/*\s*(g|x|bx|ex|bpt|ept|ph|it|mrk)\s*.*?>)/gi, "");

            var source = this.originalTranslation;
            var target = editareaText;
            var diffHTML = trackChangesHTML(source, target);

            this.setState({
                diff: this.props.decodeTextFn(this.props.segment, diffHTML)
            });
        }
    }

    textSelected(event) {
        var selection = document.getSelection();
        if (this.textSelectedInsideSelectionArea(selection, $(this.diffElem))) {
            let data = this.getSelectionData(selection, $(this.diffElem));
            this.setState({
                selectedText: data.selected_string
            });
        }
    }

    textSelectedInsideSelectionArea( selection, container ) {
        // return $.inArray( selection.focusNode, container.contents() ) !==  -1 &&
        //     $.inArray( selection.anchorNode, container.contents() ) !== -1 &&
        return container.contents().text().indexOf(selection.focusNode.textContent)>=0 &&
            container.contents().text().indexOf(selection.anchorNode.textContent)>=0 &&
            selection.toString().length > 0 ;
    }

    getSelectionData(selection, container) {
        var data = {};
        data.start_node = $.inArray( selection.anchorNode, container.contents() );
        if (data.start_node<0) {
            //this means that the selection is probably ending inside a lexiqa tag,
            //or matecat tag/marking
            data.start_node = $.inArray( $(selection.anchorNode).parent()[0], container.contents() );
        }
        var nodes = container.contents();//array of nodes
        if (data.start_node ===0)
            data.start_offset =  selection.anchorOffset;
        else {
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
    }

    allowHTML(string) {
        return { __html: string };
    }

    componentDidMount() {
        console.log("Mount SegmentFooterRevise" + this.props.id_segment);
        SegmentStore.addListener(SegmentConstants.UPDATE_TRANSLATION, this.trackChanges.bind(this));
    }

    componentWillUnmount() {
        console.log("Unmount SegmentFooterRevise" + this.props.id_segment);
        SegmentStore.removeListener(SegmentConstants.UPDATE_TRANSLATION, this.trackChanges);
    }

    componentWillMount() {

    }

    allowHTML(string) {
        return { __html: string };
    }

    render() {

        return  <div key={"container_" + this.props.code}
                     className={"tab sub-editor "+ this.props.active_class + " " + this.props.tab_class}
                     id={"segment-" + this.props.id_segment + " " + this.props.tab_class}>
            {/*<div className="error-type">*/}
            {/*<h3>Select the type of issue</h3>*/}
            {/*<table>*/}
            {/*<thead>*/}
            {/*<tr>*/}
            {/*<th>None</th>*/}
            {/*<th>Enhancement</th>*/}
            {/*<th>Error</th>*/}
            {/*<th><a className="tooltip">?<span>Error that changes the meaning of the segment</span></a></th>*/}
            {/*</tr>*/}
            {/*</thead>*/}
            {/*<tbody>*/}
            {/*<tr>*/}
            {/*<td><input type="radio" name="t1" value="0" /></td>*/}
            {/*<td><input type="radio" name="t1" value="1" /></td>*/}
            {/*<td><input type="radio" name="t1" value="2" /></td>*/}
            {/*<td className="align-left">Tag issues (mismatches, whitespaces)</td>*/}
            {/*</tr>*/}
            {/*<tr>*/}
            {/*<td><input type="radio" name="t2" value="0" /></td>*/}
            {/*<td><input type="radio" name="t2" value="1" /></td>*/}
            {/*<td><input type="radio" name="t2" value="2" /></td>*/}
            {/*<td className="align-left">Translation errors (mistranslation, additions/omissions)</td>*/}
            {/*</tr>*/}
            {/*<tr>*/}
            {/*<td><input type="radio" name="t3" value="0" /></td>*/}
            {/*<td><input type="radio" name="t3" value="1" /></td>*/}
            {/*<td><input type="radio" name="t3" value="2" /></td>*/}
            {/*<td className="align-left">Terminology and translation consistency</td>*/}
            {/*</tr>*/}
            {/*<tr>*/}
            {/*<td><input type="radio" name="t4" value="0" /></td>*/}
            {/*<td><input type="radio" name="t4" value="1" /></td>*/}
            {/*<td><input type="radio" name="t4" value="2" /></td>*/}
            {/*<td className="align-left">Language quality (grammar, punctuation, spelling)</td>*/}
            {/*</tr>*/}
            {/*<tr>*/}
            {/*<td><input type="radio" name="t5" value="0" /></td>*/}
            {/*<td><input type="radio" name="t5" value="1" /></td>*/}
            {/*<td><input type="radio" name="t5" value="2" /></td>*/}
            {/*<td className="align-left">Style (readability, consistent style and tone)</td>*/}
            {/*</tr>*/}

            {/*</tbody>*/}
            {/*</table>*/}

            {/*</div>*/}
            <div className="error-type">
                {this.state.selectedText}
            </div>
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