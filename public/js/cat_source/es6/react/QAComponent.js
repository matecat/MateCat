
/**
 * React Component for the editarea.

 */
var React = require('react');

class QAComponent extends React.Component {

    constructor(props) {
        super(props);

        this.state = {
            issues: [],
            issues_selected: false,
            lxq_issues: [],
            lxq_selected: false,
            current_counter: 1,
            selected_box: ''
        };
        this.getCurrentArray = this.getCurrentArray.bind(this);
        QAComponent.togglePanel = QAComponent.togglePanel.bind(this);
    }

    static togglePanel() {
        $('.qa-container').toggleClass("qa-open");
        $('.qa-container').slideToggle();

        if ( !$('.qa-container').hasClass('qa-open') ) {
            this.setState({
                issues_selected: false,
                lxq_selected: false,
                current_counter: 1,
                selected_box: ''
            });
        } else {
            var selectedBox = '';
            var issue_selected = false, lxq_selected = false;
            if ( this.state.issues.length > 0 ) {
                issue_selected = true;
                selectedBox = 'issues';
            } else if ( this.state.lxq_issues.length > 0 ) {
                lxq_selected = true;
                selectedBox = 'lxq';
            }
            this.setState({
                issues_selected: issue_selected,
                lxq_selected: lxq_selected,
                current_counter: 1,
                selected_box: selectedBox
            });
            var current_array = this.getCurrentArray();
            this.scrollToSegment(current_array[0]);
        }
    }

    getTotalIssues() {
        return this.state.lxq_issues.length + this.state.issues.length;
    }

    scrollToSegment(segmentId) {
        if ( segmentId) {
            UI.scrollSegment($('#segment-' + segmentId));
            window.location.hash = segmentId;
        }
    }

    setIssues(issues) {
        this.setState({ issues: issues });
    }

    setLxqIssues(issues) {
        this.setState({ lxq_issues: issues });
    }

    selectBox(type) {
        switch (type) {
            case 'issues':
                this.setState({
                    issues_selected: true,
                    lxq_selected: false,
                    current_counter: 1,
                    selected_box: type,
                });
                this.scrollToSegment(this.state.issues[this.state.current_counter - 1]);
                break;
            case 'lxq':
                this.setState({
                    lxq_selected: true,
                    issues_selected: false,
                    current_counter: 1,
                    selected_box: type
                });
                this.scrollToSegment(this.state.lxq_issues[this.state.current_counter - 1]);
                break;
        }

    }

    getCurrentArray() {
        switch (this.state.selected_box) {
            case 'issues':
                return this.state.issues;
            case 'lxq':
                return this.state.lxq_issues;
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
        if ( counter  === current_array.length) {
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

    componentDidMount() {

    }


    componentWillUnmount() {

    }

    allowHTML(string) {
        return { __html: string };
    }

    render() {
        var issues_html = '';
        var counter;
        var lxq_container = '';
        var lxq_options = '';
        var buttonClass = 'qa-arrows-disabled';
        if ( this.state.issues.length > 0 ) {
            var selected = '';
            if ( this.state.issues_selected ) {
                counter = <div className="qa-counter">{this.state.current_counter + '/'+ this.state.issues.length}</div>;
                selected = 'selected';
            }
            issues_html = <div className={"qa-issues-container "+ selected} onClick={this.selectBox.bind(this, 'issues')}>
                <span className="icon-qa-issues"/>
                <span className="qa-issues-counter">{this.state.issues.length}</span>
                Issues
            </div>;
        }
        if ( this.state.lxq_issues.length > 0 ) {
            var selected = '';
            if ( this.state.lxq_selected ) {
                counter = <div className="qa-counter">{this.state.current_counter +'/'+ this.state.lxq_issues.length}</div>;
                selected = 'selected';
                lxq_options = <ul className="lexiqa-popup-items">

                    <li className="lexiqa-popup-item">QA checks and guide
                        <a className="lexiqa-popup-icon lexiqa-quide-icon" id="lexiqa-quide-link" href={config.lexiqaServer + '/documentation.html'} target="_blank" alt="Read the quick user guide of lexiqa"/>
                    </li>
                    <li className="lexiqa-popup-item">Full QA report
                        <a className="lexiqa-popup-icon lexiqa-report-icon" id="lexiqa-report-link" target="_blank" alt="Read the full QA report"
                        href={config.lexiqaServer + '/errorreport?id='+LXQ.partnerid+'-' + config.id_job + '-' + config.password+'&type='+(config.isReview?'revise':'translate')}/>
                    </li>
                    <li className="lexiqa-popup-item">Powered by
                        <a className="lexiqa-popup-icon lexiqa-logo-icon" href="http://lexiqa.net" target="_blank" alt="lexiQA logo"/>
                    </li>
                </ul>;
            }
            lxq_container = <div className={"qa-lexiqa-container " + selected} onClick={this.selectBox.bind(this, 'lxq')}>
                <span className="icon-qa-lexiqa"/>
                <span className="qa-lexiqa-counter">{this.state.lxq_issues.length}</span>
                Suggestions
            </div>;

        }
        var current_array = this.getCurrentArray();
        if ( (this.state.lxq_selected || this.state.issues_selected) && current_array.length > 1 ) {
            buttonClass = 'qa-arrows-enabled';
        }
        return  <div className="qa-container">
                    <div className="qa-container-inside">
                        <div className="qa-issues-types">
                            {issues_html}
                            {lxq_container}
                        </div>
                        {lxq_options}
                        <div className="qa-actions">
                            {counter}
                            <div className={'qa-arrows ' + buttonClass}>
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

