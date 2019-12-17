/**
 * React Component .

 */
import React  from 'react';
import SegmentStore  from '../../stores/SegmentStore';
import SegmentFilter from "../header/cattol/segment_filter/segment_filter";
import SegmentUtils from "../../utils/segmentUtils";

class SegmentButton extends React.Component {

    constructor(props) {
        super(props);

    }

    clickOnTranslatedButton(event) {
        this.props.updateTranslation();
        UI.clickOnTranslatedButton(event.currentTarget);
    }

    clickOnApprovedButton(event) {
        this.props.updateTranslation();
        UI.clickOnApprovedButton(event.target);
    }

    goToNextRepetition(event, status) {
        this.props.updateTranslation();
        SegmentFilter.goToNextRepetition(event.currentTarget, status);
    }

    goToNextRepetitionGroup(event, status) {
        this.props.updateTranslation();
        SegmentFilter.goToNextRepetitionGroup(event.currentTarget, status);
    }

    clickOnGuessTags(e) {
        e.preventDefault();
        $(e.target).addClass('disabled');
        UI.startSegmentTagProjection(this.props.segment.sid);
        return false;
    }

    getButtons() {
        let html;
        if (this.props.isReview){
            //Revise Default, Extended
            html = this.getReviewButtons()
        } else {
            //Translate
            html = this.getTranslateButtons()
        }
        return html;
    }

    getReviewButtons(){
        const classDisable = (this.props.disabled) ? 'disabled' : '';
        let nextButton, currentButton;
        let nextSegment = SegmentStore.getNextSegment(this.props.segment.sid, this.props.segment.fid);
        let enableGoToNext = !_.isUndefined(nextSegment) && nextSegment.status !== "APPROVED";
        const filtering = (SegmentFilter.enabled() && SegmentFilter.filtering() && SegmentFilter.open);
        const className = ReviewExtended.enabled() ? "revise-button-" + ReviewExtended.number : '';
        enableGoToNext = ReviewExtended.enabled() ? enableGoToNext && ( nextSegment.revision_number === config.revisionNumber || _.isNull(nextSegment.revision_number) )  : enableGoToNext;
        nextButton = (enableGoToNext)? (
                <li>
                    <a id={'segment-' + this.props.segment.sid +'-nexttranslated'}
                       onClick={(event)=>this.clickOnApprovedButton(event)}
                       className={"btn next-unapproved "+ classDisable + " "+ className } data-segmentid={'segment-' + this.props.segment.sid}
                       title="Revise and go to next translated"> A+>>
                    </a>
                    <p>
                        {(UI.isMac) ? ('CMD') : ('CTRL')}
                        SHIFT+ENTER
                    </p>
                </li>) :
            (null);
        currentButton = <li><a id={'segment-' + this.props.segment.sid + '-button-translated'}
                               data-segmentid={'segment-' + this.props.segment.sid}
                               onClick={(event)=>this.clickOnApprovedButton(event)}
                               className={'approved ' + classDisable + " " + className} > {config.status_labels.APPROVED} </a><p>
            {(UI.isMac) ? 'CMD' : 'CTRL'} ENTER
        </p></li>;

        if (filtering) {
            nextButton = null;
            var data = SegmentFilter.getStoredState();
            var filterinRepetitions = data.reactState && data.reactState.samplingType === "repetitions";
            if (filterinRepetitions) {
                nextButton =<React.Fragment>
                    <li><a id={"segment-" + this.props.segment.sid+"-nextrepetition"}
                           onClick={(e)=>this.goToNextRepetition(e, 'approved')}
                           className={"next-review-repetition ui green button " + className}
                           data-segmentid={"segment-"+ this.props.segment.sid}
                           title="Revise and go to next repetition">REP ></a>
                    </li>
                    <li>
                        <a id={"segment-" + this.props.segment.sid +"-nextgrouprepetition"}
                           onClick={(e)=>this.goToNextRepetitionGroup(e, 'approved')}
                           className={"next-review-repetition-group ui green button " + className}
                           data-segmentid={"segment-" + this.props.segment.sid}
                           title="Revise and go to next repetition group">REP >></a>
                    </li>
                </React.Fragment>;

            }
        }
        return <ul className="buttons" data-mount="main-buttons" id={"segment-" + this.props.segment.sid + "-buttons"}>
            {nextButton}
            {currentButton}
        </ul>
    }


    getTranslateButtons(){
        const classDisable = (this.props.disabled) ? 'disabled' : '';

        let nextButton, currentButton;
        const filtering = (SegmentFilter.enabled() && SegmentFilter.filtering() && SegmentFilter.open);
        let nextSegment = SegmentStore.getNextSegment(this.props.segment.sid, this.props.segment.fid);
        let enableGoToNext = !_.isUndefined(nextSegment) && ( nextSegment.status !== "NEW" && nextSegment.status !== "DRAFT" );
        //TODO Store TP Information in the SegmentsStore
        this.currentSegmentTPEnabled = SegmentUtils.checkCurrentSegmentTPEnabled(this.props.segment);
        if (this.currentSegmentTPEnabled) {
            nextButton = "";
            currentButton = <li>
                                <a id={'segment-' + this.props.segment.sid + '-button-guesstags'}
                                   onClick={(e)=>this.clickOnGuessTags(e)}
                                   data-segmentid={'segment-' + this.props.segment.sid}
                                    className={"guesstags " + classDisable} > GUESS TAGS
                                </a>
                            <p>
                                {UI.isMac ? ('CMD') : ('CTRL') }
                                ENTER
                            </p>
                        </li>;
        } else {
            nextButton = (enableGoToNext)? (
                <li>
                    <a id={'segment-' + this.props.segment.sid +'-nextuntranslated'} onClick={(e)=>this.clickOnTranslatedButton(e)}
                       className={"btn next-untranslated " + classDisable} data-segmentid={'segment-' + this.props.segment.sid}
                       title="Translate and go to next untranslated"> T+>>
                    </a>
                    <p>
                        {(UI.isMac) ? ('CMD') : ('CTRL')}
                        SHIFT+ENTER
                    </p>
                </li>) :
                (null);
            currentButton = this.getTranslateButton();
        }
        if (filtering) {
            nextButton = null;
            var data = SegmentFilter.getStoredState();
            var filterinRepetitions = data.reactState && data.reactState.samplingType === "repetitions";
            if (filterinRepetitions) {
                nextButton =<React.Fragment>
                            <li><a id={"segment-" + this.currentSegmentId+"-nextrepetition"}
                                   onClick={(e)=>this.goToNextRepetition(e, 'translated')}
                                   className="next-repetition ui primary button"
                                   data-segmentid={"segment-"+this.currentSegmentId}
                                   title="Translate and go to next repetition">REP ></a>
                            </li>
                            <li>
                                <a id={"segment-" + this.currentSegmentId +"-nextgrouprepetition"}
                                   onClick={(e)=>this.goToNextRepetitionGroup(e, 'translated')}
                                   className="next-repetition-group ui primary button"
                                    data-segmentid={"segment-" + this.currentSegmentId}
                                    title="Translate and go to next repetition group">REP >></a>
                            </li>
                    </React.Fragment>;

            }
        }

        return <ul className="buttons" data-mount="main-buttons" id={"segment-" + this.props.segment.sid + "-buttons"}>
            {nextButton}
            {currentButton}
        </ul>
    }

    getTranslateButton() {
        const classDisable = (this.props.disabled) ? 'disabled' : '';
        return <li><a id={'segment-' + this.props.segment.sid + '-button-translated'} onClick={(e)=>this.clickOnTranslatedButton(e)}
                      data-segmentid={'segment-' + this.props.segment.sid}
                      className={'translated ' +classDisable } > {config.status_labels.TRANSLATED} </a><p>
            {(UI.isMac) ? 'CMD' : 'CTRL'} ENTER
        </p></li>;
    }

    render() {
        if ( this.props.segment.muted ) return '';
        return this.getButtons()
    }
}

export default SegmentButton;
