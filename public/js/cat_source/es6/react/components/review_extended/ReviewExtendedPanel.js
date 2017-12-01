class ReviewExtendedPanel extends React.Component{

    constructor(props) {
        super(props);
        this.state = {

        };

    }


    componentDidMount() {

    }

    componentWillUnmount() {

    }

    render() {
        return <div className="re-track-changes-box">
            <div className="re-header-track">
                <h4>Revise Track changes</h4>
                <div className="re-track-changes">
                    Prova <span className="deleted"> per track</span> changes <span className="added">che bella</span> la vita
                </div>
            </div>
        </div>;
    }
}

export default ReviewExtendedPanel ;
