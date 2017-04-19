class Navbar extends React.Component {
    constructor (props) {
        super(props);
        this.renderSideNav = this.renderSideNav.bind(this);
    }

    componentDidMount () {
    }

    renderSideNav () {
        return (
            <ul id='nav-mobile' className='side-nav'>
                {this.props.children}
            </ul>
        );
    }

    render () {

        return (
            <nav>
                <div className='nav-wrapper'>
                    <a href="/" className="brand-logo"/>
                    <ul className="right hide-on-med-and-down">
                        {this.props.children}
                    </ul>
                    {this.renderSideNav()}
                    <a className='button-collapse' href='#' data-activates='nav-mobile'>
                        <i className="material-icons">view_headline</i>
                    </a>
                </div>
            </nav>
        );
    }
}

export default Navbar ;