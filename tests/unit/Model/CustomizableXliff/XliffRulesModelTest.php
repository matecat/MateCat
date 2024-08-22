<?php
/**
 * Created by PhpStorm.
 * @author hashashiyyin domenico@translated.net / ostico@gmail.com
 * Date: 22/08/24
 * Time: 17:18
 *
 */

use TestHelpers\AbstractTest;
use Xliff\DTO\AbstractXliffRule;
use Xliff\DTO\DefaultRule;
use Xliff\DTO\Xliff12Rule;
use Xliff\DTO\XliffRulesModel;

class XliffRulesModelTest extends AbstractTest {

    /**
     * @test
     */
    public function shouldNotAcceptDuplicatedStates() {

        $rulesModel = new XliffRulesModel();

        $rule1 = new Xliff12Rule( [ 'needs-l10n' ], 'pre-translated', 'translated', '100' );
        $rule2 = new Xliff12Rule( [ 'exact-match', 'needs-l10n' ], 'pre-translated', 'translated', '100' );

        $this->expectException( DomainException::class );
        $this->expectExceptionMessage( "The same state/state-qualifier cannot be used in two different rules: " . implode( "", [ 'needs-l10n' ] ) );
        $this->expectExceptionCode( 400 );

        $rulesModel->addRule( $rule1 );
        $rulesModel->addRule( $rule2 );

    }

    /**
     * @test
     */
    public function shouldGetTheRightRule() {

        $rulesModel = new XliffRulesModel();

        $defaultRule = new DefaultRule( [ 'translated' ], AbstractXliffRule::_ANALYSIS_PRE_TRANSLATED, null, null );

        $rule1 = new Xliff12Rule( [ 'needs-l10n', 'translated' ], 'pre-translated', 'translated', '100' );
        $rule2 = new Xliff12Rule( [ 'exact-match', 'needs-adaptation' ], 'new' );

        $rulesModel->addRule( $rule1 );
        $rulesModel->addRule( $rule2 );

        $this->assertEquals( $rule2, $rulesModel->getMatchingRule( 1, null, 'exact-match' ) );
        $this->assertEquals( $rule1, $rulesModel->getMatchingRule( 1, 'translated' ) );
        $this->assertEquals( $defaultRule, $rulesModel->getMatchingRule( 1, null, 'translated' ) ); // we are passing a state as state-qualifier
        $this->assertEquals( $defaultRule, $rulesModel->getMatchingRule( 2, 'translated' ) ); // there is not 2.0 rule defined

    }

}