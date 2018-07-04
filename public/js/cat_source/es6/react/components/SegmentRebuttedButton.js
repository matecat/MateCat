class SegmentRebuttedButton extends React.Component{

    constructor(props) {
        super(props);
        this.state = {
            disabled: false,
        };
    }

    handleClick() {
        window.ReviewImproved.clickOnRebutted(this.props.sid);
    }

    componentDidMount() {
    }

    componentWillUnmount() {
    }

    render() {
        var cmd = ((UI.isMac) ? 'CMD' : 'CTRL');

        return <li>
            <a className="button button-rebutted status-rebutted"
               onClick={this.handleClick.bind(this)}
               href="javascript:;"
               disabled={!this.state.disabled} >
                Rebutted
            </a>
            <p>{window.UI.shortcutLeader}+ENTER</p>
        </li>
            ;

    }
}

export default SegmentRebuttedButton ;
