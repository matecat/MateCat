/**
 * React Component for the editarea.

 */
var React = require('react');
var SegmentStore = require('../../stores/SegmentStore');
var SegmentConstants = require('../../constants/SegmentConstants');
var SegmentHeader = require('./SegmentHeader').default;
var SegmentFooter = require('./SegmentFooter').default;
var SegmentBody = require('./SegmentBody').default;

class Segment extends React.Component {

    constructor(props) {
        super(props);

        this.createSegmentClasses = this.createSegmentClasses.bind(this);
        this.hightlightEditarea = this.hightlightEditarea.bind(this);
        this.addClass = this.addClass.bind(this);
        this.setAsAutopropagated = this.setAsAutopropagated.bind(this);

        this.state = {
            segment_classes : [],
            modified: false,
            autopropagated: this.props.segment.autopropagated_from != 0
        };
    }

    createSegmentClasses() {
        var classes = [];
        var splitGroup = this.props.segment.split_group || [];
        var readonly = ((this.props.segment.readonly == 'true')||($('body').hasClass('archived'))) ? true : false;
        if ( readonly ) {
            classes.push('readonly');
        }

        if ( this.props.segment.status ) {
            classes.push( 'status-' + this.props.segment.status.toLowerCase() );
        }
        else {
            classes.push('status-new');
        }

        if ( this.props.segment.sid == splitGroup[0] ) {
            classes.push( 'splitStart' );
        }
        else if ( this.props.segment.sid == splitGroup[splitGroup.length - 1] ) {
            classes.push( 'splitEnd' );
        }
        else if ( splitGroup.length ) {
            classes.push('splitInner');
        }
        if (this.props.enableTagProjection && (this.props.segment.status.toLowerCase() === 'draft' || this.props.segment.status.toLowerCase() === 'new') ){
            classes.push('enableTP');
            this.dataAttrTagged = "nottagged";
        } else {
            this.dataAttrTagged = "tagged";
        }
        if (this.props.isReviewImproved) {
            classes.push("reviewImproved");
        }
        return classes;
    }

    hightlightEditarea(sid) {
        if (this.props.segment.sid == sid) {
            /*  TODO REMOVE THIS CODE
             *  The segment must know about his classes
             */
            var classes = $('#segment-' + this.props.segment.sid).attr("class").split(" ");
            if (!!classes.indexOf("modified")) {
                classes.push("modified");
                this.setState({
                    segment_classes: classes
                });
            }
        }
    }

    addClass(sid, newClass) {
        if (this.props.segment.sid == sid) {
            var classes = this.state.segment_classes;
            if (classes.indexOf(newClass)) {
                classes.push(newClass);
                this.setState({
                    segment_classes: classes
                });
            }
        }
    }

    setAsAutopropagated(sid, propagation){
        if (this.props.segment.sid == sid) {
            this.setState({
                autopropagated: propagation,
            });
        }
    }

    componentDidMount() {
        console.log("Mount Segment" + this.props.segment.sid);
        SegmentStore.addListener(SegmentConstants.HIGHLIGHT_EDITAREA, this.hightlightEditarea);
        SegmentStore.addListener(SegmentConstants.ADD_SEGMENT_CLASS, this.addClass);
        SegmentStore.addListener(SegmentConstants.SET_SEGMENT_PROPAGATION, this.setAsAutopropagated);
    }

    componentWillUnmount() {
        console.log("Unmount Segment" + this.props.segment.sid);
        SegmentStore.removeListener(SegmentConstants.HIGHLIGHT_EDITAREA, this.hightlightEditarea);
        SegmentStore.removeListener(SegmentConstants.ADD_SEGMENT_CLASS, this.addClass);
        SegmentStore.removeListener(SegmentConstants.SET_SEGMENT_PROPAGATION, this.setAsAutopropagated);
    }

    /*componentWillReceiveProps(nextProps) {
        if (nextProps.splitGroup !== this.props.splitGroup) {
            this.setState({ splitGroup: nextProps.splitGroup });
        }
    }*/

    allowHTML(string) {
        return { __html: string };
    }

    render() {
        var job_marker = "";
        var timeToEdit = "";


        var segment_classes = this.state.segment_classes.concat(this.createSegmentClasses());
        var split_group = this.props.segment.split_group || [];
        var autoPropagable = (this.props.segment.repetitions_in_chunk != "1");
        var originalId = this.props.segment.sid.split('-')[0];

        if ( this.props.timeToEdit ) {
            this.segment_edit_min = this.props.segment.parsed_time_to_edit[1];
            this.segment_edit_sec = this.props.segment.parsed_time_to_edit[2];
        }

        var start_job_marker = this.props.segment.sid == config.first_job_segment;
        var end_job_marker = this.props.segment.sid == config.last_job_segment;
        if (start_job_marker) {
            job_marker = <span className={"start-job-marker"}/>;
        } else if ( end_job_marker) {
            job_marker = <span className={"end-job-marker"}/>;
        }

        if (this.props.timeToEdit) {
            timeToEdit = <span className="edit-min">{this.segment_edit_min}</span> + 'm' + <span className="edit-sec">{this.segment_edit_sec}</span> + 's';
        }

        return (
            <section id={"segment-" + this.props.segment.sid}
                     className={segment_classes.join(' ')}
                     data-hash={this.props.segment.segment_hash}
                     data-autopropagated={this.state.autopropagated}
                     data-propagable={autoPropagable}
                     data-version={this.props.segment.version}
                     data-split-group={split_group}
                     data-split-original-id={originalId}
                     data-tagmode="crunched"
                     data-tagprojection={this.dataAttrTagged}
                     data-fid={this.props.fid}>

                <div className="sid" title={this.props.segment.sid}>
                    <div className="txt">{this.props.segment.sid}</div>
                    <div className="actions">
                        <a className="split" href="#" title="Click to split segment">
                            <span className="icon-split"/>
                        </a>
                        <p className="split-shortcut">CTRL + S</p>
                    </div>
                </div>
                {job_marker}

                <div className="body">
                    <SegmentHeader sid={this.props.segment.sid} autopropagated={this.state.autopropagated}/>
                    <SegmentBody
                        segment={this.props.segment}
                        isReviewImproved={this.props.isReviewImproved}
                        decodeTextFn={this.props.decodeTextFn}
                        tagModesEnabled={this.props.tagModesEnabled}
                        speech2textEnabledFn={this.props.speech2textEnabledFn}
                    />
                    <div className="timetoedit"
                         data-raw-time-to-edit={this.props.segment.time_to_edit}>
                        {timeToEdit}
                    </div>
                    <SegmentFooter sid={this.props.segment.sid}/>
                </div>


                <ul className="statusmenu"/>

                {/*//!-- TODO: place this element here only if it's not a split --*/}
                <div className="segment-side-buttons">
                    <div data-mount="translation-issues-button" className="translation-issues-button" data-sid={this.props.segment.sid}></div>
                </div>
            </section>
        );
    }
}

export default Segment ;

