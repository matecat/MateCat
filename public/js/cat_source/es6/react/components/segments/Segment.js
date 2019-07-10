/**
 * React Component for the editarea.

 */
let React = require('react');
let SegmentStore = require('../../stores/SegmentStore');
let SegmentActions = require('../../actions/SegmentActions');
let SegmentConstants = require('../../constants/SegmentConstants');
let SegmentHeader = require('./SegmentHeader').default;
let SegmentFooter = require('./SegmentFooter').default;
let IssuesContainer = require('./footer-tab-issues/SegmentFooterTabIssues').default;
let ReviewExtendedPanel = require('../review_extended/ReviewExtendedPanel').default;
let WrapperLoader = require('../../common/WrapperLoader').default;

let Immutable = require('immutable');

class Segment extends React.Component {

    constructor(props) {
        super(props);
        this.segmentStatus = {
            approved: 'APPROVED',
            translated : 'TRANSLATED',
            draft: 'DRAFT',
            new: 'NEW',
            rejected: 'REJECTED'
        };
        this.reviewExtendedFooter = 'extended-footer';

        this.createSegmentClasses = this.createSegmentClasses.bind(this);
        this.hightlightEditarea = this.hightlightEditarea.bind(this);
        this.addClass = this.addClass.bind(this);
        this.removeClass = this.removeClass.bind(this);
        this.setAsAutopropagated = this.setAsAutopropagated.bind(this);
        this.setSegmentStatus = this.setSegmentStatus.bind(this);
        this.addTranslationsIssues = this.addTranslationsIssues.bind(this);
        this.handleChangeBulk = this.handleChangeBulk.bind(this);

        let readonly = UI.isReadonlySegment(this.props.segment);
        this.secondPassLocked = ( this.props.segment.status.toUpperCase() === this.segmentStatus.approved && this.props.segment.revision_number === 2 && config.revisionNumber !== 2);
        this.state = {
            segment_classes : [],
            modified: false,
            autopropagated: this.props.segment.autopropagated_from != 0,
            status: this.props.segment.status,
            showTranslationIssues: false,
            unlocked: UI.isUnlockedSegment(this.props.segment),
            readonly: readonly,
            inBulk: false,
            tagProjectionEnabled: this.props.enableTagProjection && ( this.props.segment.status.toLowerCase() === 'draft' ||  this.props.segment.status.toLowerCase() === 'new')
            && !UI.checkXliffTagsInText(this.props.segment.translation) && UI.removeAllTags(this.props.segment.segment) !== '',
            showRevisionPanel: false,
            selectedTextObj: null
        }
    }

    createSegmentClasses() {
        let classes = [];
        let splitGroup = this.props.segment.split_group || [];
        let readonly = this.state.readonly;
        if ( readonly ) {
            classes.push('readonly');
        }

        if ( (this.props.segment.ice_locked === "1" && !readonly) || this.secondPassLocked) {
            if (this.props.segment.unlocked) {
                classes.push('ice-unlocked');
            } else {
                classes.push('readonly');
                classes.push('ice-locked');
            }
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
        if (this.state.tagProjectionEnabled && !this.props.segment.tagged){
            classes.push('enableTP');
            this.dataAttrTagged = "nottagged";
        } else {
            this.dataAttrTagged = "tagged";
        }
        if (this.props.segment.edit_area_locked) {
            classes.push("editAreaLocked");
        }
        if (this.props.segment.inBulk) {
            classes.push("segment-selected-inBulk");
        }
        if ( this.props.segment.muted ) {
            classes.push( 'muted' );
        }
        if ( this.props.segment.status.toUpperCase() === this.segmentStatus.approved && this.props.segment.revision_number ) {
            classes.push( 'approved-step-'+ this.props.segment.revision_number);
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
        if ( this.props.segment.sid == sid || sid === -1 || sid.split("-")[0] == this.props.segment.sid ) {
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
        if ( this.props.segment.sid == sid || sid === -1 || sid.indexOf(this.props.segment.sid) !== -1 ) {
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
            let classes = this.state.segment_classes.slice(0);
            let index = classes.findIndex(function ( item ) {
                return item.indexOf("status-") > -1;
            });

            if (index >= 0) {
                classes.splice(index, 1);
            }

            this.setState({
                segment_classes: classes,
                status: status
            });
        }
    }
    checkSegmentStatus(classes) {
        if ( classes.length === 0 ) return classes;
        // TODO: remove this
        //To fix a problem: sometimes the section segment has two different status
        let statusMatches = classes.join(' ').match(/status-/g);
        if ( statusMatches && statusMatches.length > 1 ) {
            let index = classes.findIndex(function ( item ) {
                return item.indexOf("status-new") > -1;
            });

            if (index >= 0) {
                classes.splice(index, 1);
            }

        }
        return classes;

    }
    isSplitted() {
        return (!_.isUndefined(this.props.segment.split_group));
    }

    isFirstOfSplit() {
        return (!_.isUndefined(this.props.segment.split_group) &&
        this.props.segment.split_group.indexOf(this.props.segment.sid) === 0);
    }

    addTranslationsIssues() {
        this.setState({
            showTranslationIssues: true,
        });
    }

    getTranslationIssues() {
        if (this.state.showTranslationIssues && !this.state.showRevisionPanel &&
            ( !(this.props.segment.readonly === 'true')  &&
                ( !this.isSplitted() || (this.isSplitted() && this.isFirstOfSplit()))
            )
        ) {
            return <TranslationIssuesSideButton
                    sid={this.props.segment.sid.split('-')[0]}
                    reviewType={this.props.reviewType}
                    segment={this.props.segment}
                    open={this.state.showRevisionPanel}
            />;
        }
        return null;
    }

    lockUnlockSegment(event) {
        event.preventDefault();
        event.stopPropagation();
        if ( !this.props.segment.unlocked && config.revisionNumber !== 2 && this.props.segment.revision_number === 2) {
            var props = {
                text: "You are about to edit a segment that has been approved in the 2nd pass review. The project owner and 2nd pass reviser will be notified.",
                successText: "Ok",
                successCallback: function() {
                    APP.ModalWindow.onCloseModal();
                }
            };
            APP.ModalWindow.showModalComponent(ConfirmMessageModal, props, "Modify locked and approved segment ");
        }
        SegmentActions.setSegmentLocked( this.props.segment, this.props.fid, !this.props.segment.unlocked );
    }

    checkSegmentClasses() {
        let classes =  this.state.segment_classes.slice();
        classes =  _.union(classes, this.createSegmentClasses());
        classes =  this.checkSegmentStatus(classes);
        if ( (classes.indexOf("muted") > -1 && classes.indexOf("editor") > -1) ){
            let indexEditor = classes.indexOf("editor");
            classes.splice(indexEditor, 1);
            let indexOpened = classes.indexOf("opened");
            classes.splice(indexOpened, 1);
        }
        return classes;
    }

    handleChangeBulk(event){
        if (event.shiftKey) {
            this.props.setBulkSelection(this.props.segment.sid, this.props.fid);
        } else {
            SegmentActions.toggleSegmentOnBulk(this.props.segment.sid, this.props.fid);
            this.props.setLastSelectedSegment(this.props.segment.sid, this.props.fid);
        }
    }

    openRevisionPanel(data) {
        if ( parseInt(data.sid) === parseInt(this.props.segment.sid) && !(this.props.segment.ice_locked == 1 &&  !this.props.segment.unlocked) ) {
            this.setState( {
                showRevisionPanel: true,
                showTranslationIssues: false,
                selectedTextObj : data.selection
            } );
        } else {
            this.setState({
                showRevisionPanel: false,
                showTranslationIssues: true,
                selectedTextObj: null
            });
        }
    }
    removeSelection() {
        var selection = document.getSelection();
        if ( this.section.contains(selection.anchorNode) ) {
            selection.removeAllRanges();
        }
        this.setState({
            selectedTextObj: null
        });
    }
    /*
    Is possible to close the single segmentIssue panel passing the id or all segments issue panel
    without passing anything
     */
    closeRevisionPanel(sid) {
        if ( (!sid  || (sid && parseInt(sid) === parseInt(this.props.segment.sid))) && this.state.showRevisionPanel ) {
            this.setState({
                showRevisionPanel: false,
                showTranslationIssues: true
            });
        }
    }

    allowHTML(string) {
        return { __html: string };
    }

    componentDidMount() {
        SegmentStore.addListener(SegmentConstants.HIGHLIGHT_EDITAREA, this.hightlightEditarea);
        SegmentStore.addListener(SegmentConstants.ADD_SEGMENT_CLASS, this.addClass);
        SegmentStore.addListener(SegmentConstants.REMOVE_SEGMENT_CLASS, this.removeClass);
        SegmentStore.addListener(SegmentConstants.SET_SEGMENT_PROPAGATION, this.setAsAutopropagated);
        SegmentStore.addListener(SegmentConstants.SET_SEGMENT_STATUS, this.setSegmentStatus);
        SegmentStore.addListener(SegmentConstants.MOUNT_TRANSLATIONS_ISSUES, this.addTranslationsIssues);
        //Review
        SegmentStore.addListener(SegmentConstants.OPEN_ISSUES_PANEL, this.openRevisionPanel.bind(this));
        SegmentStore.addListener(SegmentConstants.CLOSE_ISSUES_PANEL, this.closeRevisionPanel.bind(this));
        if (this.state.showRevisionPanel) {
            setTimeout(()=>{
                UI.openIssuesPanel(null, false)
            });
        }
    }


    componentWillUnmount() {
        SegmentStore.removeListener(SegmentConstants.HIGHLIGHT_EDITAREA, this.hightlightEditarea);
        SegmentStore.removeListener(SegmentConstants.ADD_SEGMENT_CLASS, this.addClass);
        SegmentStore.removeListener(SegmentConstants.REMOVE_SEGMENT_CLASS, this.removeClass);
        SegmentStore.removeListener(SegmentConstants.SET_SEGMENT_PROPAGATION, this.setAsAutopropagated);
        SegmentStore.removeListener(SegmentConstants.SET_SEGMENT_STATUS, this.setSegmentStatus);
        SegmentStore.removeListener(SegmentConstants.MOUNT_TRANSLATIONS_ISSUES, this.addTranslationsIssues);
        //Review
        SegmentStore.removeListener(SegmentConstants.OPEN_ISSUES_PANEL, this.openRevisionPanel);
        SegmentStore.removeListener(SegmentConstants.CLOSE_ISSUES_PANEL, this.closeRevisionPanel);
    }

    componentDidUpdate() {
        console.log("Update Segment" + this.props.segment.sid);
    }

    shouldComponentUpdate(nextProps, nextState) {
        return (
            !Immutable.fromJS(nextProps.segment).equals(Immutable.fromJS(this.props.segment)) ||
            !Immutable.fromJS(nextState.segment_classes).equals(Immutable.fromJS(this.state.segment_classes)) ||
            nextState.modified !== this.state.modified ||
            nextState.autopropagated !== this.state.autopropagated ||
            nextState.status !== this.state.status ||
            nextState.showTranslationIssues !== this.state.showTranslationIssues ||
            nextState.readonly !== this.state.readonly ||
            nextState.showRevisionPanel !== this.state.showRevisionPanel ||
            (nextState.selectedTextObj !== this.state.selectedTextObj)
        );
    }

    render() {
        let job_marker = "";
        let timeToEdit = "";

        let readonly = this.state.readonly ;
        let showLockIcon = this.props.segment.ice_locked === '1' ||
            this.secondPassLocked;
        let segment_classes = this.checkSegmentClasses();
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

                    {showLockIcon ? (
                        !readonly ? (
                            this.props.segment.unlocked ? (
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
                    ): (null)}

                    {!config.isLQA ? (
                        <div className="txt segment-add-inBulk">
                            <input type="checkbox"
                                   ref={(node)=>this.bulk=node}
                                   checked={this.props.segment.inBulk}
                                   onClick={this.handleChangeBulk}
                            />
                        </div>
                    ) : (null)}


                    {(this.props.segment.ice_locked !== '1' && config.splitSegmentEnabled) ? (
                        <div className="actions">
                            <button className="split" href="#" title="Click to split segment">
                                <i className="icon-split"/>
                            </button>
                            <p className="split-shortcut">CTRL + S</p>
                        </div>
                    ) : (null)}

                </div>
                {job_marker}

                <div className="body">
                    <SegmentHeader sid={this.props.segment.sid} autopropagated={this.state.autopropagated}/>
                    <SegmentBody
                        segment={this.props.segment}
                        readonly={this.state.readonly}
                        decodeTextFn={this.props.decodeTextFn}
                        tagModesEnabled={this.props.tagModesEnabled}
                        speech2textEnabledFn={this.props.speech2textEnabledFn}
                        enableTagProjection={this.props.enableTagProjection && !this.props.segment.tagged}
                        locked={!this.props.segment.unlocked && (this.props.segment.ice_locked === '1' || this.secondPassLocked) }
                        removeSelection={this.removeSelection.bind(this)}
                        reviewType={this.props.reviewType}
                    />
                    <div className="timetoedit"
                         data-raw-time-to-edit={this.props.segment.time_to_edit}>
                        {timeToEdit}
                    </div>
                    {SegmentFilter && SegmentFilter.enabled() ? (
                        <div className="edit-distance">Edit Distance: {this.props.segment.edit_distance}</div>
                    ) : (null)}

                    {config.isReview && this.props.reviewType === this.reviewExtendedFooter ? (
                        <IssuesContainer
                            segment={this.props.segment}
                            sid={this.props.segment.sid}
                        />
                    ) : (null)}

                    <SegmentFooter
                        segment={this.props.segment}
                        sid={this.props.segment.sid}
                        decodeTextFn={this.props.decodeTextFn}
                    />
                </div>

                {/*//!-- TODO: place this element here only if it's not a split --*/}
                <div className="segment-side-buttons">
                    <div data-mount="translation-issues-button" className="translation-issues-button" data-sid={this.props.segment.sid}>
                        {translationIssues}
                    </div>
                </div>
                <div className="segment-side-container">
                    {this.props.isReviewExtended && this.state.showRevisionPanel ? (
                        <div className="review-balloon-container">
                            {!this.props.segment.versions ? (
                                (null)
                            ) : (
                                <ReviewExtendedPanel
                                    reviewType={this.props.reviewType}
                                    segment={this.props.segment}
                                    sid={this.props.segment.sid}
                                    isReview={config.isReview}
                                    selectionObj={this.state.selectedTextObj}
                                    removeSelection={this.removeSelection.bind(this)}
                                />
                            )}

                        </div>
                    ) : (null)}
                </div>
            </section>
        );
    }
}

export default Segment ;

