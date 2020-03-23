import IconQR from "../icons/IconQR";
import CatToolStore from "../../stores/CatToolStore";
import CattoolConstants from "../../constants/CatToolConstants";
import classnames from "classnames";

class QualityReportButton extends React.Component {
    constructor(props) {
        super(props);
        this.state = {
            is_pass : null,
            score: null, 
            vote: this.props.vote,
            progress: null
        };
    }

    getVote() {
        if ( this.state.is_pass != null ) {
            if ( this.state.is_pass ) {
                return 'excellent'; 
            }
            else {
                return 'fail'; 
            }
        }

        else {
            return this.state.vote ;
        }
    }

    updateProgress = (stats) => {
        this.setState({
            progress: stats
        });
    };

    openFeedbackModal = (e) => {
        e.preventDefault();
        e.stopPropagation();
        CatToolActions.openFeedbackModal(this.state.feedback);
    };

    componentDidMount() {
        CatToolStore.addListener(CattoolConstants.SET_PROGRESS, this.updateProgress);
    }


    componentWillUnmount() {
        CatToolStore.removeListener(CattoolConstants.SET_PROGRESS, this.updateProgress);
    }

    render() {
        let classes,label,menu;
        if ( this.state.progress && config.isReview ) {
            if ( ( config.revisionNumber === 1 ) || ( config.revisionNumber === 2  ) ){
                classes = classnames({
                    'ui simple pointing top center floating dropdown': true
                });
                label = "Feedback R" + config.revisionNumber;
                menu = <ul className="menu" id="qualityReportMenu">
                    <li className="item">
                        <a title="Revision Feedback" onClick={(e) => this.openFeedbackModal(e)}>
                            {label}
                        </a>
                    </li>
                </ul>
            }
        }

        return <div id="quality-report"
        className={classes}
        data-vote={this.getVote()} 
        onClick={()=>window.open(this.props.quality_report_href, '_blank')}>
            <IconQR width={30} height={30}/>
            <div className="dropdown-menu-overlay"/>
            {menu}
        </div> ;
    }
}

export default QualityReportButton ; 
