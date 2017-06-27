
/**
 * React Component for the editarea.

 */
var React = require('react');

class QAComponent extends React.Component {
    get uniqEs6() {
        return this._uniqEs6;
    }

    set uniqEs6(value) {
        this._uniqEs6 = value;
    }

    constructor(props) {
        super(props);

        this.state = {
            total_issues: [],
            total_issues_selected: false,
            tag_issues: [],
            tag_issues_selected: false,
            translation_conflicts: [],
            translation_conflicts_selected: false,
            lxq_issues: [],
            lxq_selected: false,
            current_counter: 1,
            selected_box: ''
        };
        this.showTotal = false;
        this.getCurrentArray = this.getCurrentArray.bind(this);
        QAComponent.togglePanel = QAComponent.togglePanel.bind(this);
    }

    static togglePanel() {
        var qa_cont = $('.qa-container');
        qa_cont.toggleClass("qa-open");
        qa_cont.toggle();

        if ( !qa_cont.hasClass('qa-open') ) {
            this.setState({
                tag_issues_selected: false,
                lxq_selected: false,
                current_counter: 1,
                selected_box: ''
            });
        } else {
            var selectedBox = '';
            var issue_selected = false, lxq_selected = false, total_issue_selected = false, translation_conflicts_selected = false;
            if ( this.checkShowTotalIssues()) {
                total_issue_selected = true;
                selectedBox = 'total_issues';
            } else if ( this.state.tag_issues.length > 0 ) {
                issue_selected = true;
                selectedBox = 'tag_issues';
            } else if ( this.state.lxq_issues.length > 0 ) {
                lxq_selected = true;
                selectedBox = 'lxq';
            } else if ( this.state.translation_conflicts.length > 0 ) {
                translation_conflicts_selected = true;
                selectedBox = 'conflicts';
            }
            this.setState({
                total_issues_selected: total_issue_selected,
                tag_issues_selected: issue_selected,
                lxq_selected: lxq_selected,
                translation_conflicts_selected: translation_conflicts_selected,
                current_counter: 1,
                selected_box: selectedBox
            });
            var current_array = this.getCurrentArray();
            this.scrollToSegment(current_array[0]);
        }
    }

    getTotalIssues() {
        var total = 0;
        //Show the total only if more than 1 arrays exist
        $.each([this.state.lxq_issues, this.state.tag_issues, this.state.translation_conflicts], function (item) {
            if (item.length) {
                total++;
            }
        });
        this.showTotal = (total > 1);
        if (this.state.total_issues.length) {
            return this.state.total_issues.length;
        } else {
            return this.state.lxq_issues.length + this.state.tag_issues.length + this.state.translation_conflicts;
        }
    }

    scrollToSegment(segmentId) {
        if ( segmentId) {
            if ( $('#segment-' + segmentId).length ) {
                window.location.hash = segmentId;
            } else if($('#segment-' + segmentId + '-1').length) {
                window.location.hash = segmentId + '-1';
            }
            UI.scrollSegment($('#segment-' + segmentId));
        }
    }

    setTagIssues(issues) {
        var total = this.createTotalIssues(issues, this.state.lxq_issues, this.state.translation_conflicts);
        this.setState({
            tag_issues: issues,
            total_issues: total
        });
    }

    setTranslationConflitcts(issues) {
        var total = this.createTotalIssues(this.state.tag_issues, this.state.lxq_issues, issues);
        this.setState({
            translation_conflicts: issues,
            total_issues: total
        });
    }

    setLxqIssues(issues) {
        var total = this.createTotalIssues(this.state.tag_issues, issues, this.state.translation_conflicts);
        this.setState({
            lxq_issues: issues,
            total_issues: total
        });
    }

    createTotalIssues(tag_issues, lxq_issues, traslation_conflicts) {
        return QAComponent._uniqueArray(tag_issues.concat(lxq_issues, traslation_conflicts)).sort();
    }

    static _uniqueArray(arrArg) {
        return arrArg.filter((elem, pos, arr) => {
            return arr.indexOf(elem) == pos;
        });
    };

    selectBox(type) {
        switch (type) {
            case 'tag_issues':
                this.setState({
                    total_issues_selected: false,
                    tag_issues_selected: true,
                    lxq_selected: false,
                    translation_conflicts_selected: false,
                    current_counter: 1,
                    selected_box: type,
                });
                this.scrollToSegment(this.state.tag_issues[this.state.current_counter - 1]);
                break;
            case 'total_issues':
                this.setState({
                    total_issues_selected: true,
                    tag_issues_selected: false,
                    lxq_selected: false,
                    translation_conflicts_selected: false,
                    current_counter: 1,
                    selected_box: type,
                });
                this.scrollToSegment(this.state.total_issues[this.state.current_counter - 1]);
                break;
            case 'lxq':
                this.setState({
                    total_issues_selected: false,
                    lxq_selected: true,
                    tag_issues_selected: false,
                    translation_conflicts_selected: false,
                    current_counter: 1,
                    selected_box: type
                });
                this.scrollToSegment(this.state.lxq_issues[this.state.current_counter - 1]);
                break;
            case 'conflicts':
                this.setState({
                    total_issues_selected: false,
                    lxq_selected: false,
                    tag_issues_selected: false,
                    translation_conflicts_selected: true,
                    current_counter: 1,
                    selected_box: type
                });
                this.scrollToSegment(this.state.translation_conflicts[this.state.current_counter - 1]);
                break;
        }

    }

    getCurrentArray() {
        switch (this.state.selected_box) {
            case 'total_issues':
                return this.state.total_issues;
            case 'tag_issues':
                return this.state.tag_issues;
            case 'lxq':
                return this.state.lxq_issues;
            case 'conflicts':
                return this.state.translation_conflicts;
            default:
                return []
        }
    }

    moveUp() {
        if ( this.state.selected_box === '' ) return;
        var current_array = this.getCurrentArray();

        var counter = this.state.current_counter;
        var newCounter;
        if ( counter  === 1) {
            newCounter = current_array.length;
        }  else {
            newCounter = this.state.current_counter - 1;

        }
        this.setState({
            current_counter: newCounter
        });
        this.scrollToSegment(current_array[newCounter - 1]);
    }

    moveDown() {
        if ( this.state.selected_box === '' ) return;
        var current_array = this.getCurrentArray();

        var counter = this.state.current_counter;
        var newCounter;
        if ( counter  >= current_array.length) {
            newCounter = 1;

        } else {
            newCounter = this.state.current_counter + 1;
        }
        this.setState({
            current_counter: newCounter
        });
        this.scrollToSegment(current_array[newCounter - 1]);
    }

    updateIcon() {
        var totalIssues = this.getTotalIssues();
        if ( totalIssues > 0 ) {
            $('#notifbox').attr('class', 'warningbox').attr("title", "Click to see the segments with potential issues").find('.numbererror').text(totalIssues);
        } else {
            $('#notifbox').attr('class', 'notific').attr("title", "Well done, no errors found!").find('.numbererror').text('');
        }
    }

    componentDidUpdate() {
        this.updateIcon();
        var totalIssues = this.getTotalIssues();
        if ( totalIssues == 0 && $('.qa-container').hasClass('qa-open')) {
            QAComponent.togglePanel();
        }
    }

    checkShowTotalIssues() {
        var total = 0;
        //Show the total only if more than 1 arrays exist
        $.each([this.state.lxq_issues, this.state.tag_issues, this.state.translation_conflicts], function (index, item) {
            if (item.length) {
                total++;
            }
        });
        return (total > 1);
    }

    allowHTML(string) {
        return { __html: string };
    }

    render() {
        var tag_issues_html = '';
        var translation_conflicts_html = '';
        var total_issues_html = '';
        var counter;
        var lxq_container = '';
        var lxq_options = '';
        var buttonArrowsClass = 'qa-arrows-disabled';
        var counterLabel = 'Segment';
        var current_array = this.getCurrentArray();
        if ( (this.state.lxq_selected || this.state.tag_issues_selected || this.state.total_issues_selected) && current_array.length > 1 ) {
            buttonArrowsClass = 'qa-arrows-enabled';
            counterLabel = 'Segments';
        }
        if ( this.checkShowTotalIssues() ) {
            let selected = '';
            if ( this.state.total_issues_selected ) {
                counter = <div className="qa-counter">{this.state.current_counter + '/'+ this.state.total_issues.length + ' ' + counterLabel}</div>;
                selected = 'selected';
            }
            total_issues_html = <div className={"qa-issues-container "+ selected} onClick={this.selectBox.bind(this, 'total_issues')}>
                <span className="icon-qa-total-issues"/>
                <span className="qa-total-issues-counter">{this.state.total_issues.length}</span>
                All
            </div>;
        }
        if ( this.state.translation_conflicts.length > 0 ) {
            let selected = '';
            if ( this.state.translation_conflicts_selected ) {
                counter = <div className="qa-counter">{this.state.current_counter + '/'+ this.state.translation_conflicts.length + ' ' + counterLabel}</div>;
                selected = 'selected';
            }
            translation_conflicts_html = <div className={"qa-issues-container "+ selected} onClick={this.selectBox.bind(this, 'conflicts')}>
                <span className="icon-conflicts"/>
                <span className="qa-conflicts-issues-counter">{this.state.translation_conflicts.length}</span>
                Translation Conflicts
            </div>;
        }
        if ( this.state.tag_issues.length > 0 ) {
            let selected = '';
            if ( this.state.tag_issues_selected ) {
                counter = <div className="qa-counter">{this.state.current_counter + '/'+ this.state.tag_issues.length + ' ' + counterLabel}</div>;
                selected = 'selected';
            }
            tag_issues_html = <div className={"qa-issues-container "+ selected} onClick={this.selectBox.bind(this, 'tag_issues')}>
                <span className="icon-qa-issues"/>
                <span className="qa-issues-counter">{this.state.tag_issues.length}</span>
                Tag issues
            </div>;
        }
        if ( this.state.lxq_issues.length > 0 ) {
            let selected = '';
            if ( this.state.lxq_selected ) {
                counter = <div className="qa-counter">{this.state.current_counter +'/'+ this.state.lxq_issues.length + ' ' + counterLabel}</div>;
                selected = 'selected';
                lxq_options = <ul className="lexiqa-popup-items">

                    <li className="lexiqa-popup-item">QA checks and guide
                        <a className="lexiqa-popup-icon lexiqa-quide-icon icon-info" id="lexiqa-quide-link" href={config.lexiqaServer + '/documentation.html'} target="_blank" alt="Read the quick user guide of lexiqa"/>
                    </li>
                    <li className="lexiqa-popup-item">Full QA report
                        <a className="lexiqa-popup-icon lexiqa-report-icon icon-file" id="lexiqa-report-link" target="_blank" alt="Read the full QA report"
                        href={config.lexiqaServer + '/errorreport?id='+LXQ.partnerid+'-' + config.id_job +'&type='+(config.isReview?'revise':'translate')}/>
                    </li>
                </ul>;
            }
            lxq_container = <div className={"qa-lexiqa-container " + selected} onClick={this.selectBox.bind(this, 'lxq')}>
                <span className="icon-qa-lexiqa"/>
                <span className="qa-lexiqa-counter">{this.state.lxq_issues.length}</span>
                lexiQA
            </div>;

        }
        return  <div className="qa-container">
                    <div className="qa-container-inside">
                        <div className="qa-issues-types">
                            {total_issues_html}
                            {tag_issues_html}
                            {translation_conflicts_html}
                            {lxq_container}
                        </div>
                        {lxq_options}
                        <div className="qa-actions">
                            {counter}
                            <div className={'qa-arrows ' + buttonArrowsClass}>
                                <div className="qa-move-up" onClick={this.moveUp.bind(this)}>
                                    <span className="icon-qa-left-arrow"/>
                                </div>
                                <div className="qa-move-down" onClick={this.moveDown.bind(this)}>
                                    <span className="icon-qa-right-arrow"/>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
    }
}

export default QAComponent ;
