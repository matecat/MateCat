/**
 * React Component .

 */
var React = require('react');
var SegmentConstants = require('../../constants/SegmentConstants');
var SegmentStore = require('../../stores/SegmentStore');
class SegmentFooterTabMessages extends React.Component {

    constructor(props) {
        super(props);
    }

    getNotes() {
        let notesHtml = [];
        this.props.notes.forEach(function (item, index) {
            if (item.note && item.note !== "") {
                let html = <li key={"note-" + index}>
                    <span className="note-label">Note: </span>
                    <span> {item.note} </span>
                </li>;
                notesHtml.push(html);
            } else if (item.json && item.json.length > 0) {
                item.json.forEach(function (jitem, index) {
                    let html = <li key={"note-json" + index}>
                        <span className="note-label">Note Json {index + 1}: </span>
                        <span> {jitem} </span>
                    </li>;
                    notesHtml.push(html);
                });

            }
        });
        return notesHtml;
    }

    componentDidMount() {
        console.log("Mount SegmentFooterMessages" + this.props.id_segment);

    }

    componentWillUnmount() {
        console.log("Unmount SegmentFooterMessages" + this.props.id_segment);

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
                <div className="overflow">
                    <div className="segment-notes-container">
                        <div className="segment-notes-panel-body">
                            <ul className="graysmall">
                                {this.getNotes()}
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
    }
}

export default SegmentFooterTabMessages;