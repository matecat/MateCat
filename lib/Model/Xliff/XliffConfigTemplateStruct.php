<?php

namespace Xliff;

use DataAccess_AbstractDaoSilentStruct;
use Date\DateTimeUtil;
use DomainException;
use JsonSerializable;
use RecursiveArrayObject;
use Xliff\DTO\AbstractXliffRule;
use Xliff\DTO\DefaultRule;
use Xliff\DTO\Xliff12Rule;
use Xliff\DTO\Xliff20Rule;
use Xliff\DTO\XliffRuleInterface;

class XliffConfigTemplateStruct extends DataAccess_AbstractDaoSilentStruct implements JsonSerializable {

    const XLIFF_12 = 'xliff12';
    const XLIFF_20 = 'xliff20';

    public $id;
    public $name;
    public $uid;
    public $created_at;
    public $modified_at;
    public $deleted_at;

    /**
     * @var array
     */
    private $ruleSets = [
        self::XLIFF_12 => [],
        self::XLIFF_20 => [],
    ];

    /**
     * @param XliffRuleInterface $rule
     */
    public function addRule( XliffRuleInterface $rule ): void {
        if ( $rule instanceof Xliff20Rule ) {
            $this->validateDuplicatedStates( $rule, self::XLIFF_20 );
            $this->ruleSets[ self::XLIFF_20 ][] = $rule;
        } elseif ( $rule instanceof Xliff12Rule ) {
            $this->validateDuplicatedStates( $rule, self::XLIFF_12 );
            $this->ruleSets[ self::XLIFF_12 ][] = $rule;
        }
    }

    /**
     * @param XliffRuleInterface $rule
     * @param string             $type
     *
     * @return void
     */
    public function validateDuplicatedStates( XliffRuleInterface $rule, string $type ): void {
        foreach ( $this->ruleSets[ $type ] as $existentRule ) {
            $stateIntersect = array_intersect( $existentRule->getStates(), $rule->getStates() );
            if ( !empty( $stateIntersect ) ) {
                throw new DomainException( "The same state/state-qualifier cannot be used in two different rules: " . implode( ", ", $stateIntersect ), 400 );
            }
        }
    }

    /**
     * @param RecursiveArrayObject $structure
     *
     * @return static
     */
    public static function fromArrayObject( RecursiveArrayobject $structure ): XliffConfigTemplateStruct {
        $self = new static();
        foreach ( $structure as $ruleType => $ruleSet ) {

            if ( $ruleType == self::XLIFF_12 ) {
                $ruleClass = Xliff12Rule::class;
            } elseif ( $ruleType == self::XLIFF_20 ) {
                $ruleClass = Xliff20Rule::class;
            } else {
                throw new DomainException( "Invalid Rule: " . $ruleType, 400 );
            }

            /** @var AbstractXliffRule $ruleClass */
            foreach ( $ruleSet as $rule ) {
                $self->addRule( $ruleClass::fromArrayObject( $rule ) );
            }

        }

        return $self;
    }

    /**
     * @param int $versionNumber
     *
     * @return XliffRuleInterface[]
     */
    public function getRulesForVersion( int $versionNumber ): array {
        if ( $versionNumber == 1 ) {
            return $this->ruleSets[ static::XLIFF_12 ];
        } elseif ( $versionNumber == 2 ) {
            return $this->ruleSets[ static::XLIFF_20 ];
        } else {
            throw new DomainException( "Invalid Version: " . $versionNumber, 400 );
        }
    }

    /**
     * @param int         $versionNumber
     * @param string|null $state
     * @param string|null $stateQualifier
     *
     * @return DefaultRule
     */
    public function getMatchingRule( int $versionNumber, string $state = null, string $stateQualifier = null ): DefaultRule
    {
        // here we must analyze and check only for editor status
        foreach ( $this->getRulesForVersion( $versionNumber ) as $rule ) {

            if ( $stateQualifier !== null && in_array( strtolower( $stateQualifier ), $rule->getStates( 'state-qualifiers' ) ) ) {
                return $rule;
            }

            if ( $state !== null && in_array( strtolower( $state ), $rule->getStates( 'states' ) ) ) {
                return $rule;
            }
        }

        return new DefaultRule( array_filter( [ $state, $stateQualifier ] ), AbstractXliffRule::_ANALYSIS_PRE_TRANSLATED, null, null );
    }

    /**
     * @param $json
     * @param null $uid
     * @return $this
     */
    public function hydrateFromJSON( $json, $uid = null )
    {
        $json = json_decode( $json, true );

        if ( !isset( $json[ 'name' ] ) ) {
            throw new DomainException( "Cannot instantiate a new XliffConfigStruct. Invalid data provided.", 400 );
        }

        if ( empty( $uid ) && empty( $json[ 'uid' ] ) ) {
            throw new DomainException( "Cannot instantiate a new XliffConfigStruct. Invalid user id provided.", 400 );
        }

        $this->uid  = $json[ 'uid' ] ?? $uid;
        $this->name = $json[ 'name' ];

        if ( isset( $json[ 'id' ] ) ) {
            $this->id = $json[ 'id' ];
        }

        if ( isset( $json[ 'created_at' ] ) ) {
            $this->created_at = $json[ 'created_at' ];
        }

        if ( isset( $json[ 'deleted_at' ] ) ) {
            $this->deleted_at = $json[ 'deleted_at' ];
        }

        if ( isset( $json[ 'modified_at' ] ) ) {
            $this->modified_at = $json[ 'modified_at' ];
        }

        // xliff12
        if(isset($json[ 'rules' ])){
            if ( isset( $json[ 'rules' ][ 'xliff12' ] ) and is_array( $json[ 'rules' ][ 'xliff12' ] ) ) {
                foreach ( $json[ 'rules' ][ 'xliff12' ] as $xliff12Rule ) {
                    $rule = new Xliff12Rule( $xliff12Rule[ 'states' ], $xliff12Rule[ 'analysis' ], $xliff12Rule[ 'editor' ], $xliff12Rule[ 'match_category' ] ?? null );
                    $this->addRule( $rule );
                }
            }

            // xliff20
            if ( isset( $json[ 'rules' ][ 'xliff20' ] ) and is_array( $json[ 'rules' ][ 'xliff20' ] ) ) {
                foreach ( $json[ 'rules' ][ 'xliff20' ] as $xliff20Rule ) {
                    $rule = new Xliff20Rule( $xliff20Rule[ 'states' ], $xliff20Rule[ 'analysis' ], $xliff20Rule[ 'editor' ], $xliff20Rule[ 'match_category' ] ?? null );
                    $this->addRule( $rule );
                }
            }
        }

        return $this;
    }

    /**
     * @return string
     * @throws \Exception
     */
    public function getRulesAsString(): string
    {
        return json_encode($this->ruleSets);
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function jsonSerialize(): array {

        return [
            'id'         => (int)$this->id,
            'uid'        => (int)$this->uid,
            'rules'      => $this->ruleSets,
            'createdAt'  => DateTimeUtil::formatIsoDate( $this->created_at ),
            'modifiedAt' => DateTimeUtil::formatIsoDate( $this->modified_at ),
            'deletedAt'  => DateTimeUtil::formatIsoDate( $this->deleted_at ),
        ];
    }
}
