<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 13/02/2017
 * Time: 15:01
 */

namespace View\API\V2\Json;


use Model\Teams\MembershipStruct;
use ReflectionException;

class Membership
{

    /**
     * @var MembershipStruct[]
     */
    protected array $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
     * @throws ReflectionException
     */
    public function renderItem(MembershipStruct $membership): array
    {
        $out = [
            'id' => $membership->id ?? 0,
            'id_team' => $membership->id_team,
        ];

        if (!is_null($membership->getUser())) {
            $out['user'] = User::renderItem($membership->getUser());
        }

        $metadata = UserMetadata::renderMetadataCollection($membership->getUserMetadata());
        if (!empty($metadata)) {
            $out['user_metadata'] = array_filter($metadata);
        }

        $out['projects'] = $membership->getAssignedProjects();

        return $out;
    }

    /**
     * @throws ReflectionException
     */
    public function render(): array
    {
        $out = [];
        foreach ($this->data as $membership) {
            $out[] = $this->renderItem($membership);
        }

        return $out;
    }

    /**
     * @throws ReflectionException
     */
    public function renderPublic(): array
    {
        $out = [];
        foreach ($this->data as $membership) {
            $render = $this->renderItemPublic($membership);
            if ($render) {
                $out[] = $render;
            }
        }

        return $out;
    }

    /**
     * @throws ReflectionException
     */
    public function renderItemPublic(MembershipStruct $membership): false|array
    {
        if (!is_null($membership->getUser())) {
            return User::renderItemPublic($membership->getUser());
        } else {
            return false;
        }
    }


}