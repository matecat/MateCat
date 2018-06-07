/**
 * React Component for the warnings.

 */
var React = require('react');
var SegmentConstants = require('../../constants/SegmentConstants');

class SegmentWarnings extends React.Component {

    constructor(props) {
        super(props);
        this.state = {};
    }

    componentDidMount() {
    }

    componentWillUnmount() {
    }

    render() {
        let warnings_count = {};
        let warnings = [];

        if (this.props.warnings) {
            if (this.props.warnings.ERROR) {
                this.props.warnings.ERROR.map((el, index) => {
                    if (warnings_count[el.outcome]) {
                        warnings_count[el.outcome]++;
                    } else {
                        let item = el;
                        item.type = 'ERROR';
                        warnings.push(item);
                        warnings_count[el.outcome] = 1;
                    }
                });
            }
            if (this.props.warnings.WARNING) {
                this.props.warnings.WARNING.map((el, index) => {
                    if (warnings_count[el.outcome]) {
                        warnings_count[el.outcome]++;
                    } else {
                        let item = el;
                        item.type = 'WARNING';
                        warnings.push(item);
                        warnings_count[el.outcome] = 1;
                    }
                });

            }
            if (this.props.warnings.INFO) {
                this.props.warnings.INFO.map((el, index) => {
                    if (warnings_count[el.outcome]) {
                        warnings_count[el.outcome]++;
                    } else {
                        let item = el;
                        item.type = 'INFO';
                        warnings.push(item);
                        warnings_count[el.outcome] = 1;
                    }
                });
            }
        }

        return <div className="warnings-block">

            {
                warnings.map((el, index) => {
                    let classes_block,
                        classes_icon;
                    switch (el.type) {
                        case 'ERROR':
                            classes_block = 'error-alert alert-block';
                            classes_icon = 'icon-cancel-circle icon';
                            break;
                        case 'WARNING':
                            classes_block = 'warning-alert alert-block';
                            classes_icon = 'icon-warning2 icon';
                            break;
                        case 'INFO':
                            classes_block = 'info-alert alert-block';
                            classes_icon = 'icon-info icon';
                            break;
                        default:
                            classes_block = 'alert-block';
                            classes_icon = 'icon-cancel-circle icon';
                            break
                    }
                    return (<div key={index} className={classes_block}>
                        <ul>
                            <li className="icon-column">
                                <i className={classes_icon}></i>
                            </li>
                            <li className="content-column">
                                <p>{el.debug} <b>({warnings_count[el.outcome]})</b></p>
                                {el.tip !== '' ?
                                    ( <p className="error-solution"><b>{el.tip}</b></p>)
                                    : null
                                }
                            </li>
                        </ul>
                    </div>)
                })
            }
        </div>;
    }
}

export default SegmentWarnings;

