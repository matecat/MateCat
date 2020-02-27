class RevisionFeedbackModal extends React.Component {


    constructor(props) {
        super(props);
    }

    render() {
        return <div className="shortcuts-modal">
            <div className="matecat-modal-top">
                <h1>Leave a feedback</h1>
            </div>
            <div className="matecat-modal-middle">
                <span>Please leave some feedback for the translator on the job quality</span>
                <textarea style={{width: "100%", height: "100px"}} placeholder="Write here"/>
            </div>
            <div className="matecat-modal-bottom">
                <div className="ui one column grid right aligned">
                    <div className="column">
                        <div className="ui button cancel-button">Submit</div>
                        <div className={"create-team ui primary button open "}>I'll do it later</div>
                    </div>
                </div>
            </div>
        </div>
    }
}


export default RevisionFeedbackModal ;