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

        if (this.props.warnings.ERRORS.total > 0) {
            Object.keys(this.props.warnings.ERRORS.categories).map((key, index) => {
                error.push(<button key={index} className="ui button qa-issue"
                                   onClick={this.setCurrentNavigationElements.bind(this, this.props.warnings.ERRORS.categories[key], 'error', key)}>
                    <i className="icon-cancel-circle icon"></i>
                    {this.state.labels[key] ?
                        this.state.labels[key] : key} ({this.props.warnings.ERRORS.categories[key].length})</button>)
            })
        }
        if (this.props.warnings.WARNINGS.total > 0) {
            Object.keys(this.props.warnings.WARNINGS.categories).map((key, index) => {
                if (key !== 'MISMATCH') {
                    warning.push(<button key={index} className="ui button qa-issue"
                                         onClick={this.setCurrentNavigationElements.bind(this, this.props.warnings.WARNINGS.categories[key], 'warning', key)}>
                        <i className="icon-warning2 icon"></i>
                        {this.state.labels[key] ?
                            this.state.labels[key] : key} ({this.props.warnings.WARNINGS.categories[key].length})
                    </button>)
                } else {
                    mismatch = <button key={index} className="ui button qa-issue"
                                       onClick={this.setCurrentNavigationElements.bind(this, this.props.warnings.WARNINGS.categories[key], 'warning', key)}>
                        <i className="icon-warning2 icon"></i>
                        {this.state.labels[key] ?
                            this.state.labels[key] : key} ({this.props.warnings.WARNINGS.categories[key].length})
                    </button>
                }

            })
        }
        if (this.props.warnings.INFO.total > 0) {
            Object.keys(this.props.warnings.INFO.categories).map((key, index) => {
                info.push(<button key={index} className="ui button qa-issue"
                                  onClick={this.setCurrentNavigationElements.bind(this, this.props.warnings.INFO.categories[key], 'info', key)}>
                    {this.state.labels[key] ?
                        this.state.labels[key] : key} ({this.props.warnings.INFO.categories[key].length})</button>)
            })
        }
        return ((this.props.active && this.props.totalWarnings > 0) ? <div className="qa-wrapper">
            <div className="qa-container">
                <div className="qa-container-inside">
                    <div className="qa-issues-list">
                        <div>
                            Segments with:
                        </div>
                        <div className="ui buttons">
                            {error}
                            {warning}
                            {info}
                        </div>
                        {(this.state.currentPriority === 'info' && this.state.currentCategory === 'lexiqa') ?
                            <div className="qa-lexiqa-info">
                                <span>QA</span>
                                <a href={config.lexiqaServer + '/documentation.html'} target="_blank">Guide</a>
                                <a target="_blank" alt="Read the full QA report"
                                   href={config.lexiqaServer + '/errorreport?id=' + LXQ.partnerid + '-' + config.id_job + '-' + config.password + '&type=' + (config.isReview ? 'revise' : 'translate')}>Report</a>
                            </div> : null}
                    </div>
                    {mismatch ? <div className="qa-mismatch">{mismatch}</div> : null}
                    {this.state.navigationList.length > 0 ? <div className="qa-issues-navigator">
                        <div className="qa-actions">
                            {this.state.navigationIndex + 1} / {this.state.navigationList.length} Segments
                            <div className={'qa-arrows qa-arrows-enabled'}>
                                <button className="qa-move-up"
                                        disabled={this.state.navigationIndex - 1 < 0}
                                        onClick={this.scrollToSegment.bind(this, -1)}>
                                    <span className="icon-qa-left-arrow"/>
                                </button>
                                <button className="qa-move-down"
                                        disabled={this.state.navigationIndex + 1 >= this.state.navigationList.length}
                                        onClick={this.scrollToSegment.bind(this, 1)}>
                                    <span className="icon-qa-right-arrow"/>
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

