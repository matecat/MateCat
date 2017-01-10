var SearchInput = require("./SearchInput").default;
var FilterProjects = require("./FilterProjects").default;

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
                        <div className="nav-wrapper">
                            <div className="container-fluid">
                                <div className="row">
                                    <div className="col m4 l1 offset-l1 logo-col" >
                                        <a href="/" className="logo"/>
                                    </div>
                                    <div className="container">
                                        <div className="col l4 s3 offset-s3">
                                            <SearchInput
                                                closeSearchCallback={this.props.closeSearchCallback}
                                                onChange={this.props.searchFn}/>
                                            </div>
                                        <div className="col l2 right">
                                            <FilterProjects
                                                filterFunction={this.props.filterFunction}/>
                                        </div>

                                        <div className="col l2 right profile-area">
                                            <ul className="right">
                                                <li>
                                                    <a href="" className="right waves-effect waves-light">
                                                        <span id="nome-cognome" className="hide-on-med-and-down">Nome Cognome </span>
                                                        <button className="btn-floating btn-flat waves-effect waves-dark z-depth-0 center hoverable">RS</button>
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </nav>
                </section>;
    }
}
export default Header ;