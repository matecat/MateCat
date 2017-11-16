/**
 * React Component .

 */
let React = require('react');
let SegmentConstants = require('../../constants/SegmentConstants');
let SegmentStore = require('../../stores/SegmentStore');
let ReviewVersionDiff = require('../review/ReviewVersionsDiff').default;
let ReviewIssueSelectionPanel = require('../review/ReviewIssueSelectionPanel').default;

class SegmentFooterTabRevise2 extends React.Component {

    constructor(props) {
        super(props);
        this.originalTranslation = this.props.translation;
        this.state = {
            translation: this.props.translation,
            selectedText: '',
            selectionObj: null,
            selectionWrappers: []
        };
    }

    trackChanges(sid, editareaText) {
        if (this.props.id_segment === sid) {
            let wrappers = "";
            this.setState({
                translation: editareaText,
                selectionWrappers: wrappers
            });
        }
    }

    textSelected(data) {
        this.setState({
            selectedText: data.selected_string,
            selectionObj: data
        });
    }

    restoreSelection( savedSel ) {
        SegmentActions.showSelection(this.props.id_segment, savedSel);
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

    componentWillMount() {}

    componentDidUpdate() {}

    render() {
        let errorsHtml = this.getErrorsList();
        return  <div key={"container_" + this.props.code}
                     className={"tab sub-editor "+ this.props.active_class + " " + this.props.tab_class}
                     id={"segment-" + this.props.id_segment + " " + this.props.tab_class}>


            {/*{this.state.selectedText !== '' ? (*/}
                {/*<div className="error-type">*/}
                    {/*<ReviewIssueSelectionPanel*/}
                        {/*sid={this.props.id_segment}*/}
                        {/*selection={this.state.selectionObj}*/}
                        {/*segmentVersion={this.props.segment.version_number}*/}
                    {/*/>*/}
                {/*</div>*/}
            {/*) : ( this.state.selectionWrappers.length > 0 ? (*/}
                {/*<div className="error-list" style={{float: "left", padding: "20px"}}>*/}
                    {/*<table>*/}
                        {/*<thead>*/}
                        {/*<tr>*/}
                            {/*<th>Errors</th>*/}
                            {/*<th>Type</th>*/}
                            {/*<th>Value</th>*/}
                        {/*</tr>*/}
                        {/*</thead>*/}
                        {/*<tbody>*/}
                            {/*{errorsHtml}*/}
                        {/*</tbody>*/}
                    {/*</table>*/}
                {/*</div>*/}
                {/*) : (null)*/}
            {/*)}*/}

            <div className="track-changes">
                <h3>Revision (track changes)</h3>
                <ReviewVersionDiff
                    previousVersion={this.originalTranslation}
                    translation={this.state.translation}
                    segment={this.props.segment}
                    decodeTextFn={this.props.decodeTextFn}
                    selectable={false}
                />
            </div>
        </div>
    }
}

export default SegmentFooterTabRevise2;