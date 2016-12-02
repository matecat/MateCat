var NavBar = require("./NavBar").default;
class Header extends React.Component {
    constructor (props) {
        super(props);
    }

    render () {
        return <NavBar>
                <li><a><i className="material-icons">search</i></a></li>
                <li><a ><i className="material-icons">view_module</i></a></li>
                <li><a><i className="material-icons">refresh</i></a></li>
                <li><a><i className="material-icons">more_vert</i></a></li>
            </NavBar>
    }
}
export default Header ;