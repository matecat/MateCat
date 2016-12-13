var SearchInput = require("./SearchInput").default;
class Header extends React.Component {
    constructor (props) {

        super(props);
        this.imgStyle = {
            float: 'left',
            border: '0',
            marginTop: '10px',
            background: 'url(http://matecat.dev/public/img/logo.png) 0px 2px no-repeat',
            width: '145px',
            height: '31px',
            backgroundSize: '130px 26px',
        }
    }
    componentDidMount () {
    }


    render () {
        return <section className="nav-mc-bar">
                    <nav role="navigation">
                        <div className="nav-wrapper container">
                            <div className="row">
                                <div className="col s4">
                                    <a id="logo-container" href="#">
                                        <img src="public/img/logo-matecat2.png" className="responsive-img middle-v"/>
                                    </a>
                                </div>
                                <SearchInput
                                    closeSearchCallback={this.props.closeSearchCallback}
                                    onChange={this.props.searchFn}/>
                                <div className="col s4">
                                    <ul className="right">
                                        <li>
                                            <a href="" className="right waves-effect waves-light">
                                                <span id="nome-cognome">Nome Cognome </span>
                                                <button className="btn-floating btn-flat waves-effect waves-dark z-depth-0 center hoverable">RS</button>
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </nav>
                </section>;
    }
}
export default Header ;