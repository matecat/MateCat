
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
    }

    static togglePanel() {
        $('.qa-container').toggleClass("qa-open");
        $('.qa-container').slideToggle()
    }

    setIssues(issues) {
        this.setState({ issues: issues });
    }

    selectBox(type) {
        switch (type) {
            case 'issues':
                this.setState({
                    issues_selected: true,
                    current_counter: 1,
                    selected_box: type
                });
                break;
            case 'lxq':
                this.setState({
                    lxq_selected: true,
                    current_counter: 1,
                    selected_box: type
                });
                break;
        }
    }

    getCurrentArray() {
        switch (this.state.selected_box) {
            case 'issues':
                current_array = this.state.issues;
                UI.scrollSegment(current_array[this.state.current_counter]);
                break;
            case 'lxq':
                current_array = this.state.lxq_issues;
                break;
        }
    }

    moveUp() {
        var current_array = this.getCurrentArray();
        if ( this.state.selected_box === '' ) return;

        var counter = this.state.current_counter;
        if ( counter  === 1) {
            this.setState({
                current_counter: current_array.length
            });
        }  else {
            this.setState({
                current_counter: this.state.current_counter - 1
            });
        }


    }

    moveDown() {
        var current_array = [];
        if ( this.state.selected_box === '' ) return;
        switch (this.state.selected_box) {
            case 'issues':
                current_array = this.state.issues;
                break;
            case 'lxq':
                current_array = this.state.lxq_issues;
                break;
        }
        var counter = this.state.current_counter;
        if ( counter  === current_array.length) {
            this.setState({
                current_counter: 1
            });
        } else {
            this.setState({
                current_counter: this.state.current_counter +1
            });
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
                counter = <div className="qa-counter">{'1/'+ this.state.lxq_issues.length}</div>;
                selected = 'selected';
            }
            lxq_container = <div className={"qa-lexiqa-container " + selected}>
                <span className="icon-qa-lexiqa"/>
                <span className="qa-lexiqa-counter">{this.state.lxq_issues.length}</span>
                Suggestions
            </div>;

        }
        return  <div className="qa-container">
                    <div className="qa-container-inside">
                        <div className="qa-issues-types">
                            {issues_html}
                            {lxq_container}
                        </div>
                        <div className="qa-actions">
                            {counter}
                            <div className="qa-arrows">
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

