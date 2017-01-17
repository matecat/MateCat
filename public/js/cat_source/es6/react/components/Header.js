// var SearchInput = require("./SearchInput").default;
// var FilterProjects = require("./FilterProjects").default;

var SubHeader = require("./SubHeader").default;

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
                                        <a href="/" className="logo logo-col"/>
                                    
                                
                                    {/*<div className="col l4 offset-l4 m4 offset-m4">
                                        <SearchInput
                                            closeSearchCallback={this.props.closeSearchCallback}
                                            onChange={this.props.searchFn}/>
                                    </div>
                                    <div className="col l2 m2 s4 right right-60">
                                        <FilterProjects
                                            filterFunction={this.props.filterFunction}/>
                                    </div>*/}

                                    <div className="input-field col m2 offset-m8">
                                        <select className="list-member-team">
                                            {/*<option><input value="Alvin" id="first_name2" type="search" className="validate" /></option>*/}
                                            <option value="" selected="selected">Personal
                                                <a className="btn-floating btn-flat waves-effect waves-dark z-depth-0 class-prova">
                                                    <i className="icon-more_vert"></i>
                                                </a>
                                            </option>
                                            <option value="1">Option 1</option>
                                            <option value="2">Option 2</option>
                                            <option value="3">Option 3</option>
                                        </select>
                                       
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
                    </nav>
                    <SubHeader/>
                </section>;
    }
}
export default Header ;