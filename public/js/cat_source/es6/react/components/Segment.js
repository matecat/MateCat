/**
 * React Component for the editarea.

 */
var SegmentStore = require('../stores/SegmentStore');
class Segment extends React.Component {

    constructor(props) {
        super(props);
    }

    componentDidMount() {}

    componentWillUnmount() {}

    componentWillMount() {
        var readonly = ((this.readonly == 'true')||(UI.body.hasClass('archived'))) ? true : false;

    }

    render() {
        var job_marker = "";
        if (this.start_job_marker) {
            job_marker = <span className={"start-job-marker"}/>;
        }
        else if (this.end_job_marker) {
            job_marker = <span className={"end-job-marker"}/>;
        }

        return (
            <section id={"segment-" + this.props.segment.sid}
                     className={this.props.segment_classes}
                     data-hash={this.props.segment.segment_hash}
                     data-autopropagated={this.autoPropagated}
                     data-propagable={this.autoPropagable}
                     data-version={this.props.segment.version}
                     data-split-group={this.props.splitGroup}
                     data-split-original-id={this.originalId}
                     data-tagmode="crunched"
                     data-tagprojection={this.dataAttrTagged}>

                <a tabindex={"-1"} href={"#" + this.props.segment.sid}/>
                <div className={"sid"} title={this.props.segment.sid}>
                    <div className={"txt"}>{this.shortened_sid}</div>
                    <div className={"actions"}>
                        <a className={"split"} href={"#"} title={"Click to split segment"}>
                            <span className={"icon-split"}/>
                        </a>
                        <p className={"split-shortcut"}>CTRL + S</p>
                    </div>
                </div>
                {job_marker}

                {/*{{> translate/_segment_body}}*/}

                <!-- .body -->
                <ul className={"statusmenu"}/>

                //!-- TODO: place this element here only if it's not a split --
                <div className={"segment-side-buttons"}>
                    <div data-mount={"translation-issues-button"} className={"translation-issues-button"} data-sid={this.props.segment.sid}></div>
                </div>
            </section>
        );
    }
}

export default Segment ;

