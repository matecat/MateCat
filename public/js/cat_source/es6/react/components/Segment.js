/**
 * React Component for the editarea.

 */
var SegmentStore = require('../stores/SegmentStore');
class Segment extends React.Component {

    constructor(props) {
        super(props);
        this.createEscapedSegment = this.createEscapedSegment.bind(this);
    }

    createEscapedSegment() {
        var text = this.props.segment.segment;
        if (!$.parseHTML(text).length) {
            text = UI.stripSpans(text);
        }

        this.escapedSegment = htmlEncode(text.replace(/\"/g, "&quot;"));
        /* this is to show line feed in source too, because server side we replace \n with placeholders */
        this.escapedSegment = this.escapedSegment.replace( config.lfPlaceholderRegex, "\n" );
        this.escapedSegment = this.escapedSegment.replace( config.crPlaceholderRegex, "\r" );
        this.escapedSegment = this.escapedSegment.replace( config.crlfPlaceholderRegex, "\r\n" );
    }

    componentDidMount() {}

    componentWillUnmount() {}

    componentWillMount() {
        this.readonly = ((this.props.segment.readonly == 'true')||(UI.body.hasClass('archived'))) ? true : false;
        this.autoPropagated = this.props.segment.autopropagated_from != 0;
        this.autoPropagable = (this.props.segment.repetitions_in_chunk != "1");
        this.originalId = this.props.segment.sid.split('-')[0];

        this.createEscapedSegment();

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

                {/*Segment body start*/}
                <div className={"body"}>
                    <div className={"header toggle"} id={"segment-" + this.props.segment.sid + "-header"}></div>
                    <div className={"text"}>
                        <div className={"wrap"}>
                            <div className={"outersource"}>
                                <div className={"source item"}
                                     tabindex={0}
                                     id={"segment-" + this.props.segment.sid +"-source"}
                                     data-original={this.escapedSegment}>{decoded_text}</div>
                                <div className={"copy"} title="Copy source to target">
                                    <a href="#"/>
                                    <p>ALT+CTRL+I</p>
                                </div>
                                <div className="target item" id={"segment-" + this.props.segment.sid + "-target"}>
                                    <span class="hide toggle"/>

                                    {/*{{> translate/_text_area_container }}*/}

                                    <p className="warnings"/>

                                    <ul className="buttons toggle" data-mount="main-buttons" id="segment-{{segment.sid}}-buttons"/>
                                </div>

                            </div>
                        </div> <!-- .wrap -->
                        <div className="status-container">
                            <a href="#" title="{{status_change_title}}"
                               className="status" id={"segment-"+ this.props.segment.sid + "-changestatus"}/>
                        </div> <!-- .status-container -->
                    </div> <!-- .text -->
                    <div className="timetoedit"
                         data-raw-time-to-edit={segment.time_to_edit}>
                        {/*{{#if t}}*/}
                        <span className="edit-min">{{segment_edit_min}}</span>m
                        <span className="edit-sec">{{segment_edit_sec}}</span>s
                        {/*{{/if}}*/}
                    </div>
                    <div className="footer toggle"></div> <!-- .footer -->
                </div>
                {/*Segment body End */}

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

