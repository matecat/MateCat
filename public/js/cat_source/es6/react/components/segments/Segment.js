/**
 * React Component for the editarea.

 */
let React = require('react');
let SegmentStore = require('../../stores/SegmentStore');
let SegmentConstants = require('../../constants/SegmentConstants');
let SegmentHeader = require('./SegmentHeader').default;
let SegmentFooter = require('./SegmentFooter').default;
let SegmentBody = require('./SegmentBody').default;
let TranslationIssuesSideButtons = require('../TranslationIssuesSideButton').default;
let Immutable = require('immutable');

class Segment extends React.Component {

    constructor(props) {
        super(props);

        this.createSegmentClasses = this.createSegmentClasses.bind(this);
        this.hightlightEditarea = this.hightlightEditarea.bind(this);
        this.addClass = this.addClass.bind(this);
        this.removeClass = this.removeClass.bind(this);
        this.setAsAutopropagated = this.setAsAutopropagated.bind(this);
        this.setSegmentStatus = this.setSegmentStatus.bind(this);
        this.addTranslationsIssues = this.addTranslationsIssues.bind(this);

        let readonly = UI.isReadonlySegment(this.props.segment);

        this.state = {
            segment_classes : [],
            modified: false,
            autopropagated: this.props.segment.autopropagated_from != 0,
            status: this.props.segment.status,
            showTranslationIssues: false,
            unlocked: (this.props.segment.ice_locked === "1" && !readonly) && UI.getFromStorage('unlocked-' + this.props.segment.sid),
            readonly: readonly
        }
    }

    createSegmentClasses() {
        let classes = [];
        let splitGroup = this.props.segment.split_group || [];
        let readonly = this.state.readonly;
        if ( readonly ) {
            classes.push('readonly');
        }

        if ( this.props.segment.ice_locked === "1" && !readonly) {
            if (UI.getFromStorage('unlocked-' + this.props.segment.sid)) {
                classes.push('ice-unlocked');
            } else {
                classes.push('readonly');
                classes.push('ice-locked');
            }
        }

        if ( this.state.status ) {
            classes.push( 'status-' + this.state.status.toLowerCase() );
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
        if (this.props.enableTagProjection && (this.state.status.toLowerCase() === 'draft' || this.state.status.toLowerCase() === 'new')
            && !UI.checkXliffTagsInText(this.props.segment.translation)){
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
            let classes = $('#segment-' + this.props.segment.sid).attr("class").split(" ");
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
            let self = this;
            let classes = this.state.segment_classes.slice();
            if (newClass.indexOf(' ') > 0) {
                let self = this;
                let classesSplit = newClass.split(' ');
                _.forEach(classesSplit, function (item) {
                    if (classes.indexOf(item) < 0) {
                        classes.push(item);
                    }
                })
            } else {
                if (classes.indexOf(newClass) < 0) {
                    classes.push(newClass);
                }
            }
            this.setState({
                segment_classes: classes
            });
        }
    }

    removeClass(sid, className) {
        if (this.props.segment.sid == sid) {
            let classes = this.state.segment_classes.slice();
            let removeFn = function (item) {
                let index = classes.indexOf(item);
                if (index > -1) {
                    classes.splice(index, 1);

                }
            };
            if ( className.indexOf(' ') > 0 ) {
                let self = this;
                let classesSplit = className.split(' ');
                _.forEach(classesSplit, function (item) {
                    removeFn(item);
                })
            } else {
                removeFn(className);
            }
            this.setState({
                segment_classes: classes
            });
        }
    }

    setAsAutopropagated(sid, propagation){
        if (this.props.segment.sid == sid) {
            this.setState({
                autopropagated: propagation,
            });
        }
    }
    setSegmentStatus(sid, status) {
        if (this.props.segment.sid == sid) {
            this.setState({
                status: status
            });
        }
    }

    isSplitted() {
        return (!_.isUndefined(this.props.segment.split_group));
    }

    isFirstOfSplit() {
        return (!_.isUndefined(this.props.split_group) &&
        this.props.segment.split_group.indexOf(this.props.segment.sid) === 0);
    }

    addTranslationsIssues() {
        this.setState({
            showTranslationIssues: true,
        });
    }

    getTranslationIssues() {
        if (this.state.showTranslationIssues &&
            (!(this.props.segment.readonly === 'true')  && !this.isSplitted()  ) ) {
            return <TranslationIssuesSideButtons
                    sid={this.props.segment.sid.split('-')[0]}
                    reviewType={this.props.reviewType}
                    segment={this.props.segment}
            />;
        }
        return null;
    }

    lockUnlockSegment(event) {
        event.preventDefault();
        event.stopPropagation();
        if (this.state.unlocked) {
            //TODO: move this to SegmentActions
            UI.removeFromStorage('unlocked-' + this.props.segment.sid);
            UI.closeSegment(UI.getSegmentById(this.props.segment.sid), 1);
        } else {
            UI.addInStorage('unlocked-'+ this.props.segment.sid, true);
            UI.editAreaClick(this.section);
        }
        this.setState({
            unlocked: !this.state.unlocked
        });
    }

    componentDidMount() {
        console.log("Mount Segment" + this.props.segment.sid);
        SegmentStore.addListener(SegmentConstants.HIGHLIGHT_EDITAREA, this.hightlightEditarea);
        SegmentStore.addListener(SegmentConstants.ADD_SEGMENT_CLASS, this.addClass);
        SegmentStore.addListener(SegmentConstants.REMOVE_SEGMENT_CLASS, this.removeClass);
        SegmentStore.addListener(SegmentConstants.SET_SEGMENT_PROPAGATION, this.setAsAutopropagated);
        SegmentStore.addListener(SegmentConstants.SET_SEGMENT_STATUS, this.setSegmentStatus);
        SegmentStore.addListener(SegmentConstants.MOUNT_TRANSLATIONS_ISSUES, this.addTranslationsIssues);
    }


    componentWillUnmount() {
        console.log("Unmount Segment" + this.props.segment.sid);
        SegmentStore.removeListener(SegmentConstants.HIGHLIGHT_EDITAREA, this.hightlightEditarea);
        SegmentStore.removeListener(SegmentConstants.ADD_SEGMENT_CLASS, this.addClass);
        SegmentStore.removeListener(SegmentConstants.REMOVE_SEGMENT_CLASS, this.removeClass);
        SegmentStore.removeListener(SegmentConstants.SET_SEGMENT_PROPAGATION, this.setAsAutopropagated);
        SegmentStore.removeListener(SegmentConstants.SET_SEGMENT_STATUS, this.setSegmentStatus);
        SegmentStore.removeListener(SegmentConstants.MOUNT_TRANSLATIONS_ISSUES, this.addTranslationsIssues);
    }

    componentDidUpdate() {
        console.log("Update Segment" + this.props.segment.sid);
    }

    shouldComponentUpdate(nextProps, nextState) {
        return (
            (!Immutable.fromJS(nextProps.segment).equals(Immutable.fromJS(this.props.segment))) ||
            (!Immutable.fromJS(nextState.segment_classes).equals(Immutable.fromJS(this.state.segment_classes))) ||
            (nextState.modified !== this.state.modified) ||
            (nextState.autopropagated !== this.state.autopropagated) ||
            (nextState.status !== this.state.status) ||
            (nextState.showTranslationIssues !== this.state.showTranslationIssues) ||
            (nextState.unlocked !== this.state.unlocked) ||
            (nextState.readonly !== this.state.readonly)
        );

        // segment_classes : [],
        //     modified: false,
        //     autopropagated: this.props.segment.autopropagated_from != 0,
        //     status: this.props.segment.status,
        //     showTranslationIssues: false,
        //     unlocked: (this.props.segment.ice_locked === "1" && !readonly) && UI.getFromStorage('locked-' + this.props.segment.sid),
        //     readonly: readonly
    }

    allowHTML(string) {
        return { __html: string };
    }

    render() {
        let job_marker = "";
        let timeToEdit = "";

        let readonly = this.state.readonly;
        let segment_classes = this.state.segment_classes.concat(this.createSegmentClasses());
        let split_group = this.props.segment.split_group || [];
        let autoPropagable = (this.props.segment.repetitions_in_chunk != "1");
        let originalId = this.props.segment.sid.split('-')[0];

        if ( this.props.timeToEdit ) {
            this.segment_edit_min = this.props.segment.parsed_time_to_edit[1];
            this.segment_edit_sec = this.props.segment.parsed_time_to_edit[2];
        }

        let start_job_marker = this.props.segment.sid == config.first_job_segment;
        let end_job_marker = this.props.segment.sid == config.last_job_segment;
        if (start_job_marker) {
            job_marker = <span className={"start-job-marker"}/>;
        } else if ( end_job_marker) {
            job_marker = <span className={"end-job-marker"}/>;
        }

        if (this.props.timeToEdit) {
            timeToEdit = <span className="edit-min">{this.segment_edit_min}</span> + 'm' + <span className="edit-sec">{this.segment_edit_sec}</span> + 's';
        }

        let translationIssues = this.getTranslationIssues();

        return (
            <section
                ref={(section)=>this.section=section}
                id={"segment-" + this.props.segment.sid}
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

                    {(this.props.segment.ice_locked !== '1' ) ? (
                        config.splitSegmentEnabled ? (
                        <div className="actions">
                            <a className="split" href="#" title="Click to split segment">
                                <span className="icon-split"/>
                            </a>
                            <p className="split-shortcut">CTRL + S</p>
                        </div>
                        ) : (null)
                    ) : (
                        !readonly ? (
                            this.state.unlocked ? (
                                <div className="ice-locked-icon"
                                     onClick={this.lockUnlockSegment.bind(this)}>
                                    <button className="unlock-button unlocked icon-unlocked3"/>
                                </div>
                            ) :(
                                <div className="ice-locked-icon"
                                     onClick={this.lockUnlockSegment.bind(this)}>
                                    <button className="icon-lock unlock-button locked"/>
                                </div>
                            )
                        ) : (null)
                    )}
                </div>
                {job_marker}

                <div className="body">
                    <SegmentHeader sid={this.props.segment.sid} autopropagated={this.state.autopropagated}/>
                    <SegmentBody
                        segment={this.props.segment}
                        readonly={this.state.readonly}
                        isReviewImproved={this.props.isReviewImproved}
                        decodeTextFn={this.props.decodeTextFn}
                        tagModesEnabled={this.props.tagModesEnabled}
                        speech2textEnabledFn={this.props.speech2textEnabledFn}
                        enableTagProjection={this.props.enableTagProjection}
                        locked={!this.state.unlocked && this.props.segment.ice_locked === '1'}
                    />
                    <div className="timetoedit"
                         data-raw-time-to-edit={this.props.segment.time_to_edit}>
                        {timeToEdit}
                    </div>
                    <SegmentFooter
                        segment={this.props.segment}
                        sid={this.props.segment.sid}
                        decodeTextFn={this.props.decodeTextFn}
                    />
                </div>


                <ul className="statusmenu"/>

                {/*//!-- TODO: place this element here only if it's not a split --*/}
                <div className="segment-side-buttons">
                    <div data-mount="translation-issues-button" className="translation-issues-button" data-sid={this.props.segment.sid}>
                        {translationIssues}
                    </div>
                </div>
            </section>
        );
    }
}

export default Segment ;

