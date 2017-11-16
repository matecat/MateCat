/**
 * React Component .

 */
let React = require('react');
let SegmentConstants = require('../../constants/SegmentConstants');
let SegmentStore = require('../../stores/SegmentStore');
let showdown = require( "showdown" );
class SegmentFooterTabMessages extends React.Component {

    constructor(props) {
        super(props);
    }

    getNotes() {
        let notesHtml = [];
        let self = this;
        if (this.props.notes) {
            this.props.notes.forEach(function (item, index) {
                if (item.note && item.note !== "") {
                    let html = <div className="note" key={"note-" + index}>
                        <span className="note-label">Note: </span>
                        <span> {item.note} </span>
                    </div>;
                    notesHtml.push(html);
                } else if (item.json && typeof item.json === "object" && Object.keys(item.json).length > 0) {
                    Object.keys(item.json).forEach(function (key, index) {
                        let html = <div className="note" key={"note-json" + index}>
                            <span className="note-label">{key.toUpperCase()}: </span>
                            <span> {item.json[key]} </span>
                        </div>;
                        notesHtml.push(html);
                    });

                } else if (typeof item.json === "string") {
                    let converter = new showdown.Converter();
                    let text = converter.makeHtml( item.json );
                    let html = <div key={"note-json" + index} className="note" style={{whiteSpace: "pre"}} dangerouslySetInnerHTML={self.allowHTML(text)}/>
                    notesHtml.push(html);
                }
            });
        }
        if (notesHtml.length === 0) {
            let html = <div className="note" key={"note-0"}>
                There are no notes available
            </div>;
            notesHtml.push(html);
        }
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
                            <div className="segments-notes-container">
                                {this.getNotes()}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
    }
}

export default SegmentFooterTabMessages;