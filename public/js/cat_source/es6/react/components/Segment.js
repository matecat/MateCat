/**
 * React Component for the editarea.

 */
var SegmentHeader = require('../components/SegmentHeader').default;
var SegmentFooter = require('../components/SegmentFooter').default;
var SegmentBody = require('../components/SegmentBody').default;

class Segment extends React.Component {

    constructor(props) {
        super(props);
        this.createSegmentClasses = this.createSegmentClasses.bind(this);
    }

    createSegmentClasses() {
        var classes = [];
        var readonly = ((this.props.segment.readonly == 'true')||(UI.body.hasClass('archived'))) ? true : false;
        if ( readonly ) {
            classes.push('readonly');
        }

        if ( this.props.segment.status ) {
            classes.push( 'status-' + this.props.segment.status.toLowerCase() );
        }
        else {
            classes.push('status-new');
        }

        if ( this.props.segment.has_reference == 'true') {
            classes.push('has-reference');
        }

        if ( this.props.segment.sid == this.splitGroup[0] ) {
            classes.push( 'splitStart' );
        }
        else if ( this.props.segment.sid == this.splitGroup[this.splitGroup.length - 1] ) {
            classes.push( 'splitEnd' );
        }
        else if ( this.splitGroup.length ) {
            classes.push('splitInner');
        }
        this.segment_classes = classes;
    }

    componentDidMount() {
        console.log("Mount Segment" + this.props.segment.sid);
    }

    componentWillUnmount() {
        console.log("Unmount Segment" + this.props.segment.sid);
    }

    componentWillMount() {
        this.autoPropagated = this.props.segment.autopropagated_from != 0;
        this.autoPropagable = (this.props.segment.repetitions_in_chunk != "1");
        this.originalId = this.props.segment.sid.split('-')[0];

        this.splitGroup = this.props.segment.split_group || this.props.splitGroup || '';

        this.createSegmentClasses();

        if ( this.props.timeToEdit ) {
            this.segment_edit_min = this.props.segment.parsed_time_to_edit[1];
            this.segment_edit_sec = this.props.segment.parsed_time_to_edit[2];
        }

        if (UI.enableTagProjection && (UI.getSegmentStatus(this.props.segment) === 'draft' || UI.getSegmentStatus(this.props.segment) === 'new') ){
            this.segment_classes.push('enableTP');
            this.dataAttrTagged = "nottagged";
        } else {
            this.dataAttrTagged = "tagged";
        }

        this.shortened_sid = UI.shortenId( this.props.segment.sid );
        this.start_job_marker = this.props.segment.sid == config.first_job_segment;
        this.end_job_marker = this.props.segment.sid == config.last_job_segment;



    }
    allowHTML(string) {
        return { __html: string };
    }

    render() {
        var job_marker = "";
        var timeToEdit = "";
        if (this.start_job_marker) {
            job_marker = <span className={"start-job-marker"}/>;
        } else if (this.end_job_marker) {
            job_marker = <span className={"end-job-marker"}/>;
        }

        if (this.props.timeToEdit) {
            timeToEdit = <span className="edit-min">{this.segment_edit_min}</span> + m + <span className="edit-sec">{this.segment_edit_sec}</span> + s;
        }

        return (
            <section id={"segment-" + this.props.segment.sid}
                     className={this.segment_classes.join(' ')}
                     data-hash={this.props.segment.segment_hash}
                     data-autopropagated={this.autoPropagated}
                     data-propagable={this.autoPropagable}
                     data-version={this.props.segment.version}
                     data-split-group={this.splitGroup}
                     data-split-original-id={this.originalId}
                     data-tagmode="crunched"
                     data-tagprojection={this.dataAttrTagged}>

                <a tabindex="0" href={"#" + this.props.segment.sid}/>
                <div className={"sid"} title={this.props.segment.sid}>
                    <div className="txt" dangerouslySetInnerHTML={ this.allowHTML(this.shortened_sid) }></div>
                    <div className={"actions"}>
                        <a className={"split"} href={"#"} title={"Click to split segment"}>
                            <span className={"icon-split"}/>
                        </a>
                        <p className={"split-shortcut"}>CTRL + S</p>
                    </div>
                </div>
                {job_marker}

                <div className={"body"}>
                    <SegmentHeader sid={this.props.segment.sid}/>
                    <SegmentBody segment={this.props.segment}/>
                    <div className="timetoedit"
                         data-raw-time-to-edit={this.props.segment.time_to_edit}>
                        {timeToEdit}
                    </div>
                    <SegmentFooter sid={this.props.segment.sid}/>
                </div>


                <ul className={"statusmenu"}/>

                {/*//!-- TODO: place this element here only if it's not a split --*/}
                <div className={"segment-side-buttons"}>
                    <div data-mount={"translation-issues-button"} className={"translation-issues-button"} data-sid={this.props.segment.sid}></div>
                </div>
            </section>
        );
    }
}

export default Segment ;

