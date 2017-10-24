/**
 * React Component .

 */
var React = require('react');
var SegmentConstants = require('../../constants/SegmentConstants');
var SegmentStore = require('../../stores/SegmentStore');
class SegmentFooterTabRevise extends React.Component {

    constructor(props) {
        super(props);
        this.originalTranslation = this.props.translation;
        this.state = {
            diff: this.props.decodeTextFn(this.props.segment, this.props.translation)
        };
    }

    getOriginalTranslation() {
        let elem = document.createElement('div');
        elem.innerHTML = this.originalTranslation;
        return elem.textContent;
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
        this.diffElem.getSelection
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

            <div className="track-changes">
                <h3>Revision (track changes)</h3>
                <p ref={(node)=>this.diffElem=node}
                    dangerouslySetInnerHTML={ this.allowHTML(this.state.diff) }
                onMouseUp={this.textSelected.bind(this)}/>
            </div>
        </div>
    }
}

export default SegmentFooterTabRevise;