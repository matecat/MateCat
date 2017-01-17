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

                                    <div className="col m2 offset-m8">
                                        <div className="ui floating dropdown labeled icon button">
                                          <i className="filter icon"></i>
                                          <span className="text">Personal </span>
                                          <div className="menu">
                                            <div className="ui right action input">
                                              <input type="text" placeholder="Create new Workspace" />
                                              <div className="ui teal button">
                                                <i className="add icon"></i>
                                                Add
                                              </div>
                                            </div>
                                            <div className="divider"></div>
                                            {/*<div className="header">
                                              <i className="tags icon"></i>
                                              Tag Label
                                            </div>*/}
                                            <div className="scrolling menu">
                                              <div className="item active selected">
                                                <div className="ui red empty circular label"></div>
                                                Personal
                                              </div>
                                              <div className="item">
                                                <div className="ui red empty circular label"></div>
                                                Ebay
                                              </div>
                                              <div className="item">
                                                <div className="ui blue empty circular label"></div>
                                                MSC
                                              </div>
                                              <div className="item">
                                                <div className="ui black empty circular label"></div>
                                                Translated
                                              </div>
                                              <div className="item">
                                                <div className="ui purple empty circular label"></div>
                                                Bamboo
                                              </div>
                                              <div className="item">
                                                <div className="ui orange empty circular label"></div>
                                                YouTube
                                              </div>
                                              <div className="item">
                                                <div className="ui empty circular label"></div>
                                                MyMemory
                                              </div>
                                              <div className="item">
                                                <div className="ui yellow empty circular label"></div>
                                                MateCat
                                              </div>
                                              <div className="item">
                                                <div className="ui pink empty circular label"></div>
                                                Chi ne ha più ne metta
                                              </div>
                                              <div className="item">
                                                <div className="ui green empty circular label"></div>
                                                Siamo una squadra fortissimi
                                              </div>
                                            </div>
                                          </div>
                                        </div>
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
                    <SubHeader
                        filterFunction={this.props.filterFunction}
                        closeSearchCallback={this.props.closeSearchCallback}
                        searchFn={this.props.searchFn}
                        />
                </section>;
    }
}
export default Header ;