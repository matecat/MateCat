import {ComponentExtendInterface} from '../../utils/ComponentExtendInterface'

/**
 * Extra links a plugin can add to the header's "more" menu.
 *
 * Two mechanisms work at once, deliberately, so core and the plugin submodules do
 * not have to land in the same instant:
 *
 *   - new: `headerInterface.getMoreLinks = fn` sets an own property
 *   - old: `HeaderInterface.prototype.getMoreLinks = fn` still reaches the
 *     singleton through the prototype chain
 *
 * Core reads through the default export at call time, so whichever a deployment's
 * plugins use is seen. Once every plugin assigns on the object, the class can go.
 */
export class HeaderInterface extends ComponentExtendInterface {
  getMoreLinks() {}
}

const headerInterface = new HeaderInterface()

export default headerInterface
