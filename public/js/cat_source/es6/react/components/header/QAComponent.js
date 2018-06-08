/**
 * React Component for the editarea.

 */
let React = require('react');

class QAComponent extends React.Component {

    constructor(props) {
        super(props);

        this.state = {
            navigationList: [],
            navigationIndex: 0,
            currentPriority: '',
            currentCategory: '',
            labels: {
                TAG: 'Tag',
                LEXIQA: 'Lexiqa',
                GLOSSARY: 'Glossary',
                MISMATCH: 'T. Conflicts'
            }
        };
    }

    scrollToSegment(increment) {
        let newIndex = this.state.navigationIndex + increment;

        if (newIndex < this.state.navigationList.length && newIndex >= 0) {
            let segmentId = this.state.navigationList[newIndex];

            let $segment = $('#segment-' + segmentId);

            if (segmentId) {
                if ($segment.length) {
                    window.location.hash = segmentId;
                } else if ($('#segment-' + segmentId + '-1').length) {
                    window.location.hash = segmentId + '-1';
                }
                UI.scrollSegment($segment, segmentId);
                if ($segment.hasClass('ice-locked')) {
                    UI.editAreaClick($(UI.targetContainerSelector(), $segment), 'moving');
                }
            }
        }
        this.setState({
            navigationIndex: newIndex
        })
    }

    setCurrentNavigationElements(list, priority, category) {

        let segmentId = list[0];

        let $segment = $('#segment-' + segmentId);

        if (segmentId) {
            if ($segment.length) {
                window.location.hash = segmentId;
            } else if ($('#segment-' + segmentId + '-1').length) {
                window.location.hash = segmentId + '-1';
            }
            UI.scrollSegment($segment, segmentId);
            if ($segment.hasClass('ice-locked')) {
                UI.editAreaClick($(UI.targetContainerSelector(), $segment), 'moving');
            }
        }

        this.setState({
            navigationList: list,
            navigationIndex: 0,
            currentPriority: priority,
            currentCategory: category
        })
    }

    allowHTML(string) {
        return {__html: string};
    }

    componentDidMount() {
    }

    componentWillUnmount() {
    }

    render() {

        let mismatch = '',
            error = [],
            warning = [],
            info = [];
        if (this.props.warnings) {
            if (this.props.warnings.ERROR.total > 0) {
                Object.keys(this.props.warnings.ERROR.Categories).map((key, index) => {
                    let activeClass = (this.state.currentPriority === 'error' && this.state.currentCategory === key) ? ' mc-bg-gray' : '';
                    error.push(<button key={index} className={"ui button qa-issue" + activeClass}
                                       onClick={this.setCurrentNavigationElements.bind(this, this.props.warnings.ERROR.Categories[key], 'error', key)}>
                        <i className="icon-cancel-circle icon"></i>
                        {this.state.labels[key] ?
                            this.state.labels[key] : key} <b>({this.props.warnings.ERROR.Categories[key].length})</b>
                    </button>)
                })
            }
            if (this.props.warnings.WARNING.total > 0) {
                Object.keys(this.props.warnings.WARNING.Categories).map((key, index) => {
                    let activeClass = (this.state.currentPriority === 'warning' && this.state.currentCategory === key) ? ' mc-bg-gray' : '';
                    if (key !== 'MISMATCH') {
                        warning.push(<button key={index} className={"ui button qa-issue" + activeClass}
                                             onClick={this.setCurrentNavigationElements.bind(this, this.props.warnings.WARNING.Categories[key], 'warning', key)}>
                            <i className="icon-warning2 icon"></i>
                            {this.state.labels[key] ?
                                this.state.labels[key] : key}
                            <b> ({this.props.warnings.WARNING.Categories[key].length}) </b>
                        </button>)
                    } else {
                        mismatch = <button key={index} className={"ui button qa-issue" + activeClass}
                                           onClick={this.setCurrentNavigationElements.bind(this, this.props.warnings.WARNING.Categories[key], 'warning', key)}>
                            <i className="icon-warning2 icon"></i>
                            {this.state.labels[key] ?
                                this.state.labels[key] : key}
                            <b> ({this.props.warnings.WARNING.Categories[key].length}) </b>
                        </button>
                    }

                })
            }
            if (this.props.warnings.INFO.total > 0) {
                Object.keys(this.props.warnings.INFO.Categories).map((key, index) => {
                    let activeClass = (this.state.currentPriority === 'info' && this.state.currentCategory === key) ? ' mc-bg-gray' : '';
                    info.push(<button key={index} className={"ui button qa-issue" + activeClass}
                                      onClick={this.setCurrentNavigationElements.bind(this, this.props.warnings.INFO.Categories[key], 'info', key)}>
                        {this.state.labels[key] ?
                            this.state.labels[key] : key} <b>({this.props.warnings.INFO.Categories[key].length})</b>
                    </button>)
                })
            }
        }

        return ((this.props.active && this.props.totalWarnings > 0) ? <div className="qa-wrapper">
            <div className="qa-container">
                <div className="qa-container-inside">
                    <div className="qa-issues-list">
                        <div className="label-issues">
                            Segments with:
                        </div>
                        <div className="ui basic tiny buttons">
                            {error}
                            {warning}
                            {info}
                        </div>
                        {(this.state.currentPriority === 'info' && this.state.currentCategory === 'lexiqa') ?
                            <div className="qa-lexiqa-info">
                                <span>QA:</span>
                                <a href={config.lexiqaServer + '/documentation.html'} target="_blank">Guide</a>
                                <a target="_blank" alt="Read the full QA report"
                                   href={config.lexiqaServer + '/errorreport?id=' + LXQ.partnerid + '-' + config.id_job + '-' + config.password + '&type=' + (config.isReview ? 'revise' : 'translate')}>Report</a>
                            </div> : null}
                        <div className="label-issues labl">
                            Repetitions with:
                        </div>
                        {mismatch ? <div className="qa-mismatch">
                            <div className="ui basic tiny buttons">
                                {mismatch}
                            </div>
                        </div> : null}
                    </div>
                    {this.state.navigationList.length > 0 ? <div className="qa-issues-navigator">
                        <div className="qa-actions">
                            <div className={'qa-arrows qa-arrows-enabled'}>
                                <button className="qa-move-up ui basic button"
                                        disabled={this.state.navigationIndex - 1 < 0}
                                        onClick={this.scrollToSegment.bind(this, -1)}>
                                    <i className="icon-chevron-left"/>
                                </button>
                                <div className="info-navigation-issues">
                                    <b>{this.state.navigationIndex + 1} </b> / {this.state.navigationList.length} {/*Segments*/}
                                </div>
                                <button className="qa-move-down ui basic button"
                                        disabled={this.state.navigationIndex + 1 >= this.state.navigationList.length}
                                        onClick={this.scrollToSegment.bind(this, 1)}>
                                    <i className="icon-chevron-right"/>
                                </button>
                            </div>
                        </div>
                    </div> : null}

                </div>
            </div>
        </div> : null)
    }
}

export default QAComponent;

